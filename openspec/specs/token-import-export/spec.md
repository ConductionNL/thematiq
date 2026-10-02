---
status: in-progress
---

# Token Import/Export Specification

## Purpose
Allows admins to download the current `custom-overrides.css` as a portable file and upload a previously saved file to restore or share a token configuration. Only known, editable Nextcloud `--color-*` tokens are accepted on import — unknown variables are silently rejected and their count is reported.
## Requirements
### Requirement: Export Current Overrides

The admin settings panel MUST provide a **Download** button that exports the current
`custom-overrides.css` as a file download. This export covers ONLY the token-editor overrides
file — it is NOT a complete configuration export: the active token set, feature toggles, per-app
exclusions, and custom token sets are exported exclusively by the full configuration bundle
defined in the `config-portability` spec (`GET /settings/config/export` /
`occ thematiq:config:export`), and the overrides UI SHOULD point admins needing whole-config
promotion (OTAP) at the bundle.

Before downloading, the panel MUST say in a dialog what the file contains and what it does not.
The download MUST carry the request token: the endpoint is CSRF-protected, so a plain link is
refused and the browser reports the download as failed.

#### Scenario: Download says what it does first

- GIVEN the admin is on the theming settings with the token editor loaded
- WHEN the admin clicks Download
- THEN a dialog MUST open naming the theme, saying the file holds only its saved overrides and
  not a complete theme, and pointing at Export as token set and the configuration bundle
- AND nothing MUST be downloaded until the admin confirms
- AND Cancel MUST close the dialog without downloading

#### Scenario: Admin downloads overrides

- GIVEN `custom-overrides.css` contains `--color-primary: #c00000` and `--color-error: #b30000`
- WHEN the admin clicks Download
- THEN the browser MUST download a file named `custom-overrides.css`
- AND the file content MUST be valid CSS containing the current overrides
- AND the file MUST be formatted identically to the server-side `custom-overrides.css`

#### Scenario: Download with no custom overrides

@e2e exclude Requires custom-overrides.css to be empty — environment state not guaranteed; file content verification requires intercepting download response.
- GIVEN `custom-overrides.css` is empty (no custom tokens set)
- WHEN the admin clicks Download
- THEN the browser MUST download a file with only the header comment and an empty `:root {}` block
- AND the download MUST NOT be blocked or result in an error

#### Scenario: Download is a GET request to a dedicated endpoint

@e2e exclude API-layer assertion (Content-Type, Content-Disposition headers) — not testable via browser UI DOM; would require network interception.
- GIVEN the admin clicks Download
- WHEN the request is made
- THEN it MUST call `GET /api/overrides/export`
- AND the response Content-Type MUST be `text/css`
- AND the Content-Disposition MUST be `attachment; filename="custom-overrides.css"`

#### Scenario: Overrides export is distinct from the configuration bundle

@e2e exclude Scope/documentation assertion — verified by spec cross-reference and unit tests on the two endpoints' payloads, not via browser DOM.
- GIVEN an admin needs to promote the COMPLETE nldesign configuration to another environment
- WHEN they use the overrides Download button alone
- THEN they obtain only `custom-overrides.css` — the active token set, toggles, exclusions, and
  custom token sets are NOT included
- AND the full-bundle export from the `config-portability` spec MUST be used instead for
  whole-config promotion

### Requirement: Import Token File

The admin settings panel MUST provide an **Upload** button that accepts a CSS file, parses it
for known `--color-*` tokens, and writes the recognized tokens to `custom-overrides.css`,
replacing the current overrides. This import touches ONLY the overrides file: it MUST NOT change
the active token set, feature toggles, per-app exclusions, or custom token sets — importing the
complete configuration is the `config-portability` bundle's job
(`POST /settings/config/import` / `occ thematiq:config:import`), which reuses this capability's
editable-token whitelist semantics for its overrides section.

Before the file picker opens, the panel MUST say in a dialog that the file replaces every value
saved for the theme.

#### Scenario: Upload says what it does first

- GIVEN the admin is on the theming settings with the token editor loaded
- WHEN the admin clicks Upload
- THEN a dialog MUST open naming the theme and saying the file replaces every value saved for
  it, that unknown values are skipped and unsaved changes are lost, and pointing at Custom token
  sets for adding a whole theme
- AND the file picker MUST only open once the admin confirms
- AND Cancel MUST close the dialog without opening the file picker

#### Scenario: Admin uploads a valid overrides file

- GIVEN a CSS file contains `--color-primary: #aa0000` and `--color-error: #990000`
- WHEN the admin uploads the file
- THEN both tokens MUST be written to `custom-overrides.css` (replacing previous overrides)
- AND the token editor forms MUST reflect the imported values
- AND the live preview MUST update to show the imported values

#### Scenario: Import replaces existing overrides

@e2e exclude Requires file upload and filesystem verification of custom-overrides.css content — mutates shared env; file content not verifiable via DOM.
- GIVEN `custom-overrides.css` currently contains `--color-warning: #ff8800`
- AND the uploaded file contains `--color-primary: #aa0000` but NOT `--color-warning`
- WHEN the admin uploads the file
- THEN `custom-overrides.css` MUST contain only `--color-primary: #aa0000`
- AND `--color-warning` MUST be removed (import is a full replace, not a merge)

#### Scenario: Overrides import never touches other configuration

@e2e exclude Backend state assertion across multiple config keys — covered by unit tests; would mutate shared-env config.
- GIVEN the active token set is `amsterdam` with one custom token set installed
- WHEN an overrides CSS file is uploaded via `POST /api/overrides/import`
- THEN after the import the `token_set` app value, both feature toggles, the exclusion list, and
  all custom token sets MUST be unchanged

### Requirement: Import Validation
On upload, the importer MUST validate each CSS custom property against the canonical editable token registry. Only tokens on the editable list MUST be written.
@e2e exclude All import-validation scenarios require file upload + server-side parse response assertions — backend validation logic, not testable via DOM; would mutate shared-env custom-overrides.css.

#### Scenario: File contains unknown tokens
- GIVEN an uploaded CSS file contains `--color-primary: #aa0000` (known) and `--my-custom-var: red` (unknown)
- WHEN the file is imported
- THEN `--color-primary` MUST be written to `custom-overrides.css`
- AND `--my-custom-var` MUST be silently rejected
- AND the response MUST report: "2 tokens found: 1 imported, 1 skipped"

#### Scenario: File contains only unknown tokens
- GIVEN an uploaded CSS file contains only variables not in the editable token registry
- WHEN the file is imported
- THEN `custom-overrides.css` MUST be written as empty (header + empty `:root {}`)
- AND the response MUST report: "X tokens found: 0 imported, X skipped"
- AND no error MUST be thrown (it is valid to import a file that contributes no tokens)

#### Scenario: File contains excluded tokens
- GIVEN an uploaded CSS file contains `--color-main-background: #ffffff` (excluded)
- WHEN the file is imported
- THEN `--color-main-background` MUST be silently rejected
- AND it MUST be counted in the "skipped" total

#### Scenario: File is not valid CSS
- GIVEN the admin uploads a file that is not parseable CSS (e.g. a JSON file or empty file)
- WHEN the import endpoint receives the file
- THEN the server MUST return HTTP 400
- AND the error MUST state the file could not be parsed as CSS
- AND `custom-overrides.css` MUST remain unchanged

#### Scenario: File exceeds size limit
- GIVEN the admin uploads a file larger than 256 KB
- WHEN the upload is submitted
- THEN the server MUST return HTTP 413
- AND `custom-overrides.css` MUST remain unchanged

### Requirement: Import Result Feedback
After a successful import, the UI MUST show a summary of the import result before the admin can continue.
@e2e exclude Requires performing a file upload that mutates shared-env custom-overrides.css — not safe to run in shared test environment.

#### Scenario: Import summary is shown
- GIVEN a file with 15 tokens was uploaded, of which 12 were known and 3 were unknown
- WHEN the import completes
- THEN the UI MUST show a message: "12 tokens imported, 3 tokens skipped (not recognized)"
- AND the message MUST be dismissible
- AND the token editor forms MUST immediately reflect the imported values without a page reload

### Requirement: Upload Endpoint
The import MUST be handled by a dedicated POST endpoint that accepts a multipart file upload.
@e2e exclude API endpoint assertion (multipart POST, JSON response format) — backend/network-layer, not testable via DOM; upload control presence is covered by token-import-export spec-coverage.

#### Scenario: Upload endpoint receives file
- GIVEN the admin submits a file via the Upload button
- WHEN the request is made
- THEN it MUST POST to `POST /api/overrides/import` as `multipart/form-data`
- AND the server MUST parse the file content server-side (not rely on client-side JS parsing)
- AND the response MUST be JSON with `{ imported: N, skipped: M }`


### Requirement: Token Set Round Trip
A theme exported with **Export as token set** and uploaded again under Custom token sets MUST come
back the same theme. The export MUST write only the tokens the set itself declares, with the
overrides saved for that set folded in, and never the design system's defaults that only exist to
draw the preview. It MUST mark the file with the design system the set is worn on, and the upload
MUST store a marked file as it arrived, on that design system, instead of converting it.

#### Scenario: Export writes only what the set declares
@e2e exclude Reads the downloaded file's content — covered by the playground unit tests; a browser download cannot be read from the page.
- GIVEN a theme saved from the stock Nextcloud set with one colour changed
- WHEN the admin clicks Export as token set
- THEN the file MUST contain the set's own tokens with the saved override folded in
- AND it MUST NOT contain nldesign defaults the set does not declare, such as spacing tokens
- AND it MUST carry the marker `/* thematiq-token-set: design-system=none */`

#### Scenario: A marked file is stored as it arrived
@e2e exclude Uploads a token set and inspects the stored file — mutates shared-env custom sets; covered by the controller unit tests.
- GIVEN a file carrying `/* thematiq-token-set: design-system=none */`
- WHEN it is uploaded under Custom token sets
- THEN it MUST be stored as it arrived, without conversion
- AND the new set MUST be recorded on design system `none`
- AND a design system the manifest does not ship MUST NOT be recorded
