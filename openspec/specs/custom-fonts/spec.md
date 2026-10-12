---
status: done
---

# Custom Fonts Specification

## Purpose
Let a licensed admin upload their organization's own webfonts (e.g. RijksoverheidSans, a
municipal corporate font) so nldesign can theme with the real house style typography instead
of only the bundled Fira Sans (SIL OFL) open proxy. nldesign cannot ship these fonts itself —
they are proprietary — but it can let the license holder upload them: self-hosted (no external
CDN, CSP-clean, works on air-gapped instances), content-validated (WOFF2 only, verified by
magic bytes, never extension or client MIME type), and capped (2 MB per file, 20 fonts per
instance). A missing or unloadable font always degrades to exactly the bundled Fira Sans
fallback chain. Backs nextcloud/server#46043 (no font facility upstream).

## Requirements

### Requirement: Font Upload (woff2 only, content-validated)

The admin settings panel MUST provide an upload control for organization fonts. Only WOFF2 is
accepted, validated by content: the first four bytes MUST be exactly `wOF2`; the file
extension and client-supplied MIME type MUST NOT be trusted. Uploads over 2 MB MUST be
rejected with HTTP 413, non-WOFF2 content with HTTP 422 naming the failed check. Each font
gets an id `custom-{slug}` where the slug derives from the admin-supplied display name using
the same contract as custom token sets (`[a-z0-9-]`, max 64 chars); id collisions MUST be
rejected with HTTP 409 without modifying the existing font. At most 20 fonts MAY be stored
per instance. The upload endpoint MUST be admin-only (`AuthorizedAdminSetting`) and
CSRF-protected, mirroring the custom-token-set upload posture.

#### Scenario: Valid woff2 upload succeeds
@e2e exclude covered by FontServiceTest::testStoreWritesFileAndManifest and
FontControllerTest upload-success case (PHPUnit)

- GIVEN an admin uploads a file whose bytes begin with `wOF2`, 180 KB, display name
  "Rijks Sans", role `body`
- WHEN the upload is processed
- THEN the file MUST be stored in appdata as `fonts/custom-rijks-sans.woff2`
- AND a manifest entry MUST be written to the `custom_fonts` appconfig key with name, role,
  size, and upload timestamp
- AND the response MUST report the id `custom-rijks-sans`

#### Scenario: Renamed non-woff2 file is rejected by magic bytes
@e2e exclude covered by FontValidatorTest hardening corpus — TTF/OTF/WOFF1/zip/renamed-text
(PHPUnit)

- GIVEN a TrueType font renamed to `font.woff2` (bytes begin `\x00\x01\x00\x00`) uploaded
  with MIME type `font/woff2`
- WHEN the upload is validated
- THEN it MUST be rejected with HTTP 422 identifying the content check that failed
- AND no file MUST be written and no manifest entry created

#### Scenario: Oversized upload is rejected
@e2e exclude covered by FontValidatorTest::testOversizeRejected and
FontControllerTest oversize case (PHPUnit)

- GIVEN an upload larger than 2 MB
- WHEN the upload is processed
- THEN it MUST be rejected with HTTP 413 before the content is stored

#### Scenario: Filename sanitization and no path traversal
@e2e exclude covered by FontServiceTest path-traversal-id cases (delete/getFont) and
FontValidatorTest display-name cases (PHPUnit)

- GIVEN a display name or id containing `/`, `..`, or a NUL byte (e.g.
  `../../config/config`)
- WHEN any font operation (upload, serve, delete) processes it
- THEN the value MUST be rejected (upload: HTTP 422) or resolve to not-found (serve/delete:
  HTTP 404) purely via manifest lookup
- AND no user-supplied string MUST ever be concatenated into an appdata filesystem path

#### Scenario: Upload endpoint is admin-only and CSRF-protected
@e2e exclude covered by FontControllerTest reflection-based auth-posture assertion
(PHPUnit); live non-admin-rejection curl check deferred to tasks.md#task-6.4

- GIVEN a non-admin authenticated user
- WHEN they POST to `/settings/fonts/upload`
- THEN Nextcloud's SecurityMiddleware MUST reject the request (`AuthorizedAdminSetting`
  posture, no `NoCSRFRequired` on the method), identically to
  `customTokenSet#upload`

### Requirement: Uploader License Responsibility

The app MUST NOT ship or download any proprietary font: government typefaces
(RijksoverheidSans, municipal corporate fonts) are proprietary, and nldesign ships Fira Sans
only as an open proxy. The upload UI MUST display, above the upload control, a notice stating that only fonts
the organization is licensed to self-host may be uploaded and that licensing responsibility
rests with the uploader. The notice MUST be a translatable string with an ENGLISH source key.

#### Scenario: License notice is visible before upload
@e2e exclude covered by a template-content assertion in AdminFontNoticeTest (PHPUnit);
live visual placement check deferred to tasks.md#task-6.3

- GIVEN an admin opens the Custom fonts section
- WHEN the section renders
- THEN the license-responsibility notice MUST be visible above the upload control without
  further interaction
- AND the string MUST come from the app's l10n catalog (English key, Dutch translation
  available)

### Requirement: Self-Hosted CSP-Clean Font Serving

Stored fonts MUST be served exclusively from the instance itself via
`GET /apps/nldesign/fonts/{id}.woff2`. The route MUST be deliberately public —
`#[PublicPage]` + `#[NoCSRFRequired]` with the rationale documented at the annotation: CSS
`url()` font loads carry no CSRF token and no session guarantee and MUST work on the
pre-login page; the route serves admin-curated static binaries addressed by opaque manifest
id. Responses MUST carry `Content-Type: font/woff2`, `Cache-Control: public,
max-age=31536000, immutable`, and an ETag derived from the font revision. Unknown ids MUST
return 404 without detail. No font source outside the instance origin MUST ever be
referenced.

#### Scenario: Font loads without authentication
@e2e exclude PublicPage attribute + cache headers covered by FontControllerTest (PHPUnit);
live unauthenticated curl check deferred to tasks.md#task-6.2

- GIVEN a stored font `custom-rijks-sans`
- WHEN an unauthenticated request fetches `/apps/nldesign/fonts/custom-rijks-sans.woff2`
- THEN the response MUST be 200 with `Content-Type: font/woff2` and the immutable cache
  headers
- AND the same URL MUST load successfully from the login page with no CSP violation and no
  request to any external host

#### Scenario: Unknown font id returns 404
@e2e exclude covered by FontServiceTest::testGetFontReturnsNullForUnknownIdEvenIfFileExists and
FontControllerTest 404 case (PHPUnit)

- GIVEN no manifest entry `custom-ghost`
- WHEN `/apps/nldesign/fonts/custom-ghost.woff2` is requested
- THEN the response MUST be 404 with no body detail
- AND this MUST hold even if a stray file of that name exists in appdata (the manifest is
  the authorization gate for what is served)

### Requirement: Font Token Mapping With Preserved Fallback Chain

Each uploaded font MUST be assigned a role (`body` and/or `heading`). The app MUST serve a
generated stylesheet at `GET /apps/nldesign/fonts/css` (same public posture and caching as
the binary route, ETag = font revision) containing one `@font-face` rule per font
(`src: url(<self-hosted serve URL>) format('woff2')`, `font-display: swap`) and `:root`
overrides of the font tokens: `--nldesign-font-family` for the `body` role and the heading
font token for the `heading` role. The generated `font-family` list MUST place the uploaded
family first and preserve the existing Fira Sans fallback chain verbatim after it, so a
missing or unloadable font degrades to exactly the current rendering. The stylesheet MUST be
injected in `Application::boot()` after the token-set styles, and MUST NOT be injected at
all when no fonts are configured. Display names MUST be CSS-string-escaped before
interpolation into the generated stylesheet.

#### Scenario: Body font override with intact fallback
@e2e exclude covered by FontServiceTest::testBuildCssBodyOverride (PHPUnit); live
computed-style check deferred to tasks.md#task-6.3

- GIVEN a stored `body`-role font "Rijks Sans"
- WHEN `/apps/nldesign/fonts/css` is served
- THEN it MUST contain an `@font-face` for `"Rijks Sans"` pointing at the self-hosted serve
  URL with `format('woff2')` and `font-display: swap`
- AND `--nldesign-font-family` MUST begin with `"Rijks Sans"` followed by the unmodified
  Fira Sans fallback chain

#### Scenario: No fonts configured means no injection
@e2e exclude covered by FontServiceTest::testHasFontsFalseWhenEmpty (PHPUnit); the
Application::boot() injection guard itself is exercised live only, consistent with the
existing hide-slogan/show-menu-labels conditional injectors in this app (see
tests/e2e/workflows/checkbox-toggle-persistence.workflow.spec.ts) — deferred to
tasks.md#task-6.3

- GIVEN zero entries in the `custom_fonts` manifest
- WHEN any themed page renders
- THEN no `/fonts/css` stylesheet link MUST be injected
- AND rendering MUST be byte-identical to the pre-feature CSS stack

#### Scenario: Display name cannot break the generated stylesheet
@e2e exclude covered by FontServiceTest::testBuildCssEscapesDisplayName (PHPUnit)

- GIVEN a font stored with display name `Test"Font` (embedded quote)
- WHEN the stylesheet is generated
- THEN the name MUST appear CSS-escaped inside the `@font-face` and token values
- AND the stylesheet MUST remain parseable with no injected rules

### Requirement: Font Management Lifecycle

The admin panel MUST list stored fonts (name, role, size) and provide delete. Deleting a
font MUST remove the appdata file and its manifest entry, bump the font revision (cache
bust), and cause the generated stylesheet to drop its rules so rendering falls back to the
shipped chain. List and delete endpoints MUST be admin-only and CSRF-protected.

#### Scenario: Admin deletes a font
@e2e exclude covered by FontServiceTest::testDeleteRemovesFileManifestAndBumpsRev and
FontControllerTest delete cases (PHPUnit); live rendering-fallback check deferred to
tasks.md#task-6.4

- GIVEN a stored font `custom-rijks-sans` currently referenced by the generated stylesheet
- WHEN the admin deletes it and reloads
- THEN the appdata file and the manifest entry MUST be gone
- AND `/apps/nldesign/fonts/css` MUST no longer reference the family (new ETag)
- AND body text MUST render with the shipped Fira Sans chain

### Requirement: A Bundled Font Stylesheet Is One Something Links

Every stylesheet the app ships under `css/` that declares an `@font-face`, token sets aside, MUST be
linked: listed under a design system in `design-systems.json`, or the public site layer
`css/fonts.css`, or a layer the injection service adds on an administrator's choice. The app SHALL
NOT ship a font stylesheet that nothing links.

#### Scenario: Every font stylesheet is linked

- GIVEN the stylesheets under `css/` that declare an `@font-face`, the token sets left out
- WHEN each is looked up in the design systems' stylesheet lists
- THEN each is in a list, or is `css/fonts.css`, or is the opt-in Marianne layer
- AND `css/fonts-conduction.css` is not shipped

#### Scenario: The faces of the retired stylesheet are still served

- GIVEN the nldesign design system is active
- WHEN a token set names Figtree or IBM Plex Mono
- THEN `css/systems/nldesign/fonts.css` declares that face
- AND the file it points at is in `css/systems/nldesign/fonts/`
