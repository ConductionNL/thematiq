## MODIFIED Requirements

### Requirement: Excluded Token Registry
The editor MUST refuse to edit any Nextcloud CSS variable whose inventory status is `excluded`, and MUST NOT exclude any variable for being auto-calculated, dark-mode sensitive or part of the layout.

Excluded tokens are exactly the inventory entries with status `excluded`:
- `icon` entries, such as `--icon-download-dark` and `--image-logo`, which carry image URLs
- `runtime` entries, which Nextcloud's own JavaScript writes on every render

Variables that were excluded before and are now settable include `--color-main-background`, its `-rgb`, `-translucent` and `-blur` variants, `--color-background-plain`, `--color-primary-element-text-dark`, and the layout variables `--header-height`, `--navigation-width`, `--sidebar-min-width`, `--sidebar-max-width`, `--body-container-margin`, `--default-grid-baseline`, `--default-clickable-area`, `--clickable-area-large`, `--clickable-area-small`, `--border-radius-container`, `--border-radius-container-large` and `--header-menu-item-height`.

#### Scenario: Admin attempts to set excluded token via API
@e2e exclude API-layer validation: a POST with an excluded token returns HTTP 400, a backend assertion with no browser surface.
- GIVEN `--icon-download-dark` has inventory status `excluded`
- WHEN a POST request is made to set `--icon-download-dark`
- THEN the server MUST return HTTP 400
- AND the error MUST state the token is not editable

#### Scenario: Excluded tokens are not shown in UI
- GIVEN the token editor panel is rendered
- WHEN the admin browses all tabs
- THEN no excluded token MUST appear as an editable field

#### Scenario: A formerly excluded token is now editable
@e2e exclude API-layer validation; the editable row is browser-tested in `token-editor-at-scale`.
- GIVEN `--color-main-background` was excluded before this change
- WHEN a POST request sets `--color-main-background` to `#fdfcf8`
- THEN the server MUST accept it
- AND the saved override MUST apply to the light scheme only, because `--color-main-background` is a dark-mode-compatibility variable (REQ-CSS-007)
