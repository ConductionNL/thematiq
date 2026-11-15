---
status: done
reviewed_date: 2026-02-28
enriched_date: 2026-03-20
---

# Theming Sync Specification

## Purpose
Defines how the NL Design app synchronizes design token values with Nextcloud's built-in theming system.

When a token set includes theming metadata (primary color, background color, logo, background image), the app can update Nextcloud's `ThemingDefaults` and `ImageManager` to ensure consistency between the NL Design CSS layer and Nextcloud's core theming (which controls background images, server branding, and email templates). This prevents a split-brain state where CSS tokens show one color scheme but Nextcloud's internal theming references another.

## Requirements

### Requirement: Theming Metadata in Token Sets

The system MUST support an optional `theming` object in a token set manifest that defines values
suitable for synchronization with Nextcloud's built-in theming system.

#### Scenario: Token set with full theming metadata

- GIVEN the `token-sets.json` entry for `rijkshuisstijl` has a `theming` object
- WHEN the metadata is read
- THEN the `theming` object MUST contain `primary_color` (hex string, e.g. `"#154273"`)
- AND it MUST contain `background_color` (hex string, e.g. `"#F5F6F7"`)
- AND it MAY contain `logo` (relative path, e.g. `"img/logos/rijkshuisstijl.svg"`)
- AND it MAY contain `logo_dark` (relative path to a dark-surface logo variant, e.g.
  `"img/logos/rijkshuisstijl-dark.svg"`)

#### Scenario: Token set with logo and background theming

- GIVEN a token set entry has `theming.logo` and `theming.background` fields
- WHEN the metadata is read
- THEN `logo` MUST be a relative path within `img/logos/`
- AND `background` MUST be a relative path within `img/backgrounds/`
- AND both paths MUST reference files that exist in the nldesign app directory

#### Scenario: Dark logo path validated like the light logo

- GIVEN a token set entry has a `theming.logo_dark` field
- WHEN the metadata is validated (manifest audit or sync request)
- THEN `logo_dark` MUST satisfy the same rules as `logo`: no path traversal, path within
  `img/logos/`, file exists in the app directory
- AND a `logo_dark` value violating any rule MUST be rejected with the same error shapes
  REQ-SYNC-004 defines for `logo`

#### Scenario: Dark logo is not synced to Nextcloud core theming

- GIVEN a token set with `theming.logo_dark`
- WHEN theming sync is applied
- THEN `logo_dark` MUST NOT be passed to `ImageManager::updateImage()` (Nextcloud core has a
  single logo slot — a dark slot is the open upstream request nextcloud/server#47357)
- AND the dark logo MUST instead be delivered by nldesign's generated dark variant stylesheet
  (see the `dark-mode` spec)

#### Scenario: Token set without theming metadata

- GIVEN a token set entry in `token-sets.json` has no `theming` key
- WHEN the token set is retrieved via the API
- THEN the `theming` field MUST be absent from the response
- AND theming sync MUST NOT be offered for this token set in the admin UI

#### Scenario: Theming metadata included in API response

- GIVEN a token set with theming metadata is retrieved via `GET /settings/tokensets`
- WHEN the response is generated
- THEN the token set object MUST include the `theming` object with all its fields, including
  `logo_dark` when present
- AND the frontend can use this data to display the theming sync dialog

#### Scenario: Partial theming metadata accepted

- GIVEN a token set has `theming: {"primary_color": "#004699"}` with no background_color, logo,
  or logo_dark
- WHEN the metadata is read
- THEN only the `primary_color` MUST be available for syncing
- AND missing fields MUST NOT cause errors

### Requirement: Get Current Theming Values
The app MUST provide an API endpoint to retrieve current Nextcloud theming values for comparison with token set metadata.

#### Scenario: Retrieve theming values
- GIVEN the admin is authenticated
- WHEN `GET /apps/nldesign/settings/theming` is called
- THEN the response MUST be JSON with fields: `primary_color` (string), `background_color` (string), `logo_url` (string), `background_url` (string), `has_custom_logo` (boolean), `has_custom_background` (boolean)

#### Scenario: No custom theming configured
- GIVEN no custom theming has been applied in Nextcloud
- WHEN `GET /apps/nldesign/settings/theming` is called
- THEN `primary_color` MUST be an empty string (from `IConfig::getAppValue('theming', 'primary_color', '')`)
- AND `background_color` MUST be an empty string
- AND `has_custom_logo` MUST be `false` (from `ImageManager::hasImage('logo')`)
- AND `has_custom_background` MUST be `false`

#### Scenario: Custom theming previously configured
- GIVEN the admin has set primary color to "#004699" via Nextcloud theming
- AND a custom logo has been uploaded
- WHEN `GET /apps/nldesign/settings/theming` is called
- THEN `primary_color` MUST be `"#004699"`
- AND `has_custom_logo` MUST be `true`
- AND `logo_url` MUST return the URL from `ImageManager::getImageUrl('logo')`

#### Scenario: Values built from buildThemingSnapshot
- GIVEN the `getThemingValues()` method is called
- WHEN the snapshot is built
- THEN `buildThemingSnapshot()` MUST read `primary_color` and `background_color` from `IConfig::getAppValue('theming', ...)`
- AND it MUST read logo and background image state from `ThemingService::getImageManager()`

### Requirement: Color Validation
All color values submitted to the theming sync API MUST be validated as valid hex color strings before being applied.

#### Scenario: Valid 6-digit hex color accepted
- GIVEN a request with `primary_color: "#154273"`
- WHEN `validateColors()` processes the parameter
- THEN validation MUST pass (return `null`)

#### Scenario: Valid 3-digit hex color accepted
- GIVEN a request with `primary_color: "#abc"`
- WHEN `validateColors()` processes the parameter
- THEN validation MUST pass (return `null`)

#### Scenario: Invalid color rejected with descriptive error
- GIVEN a request with `primary_color: "not-a-color"`
- WHEN `validateColors()` processes the parameter
- THEN validation MUST fail
- AND the return value MUST be the string `"Invalid hex color for primary_color: not-a-color"`

#### Scenario: Empty color field skipped
- GIVEN a request with `primary_color: ""`
- WHEN `validateColors()` processes the parameter
- THEN the empty field MUST be skipped (not validated, not applied)
- AND validation MUST return `null` (success)

#### Scenario: Both color fields validated
- GIVEN any request to `POST /apps/nldesign/settings/theming`
- WHEN colors are validated
- THEN the system MUST check both `primary_color` and `background_color` parameters
- AND the hex regex MUST be `/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/`
- AND validation MUST iterate over both fields, returning the first error found

### Requirement: Image Path Validation
All image paths submitted to the theming sync API MUST be validated against path traversal attacks, allowed directories, and file existence.

#### Scenario: Valid logo path accepted
- GIVEN a request with `logo: "img/logos/amsterdam.svg"`
- AND the file exists at `{appPath}/img/logos/amsterdam.svg`
- WHEN `validateImagePaths()` processes the parameter
- THEN validation MUST pass (return `null`)

#### Scenario: Path traversal via dot-dot prevented
- GIVEN a request with `logo: "../../etc/passwd"`
- WHEN `validateSinglePath()` processes the parameter
- THEN validation MUST fail with error `"Invalid image path for logo: path traversal not allowed"`
- AND the check MUST use `str_contains($imagePath, '..')`

#### Scenario: Absolute path rejected
- GIVEN a request with `logo: "/etc/passwd"`
- WHEN `validateSinglePath()` processes the parameter
- THEN validation MUST fail with error `"Invalid image path for logo: path traversal not allowed"`
- AND the check MUST use `str_starts_with($imagePath, '/')`

#### Scenario: Path outside allowed directories rejected
- GIVEN a request with `logo: "lib/Controller/SettingsController.php"`
- WHEN `validateSinglePath()` processes the parameter
- THEN validation MUST fail with error `"Invalid image path for logo: must be in img/logos/ or img/backgrounds/"`

#### Scenario: Non-existent image rejected
- GIVEN a request with `logo: "img/logos/nonexistent.svg"`
- AND the file does not exist on the filesystem
- WHEN `validateSinglePath()` processes the parameter
- THEN validation MUST fail with error `"Image file not found: img/logos/nonexistent.svg"`

#### Scenario: Both image fields validated
- GIVEN any request to `POST /apps/nldesign/settings/theming`
- WHEN images are validated
- THEN the system MUST check both `logo` and `background` parameters
- AND paths MUST start with either `img/logos/` or `img/backgrounds/`
- AND validation MUST return the first error found

### Requirement: Apply Colors to Nextcloud Theming
The app MUST apply validated color values to Nextcloud's `ThemingDefaults` service, and when it
applied a `background_color` MUST also set `backgroundMime` to `backgroundColor` unless the same
request carries a `background` image (whose mime `applyImages()` writes afterwards).

#### Scenario: Primary color applied
- GIVEN a valid request with `primary_color: "#004699"`
- WHEN `applyColors()` is called
- THEN `ThemingDefaults::set('primary_color', '#004699')` MUST be called
- AND `"primary_color"` MUST appear in the list of updated fields

#### Scenario: Background color applied
- GIVEN a valid request with `background_color: "#FFFFFF"` and no `background`
- WHEN `applyColors()` is called
- THEN `ThemingDefaults::set('background_color', '#FFFFFF')` MUST be called
- AND `ThemingDefaults::set('backgroundMime', 'backgroundColor')` MUST be called
- AND `"background_color"` MUST appear in the list of updated fields

#### Scenario: Background color with a background image in the same request
- GIVEN a valid request with `background_color: "#FFFFFF"` and `background: "img/backgrounds/x.jpg"`
- WHEN `applyColors()` is called
- THEN `backgroundMime` MUST NOT be set to `backgroundColor` by `applyColors()`
- AND `applyImages()` MUST set it to the image's detected mime type

#### Scenario: Multiple colors applied simultaneously
- GIVEN a valid request with both `primary_color: "#004699"` and `background_color: "#FFFFFF"`
- WHEN `applyColors()` is called
- THEN both colors MUST be applied via `ThemingDefaults::set()`
- AND both keys MUST appear in the updated list

#### Scenario: Empty color ignored
- GIVEN a request where `primary_color` is empty or not set
- WHEN `applyColors()` is called
- THEN `ThemingDefaults::set()` MUST NOT be called for `primary_color`
- AND `"primary_color"` MUST NOT appear in the updated list

### Requirement: Apply Images to Nextcloud Theming
The app MUST apply validated image paths to Nextcloud's `ImageManager` service using full
filesystem paths, and MUST persist the mime type `ImageManager::updateImage()` returns, because
Nextcloud reads the image through the `{key}Mime` app value rather than through the file's presence.

#### Scenario: Logo image applied
@e2e exclude Service call contract: PHPUnit ThemingServiceTest
- GIVEN a valid request with `logo: "img/logos/amsterdam.svg"`
- AND the file exists at `{appPath}/img/logos/amsterdam.svg`
- WHEN `applyImages()` is called
- THEN `ImageManager::updateImage('logo', '{appPath}/img/logos/amsterdam.svg')` MUST be called with
  the full absolute path
- AND `ThemingDefaults::set('logoMime', …)` MUST be called with the mime type that call returned
- AND `"logo"` MUST appear in the list of updated fields

#### Scenario: Background image applied
@e2e exclude Service call contract: PHPUnit ThemingServiceTest
- GIVEN a valid request with `background: "img/backgrounds/default.jpg"`
- AND the file exists
- WHEN `applyImages()` is called
- THEN `ImageManager::updateImage('background', '{fullPath}')` MUST be called
- AND `ThemingDefaults::set('backgroundMime', …)` MUST be called with the returned mime type
- AND `"background"` MUST appear in the list of updated fields

#### Scenario: The synced logo is the one Nextcloud serves
@e2e exclude Covered by tests/e2e/spec-coverage/theming-sync.spec.ts (logo_url points at /apps/theming/image/logo) and PHPUnit SetLogoReachTest
- GIVEN a token set whose `theming.logo` names a file in the app's `img/logos/`
- WHEN the theming sync is confirmed
- THEN `GET /apps/theming/image/logo` MUST return that file's bytes and its mime type
- AND the generated theming stylesheet's `--image-logo` MUST point at that route, not at
  `core/img/logo/logo.png`

#### Scenario: Empty image path ignored
@e2e exclude Service call contract: PHPUnit ThemingServiceTest
- GIVEN a request where `logo` is empty or not set
- WHEN `applyImages()` is called
- THEN `ImageManager::updateImage()` MUST NOT be called for `logo`
- AND no `{key}Mime` app value MUST be written

#### Scenario: App path resolved via IAppManager
@e2e exclude Service call contract: PHPUnit ThemingServiceTest
- GIVEN images need to be applied
- WHEN the full path is constructed
- THEN `IAppManager::getAppPath('thematiq')` MUST be used to resolve the base directory
- AND the relative path MUST be appended to get the full filesystem path

### Requirement: Update Theming API Endpoint
The app MUST provide an admin-only API endpoint that validates and applies theming changes in a defined order.

#### Scenario: Successful theming update
- GIVEN the admin is authenticated
- AND a valid request with `primary_color: "#154273"` and `logo: "img/logos/rijkshuisstijl.svg"`
- WHEN `POST /apps/nldesign/settings/theming` is called
- THEN color validation MUST run first
- AND image path validation MUST run second
- AND if both pass, colors MUST be applied
- AND images MUST be applied
- AND the response MUST be JSON with `{"status": "ok", "updated": ["primary_color", "logo"]}`

#### Scenario: Color validation failure stops all processing
- GIVEN a request with `primary_color: "invalid"` and `logo: "img/logos/valid.svg"`
- WHEN `POST /apps/nldesign/settings/theming` is called
- THEN color validation MUST fail first
- AND the response MUST be HTTP 400 with `{"error": "Invalid hex color for primary_color: invalid"}`
- AND no colors or images MUST be applied

#### Scenario: Image validation failure stops image processing
- GIVEN a request with `primary_color: "#154273"` and `logo: "../../etc/passwd"`
- WHEN validation runs
- THEN color validation MUST pass
- AND image validation MUST fail
- AND the response MUST be HTTP 400 with the image error message
- AND no changes MUST be applied (neither colors nor images)

#### Scenario: Non-admin access denied
- GIVEN a non-admin user is authenticated
- WHEN `POST /apps/nldesign/settings/theming` is called
- THEN the request MUST be rejected by the `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)` annotation

#### Scenario: Empty request applies nothing
- GIVEN a request with no parameters
- WHEN `POST /apps/nldesign/settings/theming` is called
- THEN validation MUST pass (no fields to validate)
- AND the response MUST be `{"status": "ok", "updated": []}`

### Requirement: Theming Dependencies
The theming sync feature MUST depend on the Nextcloud `theming` app for `ThemingDefaults` and `ImageManager`, injected via constructor.

#### Scenario: ThemingService dependencies injected
- GIVEN the nldesign app is loaded
- WHEN `ThemingService` is constructed
- THEN it MUST receive `ImageManager`, `ThemingDefaults`, and `IAppManager` via constructor injection
- AND it MUST NOT instantiate these dependencies directly

@e2e exclude Constructor wiring with no HTTP surface. tests/Unit/Service/ThemingServiceTest.php::setUp builds ThemingService from three injected doubles (ImageManager, ThemingDefaults, IAppManager), and every test in that file runs through them.

#### Scenario: ImageManager accessible via getter
- GIVEN the `ThemingService` is constructed
- WHEN `getImageManager()` is called
- THEN it MUST return the injected `ImageManager` instance
- AND the `SettingsController` can use this to build theming snapshots

@e2e exclude A PHP getter with no HTTP surface of its own. tests/Unit/Service/ThemingServiceTest.php::testGetImageManagerReturnsTheInjectedInstance asserts it returns the injected instance; the snapshot it feeds is browser-tested under values-built-from-buildthemingsnapshot.

#### Scenario: Theming app must be enabled
- GIVEN the theming app is not enabled in Nextcloud
- WHEN the `ThemingService` dependencies are resolved
- THEN Nextcloud's DI container MUST handle the missing dependency
- AND the nldesign app SHOULD declare `theming` as a dependency in `info.xml`

@e2e exclude The GIVEN cannot be produced on any instance: Nextcloud's core/shipped.json lists theming under alwaysEnabled, so the server refuses to disable it.

### Requirement: Validation Order
The theming sync endpoint MUST validate all inputs before applying any changes, ensuring atomicity of the validation phase.

#### Scenario: Colors validated before images
- GIVEN a request with both color and image parameters
- WHEN `updateThemingValues()` processes the request
- THEN `validateColors()` MUST be called first
- AND only if it returns `null` (success) MUST `validateImagePaths()` be called
- AND only if both return `null` MUST `applyColors()` and `applyImages()` be called

#### Scenario: Failed validation prevents all changes
- GIVEN color validation fails
- WHEN the error response is returned
- THEN no IConfig values MUST be modified
- AND no ImageManager updates MUST be triggered
- AND the Nextcloud theming state MUST remain unchanged

#### Scenario: Params read from request
- GIVEN the `updateThemingValues()` method is called
- WHEN request parameters are read
- THEN `$this->request->getParams()` MUST be used to get all parameters
- AND these params MUST be passed to both validation and apply methods

### Requirement: Theming Sync Dialog (Frontend)

The admin JavaScript MUST show a confirmation dialog when switching to a token set that has
theming metadata, allowing the admin to review and approve theming changes.

#### Scenario: Dialog shown for token set with theming metadata

- GIVEN the admin selects a token set that has a `theming` object
- WHEN the token set selection is saved
- THEN the JavaScript MUST call `checkAndShowThemingDialog()`
- AND a modal dialog MUST appear showing the proposed theming changes

#### Scenario: Dialog shows color comparison

- GIVEN the theming dialog opens
- WHEN the current theming values differ from the token set's proposed values
- THEN the dialog MUST show current vs proposed colors with visual swatches
- AND the admin MUST be able to see the difference before confirming

#### Scenario: Dialog offers the dark logo when present

- GIVEN the selected token set's `theming` object contains `logo_dark`
- WHEN the theming dialog opens
- THEN the dialog MUST render a dark-logo preview row (the dark logo shown on a dark swatch
  background)
- AND the row MUST carry an explanatory note (i18n key in English) that the dark logo is applied
  by nldesign's dark stylesheet because Nextcloud core has no dark logo slot
- AND confirming the dialog MUST NOT add a `logo_dark` field to the
  `POST /settings/theming` request

#### Scenario: Dialog omits the dark logo row when absent

- GIVEN the selected token set's `theming` object has no `logo_dark`
- WHEN the theming dialog opens
- THEN no dark-logo row MUST be rendered

#### Scenario: Dialog not shown for sets without theming metadata

- GIVEN the admin selects a token set without a `theming` object
- WHEN the token set selection is saved
- THEN no theming sync dialog MUST be shown
- AND the token set MUST be applied without further prompts

#### Scenario: Admin confirms theming sync

- GIVEN the theming dialog is shown
- WHEN the admin clicks the confirm/apply button
- THEN `POST /apps/nldesign/settings/theming` MUST be called with the proposed values
- AND on success, Nextcloud's theming MUST be updated

#### Scenario: Admin cancels theming sync

- GIVEN the theming dialog is shown
- WHEN the admin clicks cancel
- THEN the dialog MUST close
- AND no theming changes MUST be applied
- AND the token set selection MUST still take effect (CSS tokens change, but Nextcloud core
  theming remains unchanged)

### Requirement: Route Configuration
The theming sync endpoints MUST be registered in the app's route configuration.

#### Scenario: GET theming route
- GIVEN the routes configuration
- THEN `GET /settings/theming` MUST be mapped to `settings#getThemingValues`

#### Scenario: POST theming route
- GIVEN the routes configuration
- THEN `POST /settings/theming` MUST be mapped to `settings#updateThemingValues`

#### Scenario: Both routes admin-only
- GIVEN both theming routes
- THEN both corresponding controller methods MUST have `@AuthorizedAdminSetting` annotations

### Requirement: Translucent colours are blended before they reach Nextcloud core

When a set's primary or background colour has an alpha below 1, the theming values sent to
Nextcloud core MUST be that colour blended over the set's background colour (white when the set
declares none), as 6-digit hex. The theming sync dialog MUST show the original value and the
blended value, with the note "Nextcloud's own theming has no transparency. It gets this colour
instead." The existing color validation for the sync request MUST stay as it is.

#### Scenario: A translucent primary colour is synced as its blend
@e2e exclude Not yet browser-tested; this scenario sat under the spec-wide exclusion this branch retires. The blend is proven by tests/Unit/TokenValueTypesTest.php::testTranslucentPrimaryIsBlendedForCore (`#15427380` over `#ffffff` gives `primary_color` `#8aa0b9` and keeps `primary_color_original`) and the dialog note by tests/vitest/admin-token-editor.spec.js "says why Nextcloud gets the blend of a translucent colour".
- GIVEN an administrator on Settings > Administration > Theming
- AND a custom set whose `--nldesign-color-primary` is `#15427380` and background `#ffffff`
- WHEN the administrator applies the set and confirms the theming sync
- THEN Nextcloud core theming MUST receive the primary colour `#8aa0b9`
- AND the dialog MUST have shown `#15427380` next to `#8aa0b9` with the note

#### Scenario: An opaque colour is synced unchanged
@e2e exclude Blend arithmetic, covered by PHPUnit on CustomTokenSetService
- GIVEN a set whose primary colour is `#154273`
- WHEN its theming values are derived
- THEN `primary_color` MUST be `#154273`

### Requirement: Stock Nextcloud Resets Core Theming
Selecting the stock set (design system `none`) MUST offer to reset Nextcloud theming to its
defaults, not to "match" the stock set's manifest values. The app MUST expose the reset as `POST /settings/theming` with `reset=1` (admin-only, the same
endpoint and therefore the same route entry — a new path or verb 404s/405s on any instance whose
route collection is still cached, which is an hour by default and was measured turning a
successful switch into a "failed to apply" toast). It calls `ThemingDefaults::undo()` for
`primary_color`, `background_color`, `logo` and `background` — the same call core's own undo
arrows make, which deletes the value and, for an image slot, the stored image and its `{key}Mime`
— and logs `theming_sync_reset`. The `GET /settings/theming` snapshot MUST carry
`default_primary_color` and `default_background_color` (core's `BackgroundService` constants, not
`getDefaultColorPrimary()`, which returns the admin-configured value a reset removes) so the dialog
can show what "default" is before applying it.

#### Scenario: Reset undoes a synced logo
- GIVEN OpenWOO was synced: `primary_color=#23845c`, `logoMime=image/svg+xml`, a stored logo
- WHEN `POST /settings/theming` is called with `reset=1`
- THEN `primary_color`, `background_color`, `logoMime`, `backgroundMime` MUST be unset
- AND `ImageManager::hasImage('logo')` MUST be false
- AND `/apps/theming/theme/default.css` MUST carry `--color-primary:#00679e`
- AND the response MUST be `{status: "ok", reset: ["primary_color","background_color","logo","background"]}`

#### Scenario: Reset is idempotent
- GIVEN nothing is configured in core theming
- WHEN `POST /settings/theming` is called with `reset=1`
- THEN the response MUST still be `ok` and nothing MUST fail

## Current Implementation Status

**Fully implemented:**
- Theming metadata in token sets: `TokenSetService::getAvailableTokenSets()` includes the `theming` object from `token-sets.json` entries when present (`lib/Service/TokenSetService.php` lines 85-87)
- GET theming values: `GET /apps/nldesign/settings/theming` endpoint in `SettingsController::getThemingValues()` via `buildThemingSnapshot()` returns all required fields (`lib/Controller/SettingsController.php` lines 275-299)
- Color validation: `ThemingService::validateColors()` checks `primary_color` and `background_color` against regex `/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/`, skips empty values (`lib/Service/ThemingService.php` lines 87-98)
- Image path validation: `ThemingService::validateImagePaths()` and `validateSinglePath()` check for path traversal, enforce allowed directory prefixes, verify file existence (`lib/Service/ThemingService.php` lines 107-153)
- Apply colors: `ThemingService::applyColors()` calls `ThemingDefaults::set()` for each non-empty color (lines 162-174)
- Apply images: `ThemingService::applyImages()` calls `ImageManager::updateImage()` with full path resolved via `IAppManager::getAppPath()` (lines 183-197)
- POST theming endpoint: `SettingsController::updateThemingValues()` validates colors first, then images, then applies (lines 247-266)
- ThemingService constructor injection of `ImageManager`, `ThemingDefaults`, `IAppManager` (lines 58-66)
- `getImageManager()` getter for snapshot building (lines 204-207)
- Admin-only access: `@AuthorizedAdminSetting` annotation on all theming endpoints
- Frontend theming sync dialog: `js/admin.js` with `checkAndShowThemingDialog()` and `showThemingDialog()` functions
- Routes: `appinfo/routes.php` lines 16-17

**Not yet implemented:**
- All requirements in this spec are fully implemented.
- Note: The implementation does not wrap `ThemingDefaults::set()` or `ImageManager::updateImage()` in try/catch -- if these throw, the endpoint will return a 500 error.

## Standards & References
- Nextcloud Theming API: `OCA\Theming\ThemingDefaults::set()` and `OCA\Theming\ImageManager::updateImage()` are internal Nextcloud APIs
- OWASP Path Traversal Prevention: validated by checking for `..` and `/` prefix, enforcing allowed directories
- Hex color validation: Standard CSS hex color format (3 or 6 digit)
- NL Design System: Token set theming metadata bridges design tokens to Nextcloud's server-level branding (logos, background images, email templates)
