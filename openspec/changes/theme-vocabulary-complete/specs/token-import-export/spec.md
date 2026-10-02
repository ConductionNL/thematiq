## MODIFIED Requirements

### Requirement: Import Validation
On upload, the importer MUST validate each CSS custom property against the canonical editable token registry. Only tokens on the editable list MUST be written.
@e2e exclude All import-validation scenarios require a file upload and assertions on the server's parse response: backend validation logic with no DOM surface, and a run would change the shared environment's custom-overrides.css.

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
- GIVEN an uploaded CSS file contains `--icon-download-dark: url(x.svg)` (excluded, class `icon`)
- WHEN the file is imported
- THEN `--icon-download-dark` MUST be silently rejected
- AND it MUST be counted in the "skipped" total

#### Scenario: File contains a formerly excluded token
- GIVEN an uploaded CSS file contains `--color-main-background: #fdfcf8`
- WHEN the file is imported
- THEN `--color-main-background` MUST be written to `custom-overrides.css`
- AND it MUST be counted in the "imported" total

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
