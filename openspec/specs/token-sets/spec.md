---
status: done
reviewed_date: 2026-02-28
enriched_date: 2026-03-20
---

# Token Sets Specification

## Purpose
Defines how the NL Design app discovers, validates, stores, and serves design token sets.

@e2e exclude Backend/filesystem/API spec — scenarios cover TokenSetService PHP logic, manifest parsing, IConfig storage, path-traversal checks, and route configuration; the admin dropdown UI surface is covered by admin-settings tests. Token sets are organization-specific CSS files that override default Rijkshuisstijl design tokens, enabling Dutch government organizations to apply their own visual identity to Nextcloud. The system uses filesystem-based discovery combined with a JSON manifest for metadata, and supports multiple design systems via a `design_system` field that determines which CSS stack is loaded.
## Requirements
### Requirement: Filesystem-Based Discovery
The app MUST discover available token sets by scanning the `css/tokens/` directory for CSS files and merging metadata from `token-sets.json` for shipped sets and from the `custom_token_sets` appconfig manifest for uploaded sets (files matching `custom-*.css`).

#### Scenario: Token sets discovered from filesystem
- GIVEN the nldesign app is installed
- AND the `css/tokens/` directory contains CSS files (e.g. `rijkshuisstijl.css`, `amsterdam.css`)
- WHEN `TokenSetService::getAvailableTokenSets()` is called
- THEN each `.css` file in `css/tokens/` MUST produce a token set entry
- AND each entry MUST have an `id` derived from the filename without extension (via `basename($file, '.css')`)
- AND each entry MUST have a `name`, `description`, and `design_system` field

#### Scenario: Metadata merged from manifest
- GIVEN `token-sets.json` exists and contains an entry with `id: "amsterdam"`
- AND `css/tokens/amsterdam.css` exists on the filesystem
- WHEN the available token sets are retrieved
- THEN the entry for `amsterdam` MUST use the `name` from the manifest ("Gemeente Amsterdam")
- AND the entry MUST use the `description` from the manifest
- AND the entry MUST use the `design_system` from the manifest (default: "nldesign")
- AND if the manifest entry has a `theming` object, it MUST be included in the response

#### Scenario: Custom set metadata merged from appconfig manifest
@e2e exclude discovery merge — PHPUnit on TokenSetService
- GIVEN `css/tokens/custom-gemeente-voorbeeld.css` exists
- AND the `custom_token_sets` appconfig manifest contains an entry for `custom-gemeente-voorbeeld` with name "Gemeente Voorbeeld" and a `theming` object
- WHEN the available token sets are retrieved
- THEN the entry MUST use the name, description, and theming metadata from the appconfig manifest
- AND the entry MUST be marked as custom (`custom: true`) so the dropdown can label it

#### Scenario: CSS file exists without manifest entry
- GIVEN a file `css/tokens/custom-org.css` exists
- AND neither `token-sets.json` nor the `custom_token_sets` appconfig manifest contains an entry with `id: "custom-org"`
- WHEN the available token sets are retrieved
- THEN the entry MUST still be returned
- AND the `name` MUST be auto-generated from the id using `ucwords(str_replace('-', ' ', $id))` (e.g. "Custom Org")
- AND the `description` MUST default to "Design tokens for Custom Org"
- AND the `design_system` MUST default to "nldesign"

#### Scenario: Manifest entry exists without CSS file
- GIVEN `token-sets.json` or the `custom_token_sets` appconfig manifest contains an entry with `id: "phantom-org"`
- AND `css/tokens/phantom-org.css` does NOT exist
- WHEN the available token sets are retrieved
- THEN the `phantom-org` entry MUST NOT appear in the results
- AND the filesystem MUST be the source of truth for available sets

#### Scenario: Shipped manifest wins on impossible id collision
@e2e exclude defensive branch — PHPUnit on the merge order
- GIVEN an entry with the same id exists in both `token-sets.json` and the `custom_token_sets` appconfig manifest (should be impossible given the `custom-` namespace, but defensively)
- WHEN the available token sets are retrieved
- THEN the shipped `token-sets.json` metadata MUST take precedence
- AND the collision MUST be logged at warning level

#### Scenario: Token sets sorted alphabetically
- GIVEN multiple token sets are discovered, shipped and custom
- WHEN the list is returned
- THEN the token sets MUST be sorted alphabetically by `name` (case-insensitive via `strcasecmp`) across both groups

### Requirement: Token Set Manifest Structure

The `token-sets.json` manifest MUST follow a defined schema for each entry.

#### Scenario: Manifest entry with full metadata

- GIVEN a manifest entry for an organization
- WHEN the entry is valid
- THEN it MUST have an `id` field (string, kebab-case identifier matching the CSS filename)
- AND it MUST have a `name` field (string, human-readable display name)
- AND it MUST have a `description` field (string)
- AND it MUST have a `design_system` field (string, referencing an id in `design-systems.json`)
- AND it MAY have a `theming` object with optional keys: `primary_color` (hex),
  `background_color` (hex), `logo` (relative path), `background` (relative path), and
  `logo_dark` (relative path to a dark-surface logo variant within `img/logos/`)

#### Scenario: Dark logo metadata passed through

- GIVEN a manifest entry whose `theming` object contains
  `logo_dark: "img/logos/rijkshuisstijl-dark.svg"`
- WHEN the token sets are retrieved via `GET /settings/tokensets`
- THEN the entry's `theming` object in the response MUST include the `logo_dark` key unchanged
- AND sets without `logo_dark` MUST simply omit the key (no null placeholder)

#### Scenario: Dark logo consumed by the generated dark variant

- GIVEN a token set with `theming.logo_dark` set and an existing dark logo file
- WHEN the set's dark variant is generated (see the `dark-mode` spec)
- THEN the generated `css/tokens/dark/<set>.css` MUST override `--nldesign-logo-url` with the
  dark logo path inside its dark-scoped blocks
- AND the light layer's `--nldesign-logo-url` MUST remain untouched

#### Scenario: Token set file may carry a hand-authored dark block

- GIVEN a token set CSS file containing a top-level
  `@media (prefers-color-scheme: dark) { :root { … } }` block
- WHEN the file is loaded as Layer 3
- THEN the block's presence MUST NOT affect light rendering (Layer 3 light declarations still
  live on plain `:root`)
- AND the block MUST be recognised by dark-variant generation as the hand-authored override
  source (see the `dark-mode` spec's override requirement)

#### Scenario: Manifest is malformed JSON

- GIVEN `token-sets.json` contains invalid JSON
- WHEN `readManifest()` is called
- THEN it MUST return an empty array
- AND the system MUST still discover token sets from the filesystem (without metadata)
- AND the app MUST NOT throw an exception or display an error

#### Scenario: Manifest is missing

- GIVEN `token-sets.json` does not exist
- WHEN `readManifest()` is called
- THEN it MUST return an empty array
- AND the system MUST still discover token sets with auto-generated names and default design_system

#### Scenario: Manifest file unreadable

- GIVEN `token-sets.json` exists but `file_get_contents()` returns `false`
- WHEN `readManifest()` is called
- THEN it MUST return an empty array
- AND the system MUST continue with auto-generated metadata

#### Scenario: Manifest indexed by id

- GIVEN the manifest contains multiple entries
- WHEN `readManifest()` processes them
- THEN entries MUST be indexed by their `id` field
- AND entries without an `id` field MUST be skipped

### Requirement: Active Token Set Storage
The active token set MUST be stored in Nextcloud's `IConfig` and default to `nextcloud`.

#### Scenario: No token set configured (fresh install)
- GIVEN no value has been set for `nldesign:token_set` in IConfig
- WHEN the active token set is queried
- THEN the default value MUST be `'nextcloud'` (stock Nextcloud theming)

#### Scenario: Token set persisted via API
- GIVEN an admin selects the `utrecht` token set
- WHEN `POST /settings/tokenset` is called with `tokenSet=utrecht`
- THEN `IConfig::setAppValue('nldesign', 'token_set', 'utrecht')` MUST be called
- AND the response MUST be JSON with `{"status": "ok", "tokenSet": "utrecht"}`

#### Scenario: Token set retrieved via API
- GIVEN the active token set is `amsterdam`
- WHEN `GET /settings/tokenset` is called
- THEN the response MUST be JSON with `{"tokenSet": "amsterdam"}`

#### Scenario: Token set read during boot
- GIVEN the active token set is stored as `amsterdam` in IConfig
- WHEN `Application::injectThemeCSS()` reads the config
- THEN `$config->getAppValue('nldesign', 'token_set', 'nextcloud')` MUST return `'amsterdam'`
- AND `tokens/amsterdam` MUST be loaded as Layer 3 in the CSS stack

### Requirement: Token Set Validation
The app MUST validate that a token set is valid (exists on filesystem, no path traversal) before accepting it as the active set.

#### Scenario: Valid token set selected
- GIVEN `css/tokens/utrecht.css` exists on the filesystem
- WHEN `setTokenSet("utrecht")` is called
- THEN `isValidTokenSet("utrecht")` MUST return `true`
- AND the token set MUST be stored in IConfig

#### Scenario: Invalid token set rejected
- GIVEN `css/tokens/nonexistent.css` does NOT exist
- WHEN `setTokenSet("nonexistent")` is called
- THEN `isValidTokenSet("nonexistent")` MUST return `false`
- AND the API MUST return HTTP 400 with `{"error": "Invalid token set"}`
- AND IConfig MUST NOT be updated

#### Scenario: Path traversal with forward slash prevented
- GIVEN a malicious token set id containing `/` (e.g., "../../etc/passwd")
- WHEN `isValidTokenSet()` is called
- THEN `str_contains($tokenSetId, '/')` MUST return `true`
- AND the method MUST return `false` immediately without filesystem access

#### Scenario: Path traversal with dot-dot prevented
- GIVEN a malicious token set id containing `..` (e.g., "..%2F..%2Fetc%2Fpasswd")
- WHEN `isValidTokenSet()` is called
- THEN `str_contains($tokenSetId, '..')` MUST return `true`
- AND the method MUST return `false` immediately

#### Scenario: Validation checks actual file existence
- GIVEN the id passes path traversal checks
- WHEN `isValidTokenSet()` continues
- THEN it MUST construct the path `{appPath}/css/tokens/{tokenSetId}.css`
- AND it MUST verify the file exists via `file_exists()`

### Requirement: Token Set CSS Structure
Each token set CSS file MUST define organization-specific `--nldesign-*` variables on `:root`.

#### Scenario: Complete token set
- GIVEN a token set like `rijkshuisstijl.css`
- WHEN loaded after `defaults.css`
- THEN it MUST override `--nldesign-color-primary` with the organization's primary color
- AND it MUST override `--nldesign-color-primary-text` for accessible text on the primary color
- AND it MAY override any other `--nldesign-*` variable defined in `defaults.css`

#### Scenario: Incomplete token set (partial overrides)
- GIVEN a token set that only defines `--nldesign-color-primary` and `--nldesign-color-primary-text`
- WHEN loaded after `defaults.css`
- THEN all undefined tokens MUST fall back to the Rijkshuisstijl defaults from `defaults.css`
- AND the application MUST render correctly with the partial overrides
- AND no visual errors (missing colors, transparent elements) MUST occur

#### Scenario: Token set with logo
- GIVEN a token set defines `--nldesign-logo-url: url('../img/logos/amsterdam.svg')`
- WHEN the theme is rendered
- THEN the logo MUST be displayed in the header and login page via `background-image`
- AND the logo MUST be sized and positioned using `--nldesign-logo-center` and related variables

#### Scenario: Token set with lint/ribbon
- GIVEN a token set defines `--nldesign-color-logo-background`, `--nldesign-size-lint`, and `--nldesign-size-lint-height`
- WHEN the header renders
- THEN a colored ribbon MUST appear behind the logo area
- AND the ribbon dimensions MUST match the token values

#### Scenario: WCAG AA contrast in token sets
- GIVEN a token set defines `--nldesign-color-primary` and `--nldesign-color-primary-text`
- WHEN these colors are used together (e.g., on primary buttons)
- THEN the contrast ratio MUST be at least 4.5:1 for normal text
- AND token set authors MUST ensure their color combinations meet WCAG AA

### Requirement: Token Sets API Endpoints
The app MUST expose admin-only API endpoints for listing, getting, and setting token sets.

#### Scenario: List all available token sets
- GIVEN the admin is authenticated
- WHEN `GET /apps/nldesign/settings/tokensets` is called
- THEN the response MUST be JSON with `{"tokenSets": [...]}` containing all discovered token sets
- AND each token set object MUST have `id`, `name`, `description`, `design_system` fields
- AND token sets with theming metadata MUST include the `theming` object

#### Scenario: Get current token set
- GIVEN the admin is authenticated
- WHEN `GET /apps/nldesign/settings/tokenset` is called
- THEN the response MUST be JSON with `{"tokenSet": "<current-id>"}`
- AND the default MUST be `"nextcloud"` if not configured

#### Scenario: Set active token set
- GIVEN the admin is authenticated
- AND the token set `denhaag` exists
- WHEN `POST /apps/nldesign/settings/tokenset` is called with `tokenSet=denhaag`
- THEN the response MUST be JSON with `{"status": "ok", "tokenSet": "denhaag"}`
- AND the active token set MUST be updated in IConfig

#### Scenario: Set invalid token set returns error
- GIVEN the admin is authenticated
- AND the token set `nonexistent` does NOT exist
- WHEN `POST /apps/nldesign/settings/tokenset` is called with `tokenSet=nonexistent`
- THEN the response MUST be HTTP 400 with `{"error": "Invalid token set"}`

#### Scenario: Non-admin access denied
- GIVEN a non-admin user is authenticated
- WHEN any `/settings/tokenset` or `/settings/tokensets` endpoint is called
- THEN the request MUST be rejected by the `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)` annotation

### Requirement: Token Set Count and Coverage

The app MUST support at minimum the documented set of Dutch government organizations as token
sets, plus the `lasuite` set for European sovereign-workplace (MijnBureau/EDIC) bundles, and MAY
additionally ship the published Cunningham blue base as a `cunningham` set.

#### Scenario: All required token sets present

- GIVEN the nldesign app is installed
- WHEN the `css/tokens/` directory is scanned
- THEN it MUST contain CSS files for at least: rijkshuisstijl, amsterdam, utrecht, rotterdam,
  denhaag, nextcloud, lasuite
- AND the total number MUST be at least 40

#### Scenario: Token set count matches manifest

- GIVEN the `token-sets.json` manifest lists N entries
- WHEN the `css/tokens/` directory is scanned
- THEN each manifest entry MUST have a corresponding CSS file
- AND conversely, each CSS file SHOULD have a corresponding manifest entry (files without
  manifest entries receive auto-generated names)

#### Scenario: Token sets include major Dutch municipalities

- GIVEN the available token sets
- THEN they MUST include: amsterdam, rotterdam, denhaag, utrecht, groningen, nijmegen, leiden,
  tilburg, zwolle, haarlem
- AND they MUST include government organizations: rijkshuisstijl, duo, vng

#### Scenario: La Suite set manifest entry

- GIVEN the `token-sets.json` manifest
- WHEN the `lasuite` entry is read
- THEN it MUST declare `design_system: "lasuite"`
- AND its `theming` object MUST contain `primary_color: "#4844AD"` (the deployed violet brand) and
  `background_color: "#FFFFFF"`
- AND it MUST NOT contain a `logo` key (no La Suite/state logos are bundled — the logo slot
  stays empty for trademark reasons)

#### Scenario: La Suite token set file is a standard Layer-3 set

- GIVEN `css/tokens/lasuite.css`
- WHEN it is loaded after the lasuite design system bundle
- THEN it MUST declare only `--nldesign-*` custom properties on `:root` (REQ-TSET-005 rules
  apply unchanged)
- AND undefined tokens MUST fall through to the lasuite bridge/defaults values

#### Scenario: Cunningham blue-base set manifest entry (optional sibling)

- GIVEN the app ships the optional `cunningham` sibling set
- WHEN the `cunningham` entry in `token-sets.json` is read
- THEN it MUST declare `design_system: "cunningham"`
- AND its `theming` object MUST contain `primary_color: "#1A509F"` (brand-650 of the published
  Cunningham blue base — the same scale step the shared bridge/element-overrides derive
  `--color-primary` from for lasuite's violet `#4844AD`, so the swatch matches what actually
  renders) and `background_color: "#FFFFFF"`
- AND it MUST NOT contain a `logo` key
- AND `css/tokens/cunningham.css` MUST exist as a standard Layer-3 `--nldesign-*` set pinning the blue
  identity, reusing the shared generated `defaults.css` via its design system bundle
- AND shipping or omitting this set MUST NOT change any `lasuite` behaviour

### Requirement: Design System Association
Each token set MUST be associated with a design system that determines which CSS layers are loaded.

#### Scenario: Token set with nldesign design system
- GIVEN a token set has `design_system: "nldesign"` in the manifest
- WHEN the token set is activated
- THEN the full nldesign CSS stack (7 layers) MUST be loaded from `design-systems.json`
- AND the token set CSS MUST be loaded after the design system stylesheets

#### Scenario: Token set with "none" design system (stock Nextcloud)
- GIVEN the "nextcloud" token set has `design_system: "none"` in the manifest
- WHEN the token set is activated
- THEN no design system stylesheets MUST be loaded
- AND the token set CSS MUST NOT be loaded (the `designSystemId !== 'none'` check prevents it)
- AND Nextcloud's default theming MUST remain active

#### Scenario: Default design system for token sets without manifest entry
- GIVEN a CSS file exists in `css/tokens/` without a manifest entry
- WHEN the token set is discovered
- THEN `design_system` MUST default to `"nldesign"`
- AND the full nldesign CSS stack MUST be loaded when this set is activated

### Requirement: Token Set Preview
The app MUST provide an endpoint for previewing the resolved CSS values of a token set without applying it.

#### Scenario: Valid token set preview
- GIVEN the admin is authenticated
- AND the token set "amsterdam" exists
- WHEN `GET /apps/nldesign/settings/tokenset-preview/amsterdam` is called
- THEN the response MUST be JSON with `{"tokenSetId": "amsterdam", "resolved": {...}}`
- AND `resolved` MUST contain the CSS variable values that would result from applying this token set

#### Scenario: Invalid token set preview returns 404
- GIVEN the admin is authenticated
- AND the token set "nonexistent" does NOT exist
- WHEN `GET /apps/nldesign/settings/tokenset-preview/nonexistent` is called
- THEN the response MUST be HTTP 404 with `{"error": "Token set not found"}`

#### Scenario: Preview used by apply dialog
- GIVEN the admin selects a new token set in the dropdown
- WHEN the JavaScript prepares the apply dialog
- THEN it MUST call the preview endpoint to get resolved values
- AND it MUST compare these against the current custom overrides
- AND it MUST display the differences for the admin to review

### Requirement: Token Set Service Architecture
The `TokenSetService` MUST be a clean service class with `IAppManager` as its only dependency.

#### Scenario: Constructor dependency
- GIVEN `TokenSetService` is constructed
- THEN it MUST receive `IAppManager` via constructor injection
- AND it MUST use `IAppManager::getAppPath('nldesign')` to resolve filesystem paths

#### Scenario: Private helper methods
- GIVEN the service has internal methods
- THEN `getAppPath()` MUST be private and return the absolute app directory
- AND `readManifest()` MUST be private and handle all file-reading errors gracefully
- AND `formatName()` MUST be private and convert kebab-case ids to display names

#### Scenario: Service used in multiple locations
- GIVEN the `TokenSetService` is needed by both `Admin::getForm()` and `SettingsController`
- WHEN it is instantiated
- THEN `Admin::getForm()` creates a new instance via `new TokenSetService(appManager: $appManager)`
- AND `SettingsController::setTokenSet()` also creates a new instance
- AND the `SettingsController` also receives an injected instance via constructor for `getAvailableTokenSets()`

### Requirement: Route Configuration
Token set management endpoints MUST be registered in the route configuration.

#### Scenario: List token sets route
- GIVEN the routes configuration in `appinfo/routes.php`
- THEN `GET /settings/tokensets` MUST be mapped to `settings#getAvailableTokenSets`

#### Scenario: Get active token set route
- GIVEN the routes configuration
- THEN `GET /settings/tokenset` MUST be mapped to `settings#getTokenSet`

#### Scenario: Set active token set route
- GIVEN the routes configuration
- THEN `POST /settings/tokenset` MUST be mapped to `settings#setTokenSet`

#### Scenario: Token set preview route
- GIVEN the routes configuration
- THEN `GET /settings/tokenset-preview/{tokenSetId}` MUST be mapped to `settings#getTokenSetPreview`

### Requirement: Only Fully Functional Brands Are Selectable
The admin dropdown MUST offer only the token sets that are fully functional. A set that does not declare the vocabulary the design system reads renders as the `defaults.css` brand rather than its own, and offering it invites an admin to apply a theme that does not do what its name says. Today that leaves `nextcloud` and nothing else: every other shipped brand is still discovered, still served and still in the catalogue, but is not offered for selection until it passes the vocabulary audit.

Narrowing a picker MUST NOT be able to change what an instance is doing, so three ids survive the filter whatever the allowlist says.

#### Scenario: The allowlist is the only shipped set offered
- GIVEN an instance running the stock set
- WHEN the admin panel builds its dropdown
- THEN the dropdown MUST contain `TokenSetService::SELECTABLE_SHIPPED_SETS` and no other shipped set
- AND the full catalogue MUST remain unchanged: discovery, the public catalogue and the preview endpoint still answer for every shipped set

#### Scenario: The active set is always selectable
- GIVEN the instance is running a set that is not on the allowlist
- WHEN the dropdown is built
- THEN that set MUST still be offered
- BECAUSE dropping it would render the panel with no option selected, and the first save would silently re-theme the instance to whatever happened to come first

#### Scenario: A set a group mapping points at is always selectable
- GIVEN a per-group mapping assigns a token set to a group
- WHEN the dropdown is built
- THEN that set MUST still be offered
- BECAUSE the group picker is fed from this same list, and a group's theme would disappear from the UI while still being applied

#### Scenario: An imported set is always selectable
- GIVEN an admin has imported a `custom-*` set
- WHEN the dropdown is built
- THEN that set MUST be offered unconditionally
- BECAUSE the importer tells the admin their upload was added and selectable

#### Scenario: Adding a brand to the allowlist is an audit outcome
- GIVEN a shipped brand that the vocabulary audit reports as complete
- THEN it MAY be added to `SELECTABLE_SHIPPED_SETS`
- AND a brand the audit still reports as incomplete MUST NOT be

### Requirement: Shipped Token Set Vocabulary Completeness
A shipped token set MUST itself declare the `--nldesign-*` vocabulary the design system reads. A set that declares none of it still renders — as the brand in `css/systems/nldesign/defaults.css` rather than its own — which is why this is audited separately from contrast: no colour ratio can reveal it.

#### Scenario: Required tokens are judged on the set file alone
- GIVEN a shipped token set file under `css/tokens/`
- WHEN `TokenSetVocabularyAuditService` audits it
- THEN the set file MUST be read on its own, never layered over `css/systems/nldesign/defaults.css`
- AND every `REQUIRED_TOKENS` entry the file does not declare MUST be reported in `missingRequired`

#### Scenario: Raw palette steps may not claim the app vocabulary
- GIVEN a set declares a `--nldesign-*` name that no CSS layer in the app declares a default for or reads
- WHEN the set is audited
- THEN that name MUST be reported in `foreignNldesignNames`
- AND the raw upstream palette step MUST instead be declared under the brand's own prefix, for example `--zwolle-color-blue-40`

#### Scenario: The CSS primary and the manifest primary are one value
- GIVEN a set declares `--nldesign-color-primary` in CSS
- AND `token-sets.json` declares `theming.primary_color` for the same id
- WHEN the two values differ
- THEN `primaryMismatch` MUST be true
- BECAUSE the CSS paints the app while the manifest value is pushed into Nextcloud core theming, so a divergence means the two halves of one theme disagree

#### Scenario: A set the audit cannot judge reports nothing
- GIVEN a token set whose design system does not read `--nldesign-*` tokens at all
- WHEN the set is audited
- THEN `auditable` MUST be false
- AND the audit MUST report no findings for it, rather than reporting every required token as missing

#### Scenario: The PHP service and the Node CLI apply the same rules
- GIVEN `scripts/audit-token-sets.mjs` mirrors this service for the standalone CLI
- THEN its `REQUIRED_TOKENS` MUST stay byte-identical to `TokenSetVocabularyAuditService::REQUIRED_TOKENS`
- AND the two lists MUST change together or not at all

### Requirement: Incomplete Sets Are Surfaced in the Admin Dropdown
A shipped set that fails the vocabulary audit MUST reach the admin as a non-blocking warning on the channel the dropdown and the apply dialog already read, so it cannot be applied silently.

#### Scenario: The verdict rides the existing warnings channel
- GIVEN a shipped set the vocabulary audit reports as incomplete
- WHEN `TokenSetService` builds that set's entry
- THEN an entry of the form `{kind: "incomplete", missing, foreign, primaryMismatch, declaredPrimary, cssPrimary}` MUST be appended to the entry's `warnings`
- AND it MUST be appended AFTER the WCAG contrast warnings, so those keep their position in the list

#### Scenario: A complete set carries no incomplete warning
- GIVEN a shipped set that declares the full required vocabulary
- WHEN its entry is built
- THEN no `incomplete` warning MUST be added for it

#### Scenario: A custom upload keeps its uploader-supplied warnings
- GIVEN a genuine `custom-*` set imported by an admin
- WHEN its entry is built
- THEN its `warnings` MUST be the ones recorded at upload time
- AND the shipped-set vocabulary audit MUST NOT run for it

## Current Implementation Status

**Fully implemented:**
- Filesystem-based discovery: `TokenSetService::getAvailableTokenSets()` scans `css/tokens/` for `.css` files and merges metadata from `token-sets.json` (`lib/Service/TokenSetService.php` lines 62-98)
- Metadata merging: `readManifest()` reads `token-sets.json`, indexes by `id`, merges `name`, `description`, `design_system`, and optional `theming` object (lines 127-151)
- Auto-generated names for CSS files without manifest entry: `formatName()` uses `ucwords(str_replace('-', ' ', $id))` (lines 160-163)
- Default design_system "nldesign" for entries without manifest (line 83)
- Manifest entries without CSS files are excluded
- Alphabetical sort by name (case-insensitive): `usort()` with `strcasecmp` (line 95)
- Malformed/missing manifest: `readManifest()` returns `[]` on invalid JSON, missing file, or unreadable file
- Active token set storage: Default `'nextcloud'` via `IConfig::getAppValue()` in `Application.php` (line 84) and `SettingsController::getTokenSet()` (line 134)
- Token set validation: `isValidTokenSet()` checks for path traversal (`/` and `..`) and verifies CSS file existence (lines 107-118)
- API endpoints: all four routes implemented (tokensets GET, tokenset GET/POST, tokenset-preview GET)
- Admin-only access: `@AuthorizedAdminSetting` annotation on all endpoints
- Token set count: 39+ CSS files in `css/tokens/`, manifest with corresponding entries
- Required token sets present: rijkshuisstijl, amsterdam, utrecht, rotterdam, denhaag, nextcloud all exist
- Token set preview: `SettingsController::getTokenSetPreview()` uses `TokenSetPreviewService::getResolvedColors()` (lines 313-322)
- Design system association: `design_system` field included in token set metadata

**Not yet implemented:**
- All requirements in this spec are fully implemented.

## Standards & References
- NL Design System community design tokens: https://nldesignsystem.nl/
- W3C Design Tokens community group specification: https://design-tokens.github.io/community-group/format/
- CSS Custom Properties specification: https://www.w3.org/TR/css-variables-1/
- Rijkshuisstijl (Dutch government house style): https://www.rijkshuisstijl.nl/
- Utrecht Design System `--utrecht-*` token namespace: https://nl-design-system.github.io/utrecht/
- OWASP Path Traversal prevention for token set ID validation
- WCAG 2.1 AA contrast requirements for token set color combinations
