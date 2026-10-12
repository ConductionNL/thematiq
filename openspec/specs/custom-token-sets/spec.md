---
status: done
---

# Custom Token Sets Specification

## Purpose
Admins upload, validate, manage, and activate their own organization token sets ("eigen huisstijl"), in the app native `--nldesign-*` CSS format or the W3C Design Tokens JSON format. Backs GOVERNMENT-FEATURES F-04.

## Requirements

### Requirement: Upload Custom Token Set (CSS format)
The admin settings panel MUST provide an upload control that accepts a CSS file containing `--nldesign-*` custom property declarations and stores it as a new token set with id `custom-{slug}`, where the slug is derived from the admin-supplied display name (`[a-z0-9-]`, max 64 chars).

#### Scenario: Admin uploads a valid CSS token set
- GIVEN an admin on the NL Design settings panel
- AND a file `huisstijl.css` containing `:root { --nldesign-color-primary: #007bc7; --nldesign-color-primary-text: #ffffff; }`
- WHEN the admin uploads it with display name "Gemeente Voorbeeld"
- THEN the server MUST write `css/tokens/custom-gemeente-voorbeeld.css` atomically (temp file + rename)
- AND the response MUST report the set id `custom-gemeente-voorbeeld` and counts `{ imported, skipped }`
- AND the token set dropdown MUST list "Gemeente Voorbeeld" after a panel reload without any other configuration

#### Scenario: Upload endpoint is admin-only and CSRF-protected
@e2e exclude auth-posture assertion — PHPUnit/Newman verify middleware rejection, not a UI flow
- GIVEN a non-admin authenticated user
- WHEN they POST a file to `/settings/tokensets/upload`
- THEN Nextcloud's SecurityMiddleware MUST reject the request (no `NoAdminRequired` on the method)
- AND requests without a CSRF token MUST be rejected (no `NoCSRFRequired` on the method)

#### Scenario: Slug collisions are rejected
@e2e exclude validation branch — PHPUnit on the service
- GIVEN an existing custom set `custom-gemeente-voorbeeld`
- WHEN the admin uploads another file with display name "Gemeente Voorbeeld"
- THEN the upload MUST be rejected with HTTP 409 and a localized message offering to delete or rename
- AND the existing file MUST NOT be modified

#### Scenario: Shipped set ids can never be shadowed
@e2e exclude namespace invariant — PHPUnit on the service
- GIVEN the shipped token set `utrecht`
- WHEN any custom set is created
- THEN its id MUST start with `custom-`
- AND no upload MUST ever write to a path other than `css/tokens/custom-*.css`

### Requirement: CSS Validation Whitelist

No upload may put a dangerous construct into a served token set. That guarantee is carried by TWO
mechanisms, and the distinction matters because they produce different HTTP results for the same
class of payload:

1. **Normalisation.** Every upload is parsed into declarations and re-emitted by
   `TokenSetConverterService`. Anything that is not a well-formed custom property — a smuggled
   second declaration after a `;`, a CSS comment, an at-rule — does not survive the round trip and
   never reaches the emitted file. The upload SUCCEEDS; the payload is simply not in it.
2. **The value gate.** `CustomTokenSetValidator::isForbiddenValue()` MUST refuse, as a hard 422 via
   `CustomTokenSetController::upload()`, any declaration that survives normalisation and still
   carries `@import`, `expression(`, `javascript:`, raw `<`, a disallowed `url()` scheme or host, or
   an injection character (`;`, `{`, `}`, `/*`, `*/`).

The value gate MUST run over EVERY declaration in the emitted file, not only the ones whose name the
whitelist accepts. `store()` writes the converter's emitted CSS rather than `serialize($accepted)`,
and the converter keeps a name it does not recognise verbatim — it parses `--[\w-]+` where the
whitelist accepts only `[a-z0-9-]+` — so a name such as `--utrecht-colorPrimary` is written even
though it is reported as skipped. Judging only the accepted set would leave its value unchecked.

#### Scenario: A smuggled second declaration never reaches the served file

- GIVEN a CSS upload whose `:root` block contains
  `--nldesign-color-primary: red; background: url(https://evil.example/x.png);`
- WHEN the admin uploads the file
- THEN the served CSS MUST NOT contain the smuggled `background` declaration or the external host
- AND `--nldesign-color-primary` MUST be stored as `red`
- AND the upload MAY succeed: the fragment is dropped at parse time rather than refused, because it
  is not a custom property and so is never a declaration the gate is asked about

#### Scenario: A comment marker in a value never reaches the served file

- GIVEN a CSS upload whose `:root` block contains a declaration value containing `/*` or `*/`
- WHEN the admin uploads the file
- THEN the comment MUST be stripped before parsing
- AND the served CSS MUST NOT contain the comment or anything hidden inside it

#### Scenario: A dangerous construct that survives normalisation is a hard 422

- GIVEN a CSS upload declaring `--utrecht-colorPrimary: expression(alert(1))`
- AND that name is kept verbatim by the converter but skipped by the whitelist
- WHEN the admin uploads the file
- THEN the upload MUST fail with HTTP 422 naming that property
- AND no manifest entry and no CSS file MUST be written
- AND the same MUST hold for `javascript:` and for raw `<`, and under an accepted name equally

#### Scenario: The W3C Design Tokens path is gated identically

- GIVEN a W3C Design Tokens JSON upload whose mapped value carries a dangerous construct
- WHEN the admin uploads the file
- THEN it MUST be subject to the same two mechanisms as the CSS path
- AND the gate MUST be `isForbiddenValue()` reached through the converter and
  `CustomTokenSetController::upload()`; there is no separate JSON mapping entry point

#### Scenario: Legitimate values with no injection characters still succeed

- GIVEN a CSS upload with `--nldesign-color-primary: #154273`
- WHEN the admin uploads it
- THEN the upload MUST succeed exactly as before this change (no regression for benign values)
- AND a well-formed component token such as `--utrecht-button-border-radius: 4px` MUST still be
  stored, since the gate judges values and not vocabularies

### Requirement: The Stored File Is The Converted File

An upload is stored as the converter's emitted CSS, not as the bytes the admin supplied. Every
accepted input shape — including hand-authored `--nldesign-*` CSS, which earlier releases stored
verbatim — is normalised into a complete semantic layer, with targets the document did not declare
filled from the mapping table's fallbacks.

#### Scenario: A hand-authored upload is backfilled

- GIVEN a CSS upload declaring only `--nldesign-color-primary` and `--nldesign-color-primary-text`
- WHEN the admin uploads it
- THEN the stored file MUST carry those two values unchanged
- AND it MUST additionally carry the semantic targets derived from them and from the mapping table's
  fallbacks, so the stored set does not resolve through another brand's defaults
- AND `imported` in the response MUST count what the ADMIN'S document yielded, not the size of the
  stored file

#### Scenario: A declared value is never overwritten by a fallback

- GIVEN an upload that declares a target the mapping table also has a fallback for
- WHEN the set is converted
- THEN the admin's value MUST be kept and reported as `kept`
- AND the fallback MUST apply only to targets the document left undeclared

#### Scenario: The backfill is inspectable

- GIVEN any successful upload
- WHEN the response is rendered
- THEN each derived target MUST appear in `report` with the reason it was derived
- AND `counts` MUST separate what was kept from what was adapted

### Requirement: W3C Design Tokens JSON Import

The upload control MUST also accept a JSON file in the W3C DTCG Design Tokens Format Module
v2025.10. The importer MUST implement the format's core semantics, not a lookalike subset:

- **`$type` resolution with group inheritance**: a token's type is its own `$type`, else the
  `$type` of the nearest ancestor group declaring one. Tokens whose resolved type is absent MUST
  be skipped with reason `missing-type` (never guessed from the value shape).
- **Typed `$value` handling** for at least: `color` (legacy string literals `#rrggbb`/`rgb()`
  AND the v2025.10 object form in every colour space the DTCG colour module lists; see the colour
  rules below), `dimension` (`{value, unit}` object serialized as `<value><unit>`; legacy string
  form accepted), `fontFamily` (string or array serialized as a quoted CSS font stack),
  `fontWeight` (number, or v2025.10 weight keyword normalized to its numeric value), and
  **composite `typography`** (each sub-property, `fontFamily`, `fontSize`, `fontWeight`,
  `lineHeight`, mapped individually where a corresponding `--nldesign-*` target exists, unmapped
  sub-properties counted as skipped).
- **Colour objects**: a colour in `srgb` MUST use its `hex` when present, else its components. A
  colour in `srgb-linear` MUST apply the sRGB transfer function to its components before scaling
  to 0..255. A colour in any other colour space MUST be converted to sRGB with the CSS Color 4
  conversions. A converted colour outside the sRGB gamut MUST use the document's `hex` when
  present and be reported `adapted` with reason `out-of-gamut-hex-fallback`, else each channel
  MUST be clipped to 0..1 and reported `adapted` with reason `out-of-gamut-clipped` and the
  original value. An `alpha` below 1 MUST be kept as an 8-digit hex value `#rrggbbaa`. A colour
  space the colour module does not list MUST be skipped with reason `unsupported-color-space`.
- **`$extensions`** MUST be ignored without error and without affecting mapping
  (passthrough-ignore), with one exception: a token whose
  `$extensions["nl.conduction.thematiq"].cssVariable` names a custom property MUST be mapped to
  that name before the suffix table is tried, and the document root's
  `$extensions["nl.conduction.thematiq"].cssOnly` map MUST be read as declarations. Both still pass
  the validator. **`$deprecated`** (boolean `true` or string form) on an imported token MUST
  surface a warning naming the token path (and the string message when given) while still
  importing the value.
- Recognized tokens MUST be mapped to `--nldesign-*` variables via the published mapping table;
  unmapped tokens MUST be skipped and counted with reason `unmapped-path`. The mapped result
  MUST pass through the same whitelist, serialization, and storage pipeline as CSS uploads
  (`CustomTokenSetValidator`; atomic write to `css/tokens/custom-*.css`). DTCG hardening
  changes what the mapper understands, never where or how sets are stored.

#### Scenario: DTCG color tokens map onto the nldesign vocabulary

@e2e exclude mapping logic, PHPUnit on the mapper with DTCG fixtures
- GIVEN a `huisstijl.tokens.json` containing `{ "color": { "primary": { "$type": "color", "$value": "#154273" }, "on-primary": { "$type": "color", "$value": "#ffffff" } } }`
- WHEN the admin uploads it with display name "Eigen huisstijl"
- THEN `css/tokens/custom-eigen-huisstijl.css` MUST contain `--nldesign-color-primary: #154273` and `--nldesign-color-primary-text: #ffffff`
- AND the response MUST report `imported: 2, skipped: 0`

#### Scenario: Group-level $type is inherited by descendant tokens

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a document `{ "color": { "$type": "color", "primary": { "$value": "#154273" } } }`
  where the token itself declares no `$type`
- WHEN the document is imported
- THEN `color.primary` MUST resolve type `color` from its group
- AND MUST be imported as `--nldesign-color-primary: #154273`

#### Scenario: v2025.10 object color and dimension values serialize to CSS

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.primary` token whose `$value` is the object form with an sRGB color space and
  hex fallback for `#154273`, and a `dimension.border-radius` token with
  `$value: { "value": 8, "unit": "px" }`
- WHEN the document is imported
- THEN the emitted declarations MUST be `--nldesign-color-primary: #154273` and
  `--nldesign-border-radius: 8px`
- AND a color token in a colour space the DTCG colour module does not list MUST be skipped with
  reason `unsupported-color-space` and its path listed

#### Scenario: An oklch brand colour is converted to sRGB

@e2e exclude mapping logic, PHPUnit on the mapper and ColorSpaceConverter
- GIVEN an administrator on Settings > Administration > Theming
- AND a `color.primary` token `{ "colorSpace": "oklch", "components": [0.5, 0.1, 250] }`
- WHEN the administrator uploads the document
- THEN `--nldesign-color-primary` MUST be `#32669a`, each channel within 1 of that value
- AND the token MUST count as imported, not skipped

#### Scenario: A display-p3 colour outside sRGB is clipped and reported

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.primary` token `{ "colorSpace": "display-p3", "components": [1, 0, 0] }` with no `hex`
- WHEN the document is imported
- THEN `--nldesign-color-primary` MUST be `#ff0000`
- AND the report MUST list the token as `adapted` with reason `out-of-gamut-clipped` and the original value

#### Scenario: A linear sRGB grey keeps its lightness

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.background` token `{ "colorSpace": "srgb-linear", "components": [0.5, 0.5, 0.5] }` with no `hex`
- WHEN the document is imported
- THEN `--nldesign-color-background` MUST be `#bcbcbc`
- AND it MUST NOT be `#808080`

#### Scenario: A translucent colour keeps its alpha

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.primary-light` token `{ "colorSpace": "srgb", "components": [0.0824, 0.2588, 0.451], "alpha": 0.5 }`
- WHEN the document is imported
- THEN `--nldesign-color-primary-light` MUST be `#15427380`

#### Scenario: The thematiq extension names the target

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a token at `nldesign.color.error` carrying
  `"$extensions": { "nl.conduction.thematiq": { "cssVariable": "--nldesign-color-error" } }`
- WHEN the document is imported
- THEN `--nldesign-color-error` MUST receive the token's value
- AND the token MUST NOT be skipped as `unmapped-path`

#### Scenario: Composite typography token maps sub-values individually

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `typography.font-family`-adjacent composite token of `$type: "typography"` whose
  `$value.fontFamily` is `["Fira Sans", "sans-serif"]`
- WHEN the document is imported
- THEN `--nldesign-font-family` MUST be emitted as the serialized font stack
- AND composite sub-properties without an `--nldesign-*` target MUST be counted as skipped, each
  with its sub-path

#### Scenario: Unmapped DTCG tokens degrade to skipped counts

@e2e exclude mapping tolerance, PHPUnit on the mapper
- GIVEN a DTCG file containing a recognized `color.primary` token and an unrecognized
  `shadow.elevation-1` token
- WHEN the admin uploads it
- THEN the upload MUST succeed with `imported: 1, skipped: 1`
- AND the skipped token path MUST be listed in the response with reason `unmapped-path`

#### Scenario: Deprecated token imports with a surfaced warning

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a mapped token carrying `"$deprecated": "Use color.brand.primary instead"`
- WHEN the document is imported
- THEN the token MUST still be imported
- AND the response `warnings` MUST contain the token path and the deprecation message
- AND the admin panel MUST display the warning after upload

#### Scenario: Malformed JSON is rejected

@e2e exclude parse guard, PHPUnit on the controller
- GIVEN a `.json` upload that is not valid JSON
- WHEN the admin uploads it
- THEN the upload MUST be rejected with HTTP 422 and a localized parse error
- AND no file MUST be written

### Requirement: WCAG AA Contrast Warnings on Upload
The server MUST compute WCAG 2.1 relative-luminance contrast ratios for the fixed token pairs (`--nldesign-color-primary` vs `--nldesign-color-primary-text` at 4.5:1; `--nldesign-color-primary` vs `--nldesign-color-background` at 3:1) from the uploaded values. Failures MUST be returned as non-blocking warnings, persisted in the custom-set manifest entry, and resurfaced in the token-set apply dialog. Pairs with unresolvable (non-literal) values MUST be reported as `unevaluated`, never as passing.

#### Scenario: Low-contrast upload succeeds with a warning
- GIVEN a CSS upload with `--nldesign-color-primary: #cccccc` and `--nldesign-color-primary-text: #ffffff` (ratio ≈ 1.6:1)
- WHEN the admin uploads it
- THEN the upload MUST succeed
- AND the response MUST contain a warning for the pair with the computed ratio and the 4.5:1 AA threshold
- AND the admin panel MUST display the warning with a localized explanation referencing WCAG 2.1 AA

#### Scenario: Contrast warning resurfaces when applying the set
- GIVEN a stored custom set with a persisted contrast warning
- WHEN the admin selects it in the token set dropdown and the apply dialog opens
- THEN the dialog MUST display the persisted contrast warning above the change list
- AND the admin MUST still be able to apply the set (warning is non-blocking)

#### Scenario: Compliant upload produces no warnings
@e2e exclude computation branch — PHPUnit on the contrast service with known-ratio fixtures
- GIVEN a CSS upload with `--nldesign-color-primary: #154273` and `--nldesign-color-primary-text: #ffffff` (ratio ≥ 4.5:1)
- WHEN the admin uploads it
- THEN the response `warnings` array MUST be empty

**See also**: `app-token-set-selection`'s "Selection Contrast Is Non-Blocking"
requirement extends this same warn-only policy to a leaf app's own picker
selecting one of this app's catalogue entries (via `GET /api/token-sets` and
`POST /api/contrast/evaluate`) — selecting an existing catalogue entry is
always warn-only, consistent with the upload-time policy above.

### Requirement: Custom Set Metadata and Theming Bridge
Custom-set metadata (display name, description, `theming.primary_color`, `theming.background_color`) MUST be stored in the `nldesign` appconfig key `custom_token_sets` as a JSON object indexed by set id. `theming.primary_color` and `theming.background_color` MUST be derived from the uploaded `--nldesign-color-primary` / `--nldesign-color-background` values when present so the theming-sync dialog works for custom sets exactly as for shipped sets.

#### Scenario: Uploaded set participates in theming sync
@e2e exclude theme-config scenario; covered by themer integration, no standalone e2e
- GIVEN a custom set uploaded with `--nldesign-color-primary: #007bc7`
- WHEN the admin selects the custom set and the theming sync dialog opens
- THEN the dialog MUST offer to sync `#007bc7` as the Nextcloud primary color
- AND accepting MUST behave identically to a shipped set with the same theming metadata

#### Scenario: Manifest entry without a file is dropped
@e2e exclude discovery edge — PHPUnit on the discovery merge
- GIVEN the appconfig manifest contains `custom-stale` but `css/tokens/custom-stale.css` does not exist
- WHEN token sets are discovered
- THEN `custom-stale` MUST NOT appear in the available token sets
- AND no error MUST be raised

### Requirement: Manage Custom Token Sets
The admin panel MUST list uploaded sets with their contrast status and provide download (export) and delete actions. Export MUST return the exact CSS file that is served (`text/css`, `Content-Disposition: attachment`). Deleting the currently active set MUST reset the active token set to `nextcloud` in the same operation.

#### Scenario: Admin downloads an uploaded set
- GIVEN a stored custom set `custom-gemeente-voorbeeld`
- WHEN the admin clicks its Download action
- THEN the browser MUST download `custom-gemeente-voorbeeld.css`
- AND the content MUST be byte-identical to the served `css/tokens/custom-gemeente-voorbeeld.css`

#### Scenario: Admin deletes an inactive custom set
- GIVEN a stored custom set that is not the active token set
- WHEN the admin clicks Delete and confirms
- THEN the CSS file and its manifest entry MUST be removed
- AND the set MUST disappear from the dropdown and the custom-set list

#### Scenario: Deleting the active set falls back to nextcloud
@e2e exclude theme-config scenario; covered by themer integration, no standalone e2e
- GIVEN `custom-gemeente-voorbeeld` is the active token set
- WHEN the admin deletes it
- THEN the active token set MUST be reset to `nextcloud`
- AND after reload no `custom-gemeente-voorbeeld` CSS MUST be injected on any page

### Requirement: DTCG Alias Resolution

The importer MUST resolve DTCG alias values — a `$value` of the form `{token.path}` referencing
another token in the same document — before mapping and serialization. Resolution MUST follow
transitive chains (an alias pointing at an alias) to the terminal concrete value, applying the
terminal token's resolved `$type`. Resolution MUST detect cycles: a chain that revisits a path
MUST fail that token (never the whole import) with reason `alias-cycle` and the full cycle path
in document order (e.g. `a.b -> c.d -> a.b`). An alias whose target path does not exist MUST
fail that token with reason `alias-target-missing` naming the missing path. Alias chains longer
than a documented bound (at least 10 hops) MUST fail with reason `alias-depth-exceeded`.

#### Scenario: Transitive alias chain resolves to the concrete value

@e2e exclude resolution logic — PHPUnit on the mapper
- GIVEN a document where `color.primary.$value` is `{brand.blue}` and `brand.blue.$value` is
  `{palette.blue-500}` and `palette.blue-500` is a concrete `color` token `#154273`
- WHEN the document is imported
- THEN `--nldesign-color-primary: #154273` MUST be emitted
- AND `imported` MUST count the aliased token as one imported token

#### Scenario: Alias cycle produces an actionable per-token error

@e2e exclude resolution logic — PHPUnit on the mapper
- GIVEN a document where `a.x.$value` is `{b.y}` and `b.y.$value` is `{a.x}` alongside an
  unrelated valid `color.primary` token
- WHEN the document is imported
- THEN the upload MUST still succeed for the valid token
- AND the response `errors` MUST contain an entry with reason `alias-cycle` and the cycle path
  `a.x -> b.y -> a.x`

#### Scenario: Dangling alias is reported with its missing target

@e2e exclude resolution logic — PHPUnit on the mapper
- GIVEN a token whose `$value` is `{does.not.exist}`
- WHEN the document is imported
- THEN that token MUST appear in `errors` with reason `alias-target-missing` and the path
  `does.not.exist`
- AND no declaration MUST be emitted for it

### Requirement: DTCG Import Diagnostics

Every token that is not imported MUST be accounted for in the upload response as a structured
entry `{path, reason, detail?}` — reasons at minimum: `unmapped-path`, `missing-type`,
`unsupported-color-space`, `unsupported-value-shape`, `alias-cycle`, `alias-target-missing`,
`alias-depth-exceeded`. Diagnostics MUST be aggregated (one response listing all issues, not
fail-on-first). The existing numeric `imported`/`skipped` counts MUST remain and MUST be
consistent with the structured lists. The admin panel MUST render the diagnostics grouped by
reason after an upload. Import diagnostics are informational for a partially-mappable document;
the upload only fails outright (HTTP 422) when the document is malformed JSON or yields zero
imported declarations.

#### Scenario: Mixed-quality package yields one aggregated diagnosis

@e2e exclude aggregation logic — PHPUnit on the mapper/controller
- GIVEN a real municipal DTCG package snippet containing mappable colors, unmapped component
  tokens, one dangling alias, and one typeless token
- WHEN the admin uploads it
- THEN the response MUST list every non-imported token path exactly once with its reason
- AND `imported + |structured skip/error entries|` MUST equal the number of token leaves
  processed
- AND the upload MUST succeed because at least one declaration was imported

#### Scenario: Zero-yield import fails actionably

@e2e exclude guard branch — PHPUnit on the controller
- GIVEN a syntactically valid DTCG document none of whose tokens map to an `--nldesign-*` target
- WHEN the admin uploads it
- THEN the upload MUST be rejected with HTTP 422
- AND the body MUST carry the full structured diagnostics so the admin can see why nothing
  mapped

### Requirement: DTCG Package Version Metadata

The importer MUST record a declared package version when an uploaded DTCG document carries
one — checked in order: top-level
`$version` member; `$extensions` version conventions; a top-level non-`$` `version` string —
the importer MUST record it verbatim in the custom set's entry in the `custom_token_sets`
appconfig manifest as `version`, alongside the existing metadata (display name, description,
theming bridge values). Absent a declared version, `version` MUST be omitted (not invented).
The custom-set list endpoint and admin panel MUST expose the recorded version. Recorded
versions are the substrate for upstream-freshness comparison (change `upstream-token-freshness`)
and for compliance-report metadata (change `compliance-evidence-report`); this requirement only
covers recording and display.

#### Scenario: Declared package version is recorded and listed

@e2e exclude metadata persistence — PHPUnit on the service
- GIVEN a DTCG upload whose document carries `"$version": "2.3.1"`
- WHEN the import succeeds as set `custom-eigen-huisstijl`
- THEN the manifest entry for `custom-eigen-huisstijl` MUST contain `version: "2.3.1"`
- AND `GET /settings/tokensets/custom` MUST include `version: "2.3.1"` for that set
- AND the admin panel custom-set list MUST display it

#### Scenario: Version-less package stores no fabricated version

@e2e exclude metadata persistence — PHPUnit on the service
- GIVEN a DTCG upload declaring no version in any recognized location
- WHEN the import succeeds
- THEN the manifest entry MUST NOT contain a `version` key
- AND the list response MUST return `version: null` (or omit the field) for that set

### Requirement: An Uploaded Set Is Selectable Without A Reload
After a successful upload the admin panel MUST bring the token-set dropdown and its in-memory
catalogue in line with the server's selectable list, so the new set can be chosen at once.

#### Scenario: Option appears after upload
- GIVEN the admin uploads a theme named "Midden-Delfland"
- WHEN the upload response is `200`
- THEN the client MUST re-read `GET /settings/tokensets`
- AND the dropdown MUST gain `<option value="custom-midden-delfland">Midden-Delfland</option>` in
  alphabetical position, carrying `data-design-system`
- AND `tokenSetsData['custom-midden-delfland']` MUST hold the catalogue entry (name, theming,
  warnings), so the apply and theming-sync dialogs can read it
- AND the page MUST NOT reload

#### Scenario: Upload does not select or apply
- GIVEN the option was added
- WHEN the upload flow completes
- THEN the dropdown's selection MUST be unchanged and no apply dialog MUST open
- AND the notification MUST tell the admin the set is in the dropdown and to select it to apply it
- AND the dropdown MUST receive focus

#### Scenario: Catalogue refresh fails
- GIVEN `GET /settings/tokensets` fails after a successful upload
- WHEN the client handles it
- THEN the notification MUST fall back to asking for a reload, and the upload MUST still count as
  stored

### Requirement: A Deleted Set Leaves The Dropdown Without A Reload
After a successful delete the admin panel MUST remove the set's option and catalogue entry, and MUST
mirror the server's reset to `nextcloud` on the page when the deleted set was the one on it.

#### Scenario: Deleting the active set
- GIVEN `custom-openwoo` is the active set and is on the page
- WHEN the admin confirms its deletion and the server answers `{status: "ok"}`
- THEN its `<option>` and `tokenSetsData` entry MUST be removed
- AND the page's stylesheet run MUST be swapped to `nextcloud`'s (empty)
- AND the dropdown MUST show `nextcloud`
- AND the page MUST NOT reload

#### Scenario: Deleting an inactive set
- GIVEN `custom-x` exists but is neither selected nor on the page
- WHEN it is deleted
- THEN only its option and entry MUST be removed; the page's stylesheets MUST be untouched

### Requirement: Theme Sources Are Accepted, Not Only Token Sets
The upload MUST accept the four input shapes defined by `token-set-converter` and MUST convert them
to a token set before validation and storage. It MUST NOT require the admin to pre-bake
`--nldesign-*` CSS.

#### Scenario: A design system's built CSS becomes a selectable token set
- GIVEN an admin picks a `design-tokens.css` whose declarations sit in a `.{prefix}-theme` block
- AND enters the name "OpenWOO"
- WHEN the upload is submitted
- THEN the content MUST be converted before validation
- AND the stored `css/tokens/custom-openwoo.css` MUST declare the required semantic tokens
- AND the response MUST confirm the set was added and is selectable

#### Scenario: A file whose content contradicts its extension is handled by content
@e2e exclude Server-side detection by content: PHPUnit CustomTokenSetUploadWritesTest and TokenSetConverterServiceTest
- GIVEN a file named `tokens.json` whose content is CSS
- WHEN the upload is submitted
- THEN the input MUST be detected as CSS from the content
- AND the file name MUST be used only as a provenance hint

### Requirement: Pasted Content Is A First-Class Input
The "Custom token sets" section MUST offer a textarea beside the file picker, and
`CustomTokenSetController::upload()` MUST accept a `content` parameter as an alternative to `file`,
with an optional `sourceName` for the provenance block. Everything after reading the input MUST be
identical for both paths.

#### Scenario: Pasting the contents of a theme file creates the same set as uploading it
- GIVEN an admin pastes the full text of a `design-tokens.css` into the textarea
- AND enters a token set name
- WHEN Convert is submitted
- THEN the resulting stored CSS MUST be identical to uploading the same bytes as a file
- AND the validator MUST have run on the emitted CSS

#### Scenario: An empty paste and an empty file picker are the same error
- GIVEN neither a file nor pasted content is supplied
- WHEN the form is submitted
- THEN the response MUST be a 400 naming that a file or pasted content is required
- AND no set MUST be created

#### Scenario: Pasted content over the size limit is refused
@e2e exclude Size limit enforced server-side: PHPUnit CustomTokenSetControllerTest
- GIVEN pasted content larger than `CustomTokenSetValidator::MAX_SIZE`
- WHEN Convert is submitted
- THEN the response MUST refuse it with the same 512 KB limit the file path enforces

### Requirement: The Conversion Report Is Returned And Rendered
The upload response MUST carry the conversion `report` and its `counts`, and the admin panel MUST
render it grouped by reason using the existing diagnostics grouping, so an admin can see what a theme
asked for that Nextcloud will not do.

#### Scenario: The report groups skipped tokens by reason
- GIVEN a converted theme that declared page width, font sizes and button paddings
- WHEN the upload response is rendered
- THEN the panel MUST show a grouped block per reason code
- AND each group MUST show the human sentence for that code
- AND the applied / adapted / skipped counts MUST be shown

#### Scenario: The new set appears in the dropdown without a page reload
- GIVEN a successful conversion
- WHEN the response is handled
- THEN the new set MUST be appended to the token-set dropdown client-side
- AND selecting it MUST NOT require reloading the settings page

### Requirement: Component-Prefix Tokens Are Accepted In Stored Sets
`CustomTokenSetValidator` MUST accept `--utrecht-*`, `--ams-*` and `--denhaag-*` names beside
`--nldesign-*` and `--{slug}-*`, because the converted file carries a component layer that
`utrecht-bridge.css` and Conduction's own apps read. The value rules MUST NOT be relaxed.

#### Scenario: A component-prefix declaration is stored
@e2e exclude Validator rule: PHPUnit CustomTokenSetValidatorTest::testTheConvertersComponentPrefixesAreAccepted
- GIVEN a converted set declaring `--utrecht-button-border-radius: 3px`
- WHEN it is validated
- THEN the declaration MUST be accepted and stored

#### Scenario: The value gate is unchanged for the widened vocabulary
@e2e exclude Validator rule: PHPUnit CustomTokenSetValidatorTest::testAComponentPrefixDoesNotLoosenTheValueGate
- GIVEN a declaration `--utrecht-button-border-radius: 3px; background: url(x)`
- WHEN it is validated
- THEN it MUST be rejected by `isForbiddenValue()` exactly as a `--nldesign-*` declaration would be

#### Scenario: An external url() never reaches the validator
@e2e exclude Security path: PHPUnit TokenSetConverterServiceTest::testAnExternalUrlIsDroppedBeforeTheValidator
- GIVEN a theme declaring a logo token pointing at a remote host
- WHEN it is converted and validated
- THEN the converter MUST have dropped the declaration with reason `external-url-blocked`
- AND the stored file MUST contain no remote URL
