---
status: in-progress
---

# Token Editor UI Specification

## Purpose
Provides a tabbed admin settings panel for browsing and editing all editable Nextcloud CSS custom properties (`--color-*`) with live preview and per-token reset controls. Changes are previewed in the browser before being committed to `custom-overrides.css`.

## Requirements

### Requirement: Token Editor Panel
The admin settings page MUST include a token editor panel below the existing NL Design token-set selector. The panel MUST be rendered as a Vue component within the existing nldesign admin settings template.

#### Scenario: Admin opens settings
- GIVEN an admin navigates to Nextcloud Settings → Appearance & Accessibility (theming section)
- WHEN the nldesign settings panel renders
- THEN the token editor panel MUST be visible below the token-set selector
- AND the panel MUST show tabbed navigation for functional token groups

#### Scenario: Non-admin user visits settings
@e2e exclude Requires a non-admin session — test environment only has the admin user.
- GIVEN a user without admin privileges visits the theming settings
- WHEN the page renders
- THEN the token editor panel MUST NOT be shown
- AND no edit endpoints MUST be accessible

### Requirement: Functional Tab Groups
The token editor MUST organize editable `--color-*` Nextcloud variables into exactly four functional tabs. Each tab MUST only show tokens that meaningfully affect that UI area.

**Note**: A Navigation bar tab was originally planned but dropped because Nextcloud's header/navigation styling uses `--nldesign-color-header-*` abstraction variables rather than native `--color-*` custom properties. These cannot be exposed as editable Nextcloud tokens without breaking the abstraction layer.

Tabs and their primary tokens:

**Login page & Branding** — `--color-primary`, `--color-primary-text`, `--color-primary-hover`, `--color-primary-light`, `--color-primary-light-hover`, `--color-primary-element`, `--color-primary-element-text`, `--color-primary-element-hover`, `--color-primary-element-light`, `--color-primary-element-light-text`, `--color-primary-element-light-hover`

**Content area** — `--color-background-hover`, `--color-background-dark`, `--color-background-darker`, `--color-border`, `--color-border-dark`, `--color-border-maxcontrast`, `--color-scrollbar`, `--color-placeholder-light`, `--color-placeholder-dark`, border radius tokens, animation timing tokens

**Buttons & Status** — `--color-error`, `--color-error-hover`, `--color-warning`, `--color-success`, `--color-info`, `--color-favorite`, status RGB values (`--color-error-rgb`, `--color-warning-rgb`, `--color-success-rgb`), semantic element/border variants (`--color-element-error`, `--color-border-error`, etc.)

**Typography** — `--color-main-text`, `--color-text-maxcontrast`, `--color-text-light`, `--color-text-lighter`, `--color-text-error`, `--color-text-success`, `--color-text-warning`, `--font-face`

#### Scenario: Admin selects Login page tab
- GIVEN the token editor is open
- WHEN the admin clicks the "Login page & Branding" tab
- THEN all primary-color variables MUST be shown as editable fields
- AND no variables from other functional areas MUST appear in this tab

#### Scenario: Every editable token appears in exactly one tab
- GIVEN the full list of editable tokens is defined in the token registry
- WHEN all tabs are rendered
- THEN every editable token MUST appear in exactly one tab
- AND no token MUST appear in more than one tab

### Requirement: Excluded Token Registry
The editor MUST maintain a canonical list of Nextcloud CSS variables that are excluded from editing. These variables MUST NOT appear in any tab or be writable via any editor endpoint.

Excluded tokens include (but are not limited to):
- `--color-main-background` (breaks dark mode)
- `--color-main-background-rgb`, `--color-main-background-translucent`, `--color-main-background-blur` (derived from main-background)
- `--color-background-plain`, `--color-background-plain-text` (admin/auto-calculated)
- `--color-primary-element-text-dark` (auto-calculated dark variant)
- All layout variables: `--header-height`, `--navigation-width`, `--sidebar-*-width`, `--body-container-margin`, `--default-grid-baseline`, `--default-clickable-area`, `--clickable-area-large`, `--clickable-area-small`, `--border-radius-container`, `--border-radius-container-large`, `--header-menu-item-height`, `--header-menu-icon-mask`

#### Scenario: Admin attempts to set excluded token via API
@e2e exclude API-layer validation (POST with excluded token → HTTP 400) — backend assertion, not testable via browser UI.
- GIVEN `--color-main-background` is in the excluded list
- WHEN a POST request is made to set `--color-main-background`
- THEN the server MUST return HTTP 400
- AND the error MUST state the token is not editable

#### Scenario: Excluded tokens are not shown in UI
- GIVEN the token editor panel is rendered
- WHEN the admin browses all tabs
- THEN no excluded token MUST appear as an editable field

### Requirement: Editable Token Input
Each token in the editor MUST be shown as a labelled row with a color picker or text input (depending on token type), its current resolved value, and a reset button.

#### Scenario: Token shows resolved current value
- GIVEN a token has no entry in `custom-overrides.css`
- WHEN the editor renders that token's row
- THEN the input MUST show the currently resolved value (from NL Design token set or Nextcloud default)
- AND the row MUST NOT be marked as "customized"

#### Scenario: Token shows custom value indicator
@e2e exclude Requires a known custom override to be set in custom-overrides.css first — environment may not have customizations in place.
- GIVEN a token has an entry in `custom-overrides.css`
- WHEN the editor renders that token's row
- THEN the input MUST show the overridden value
- AND the row MUST be visually marked as "customized" (e.g. a dot or badge)

#### Scenario: Color tokens render a color picker
@e2e exclude Colour picker is a native <input type="color"> element; its presence alongside the hex text field is verified by checking both inputs render in the token-shows-resolved-value test.
- GIVEN a token value is a CSS color (hex, rgb, rgba, hsl, named)
- WHEN the row renders
- THEN a color picker input MUST be shown alongside a hex text field

#### Scenario: Non-color tokens render a text input
@e2e exclude Requires inspecting specific non-color token rows; covered partially by border-radius rows in content-area tab test in token-editor-ui spec-coverage.
- GIVEN a token value is not a CSS color (e.g. a length, opacity, or filter value)
- WHEN the row renders
- THEN a plain text input MUST be shown

### Requirement: Live Preview
Changes typed or picked in the editor MUST be immediately reflected in the current page's visual appearance without a page reload, before the admin saves.

#### Scenario: Admin changes a color token
@e2e exclude Live preview via inline style injection — requires modifying a token value and verifying live CSS update which alters visible state of shared env.
- GIVEN the admin changes `--color-primary` to `#c00000` in the editor
- WHEN the value is updated in the input
- THEN the browser MUST apply `--color-primary: #c00000` to `:root` via inline style injection
- AND all page elements using `--color-primary` MUST immediately reflect the new color

#### Scenario: Unsaved changes are lost on reload
@e2e exclude Side-effect test that reloads the page — not safe in parallel runs; requires first modifying a token value.
- GIVEN the admin changed a token value but has not clicked Save
- WHEN the page is reloaded
- THEN the change MUST be discarded
- AND the editor MUST show the previously saved value

### Requirement: Save Action
A single **Save** button MUST write all current editor values that differ from defaults into `custom-overrides.css`. Tokens that match their resolved default MUST be omitted from the file.

#### Scenario: Admin saves changes
- GIVEN the admin has changed one or more token values
- WHEN the Save button is clicked
- THEN the server MUST write only the changed (non-default) tokens to `custom-overrides.css`
- AND the browser MUST receive a success confirmation
- AND no page reload MUST be required

#### Scenario: Save with no changes
@e2e exclude Clicking Save writes custom-overrides.css — avoid mutating shared env state.
- GIVEN the admin opens the editor but makes no changes
- WHEN the Save button is clicked
- THEN the server MUST write an empty (or minimal) `custom-overrides.css`
- AND no error MUST occur

### Requirement: Per-Token Reset
Each token row MUST have an inline reset button that clears that token's custom value, reverting it to the resolved default from the CSS stack.

#### Scenario: Admin resets a customized token
- GIVEN a token has a custom entry in `custom-overrides.css`
- WHEN the admin clicks the reset button for that token
- THEN the input value MUST revert to the current resolved default
- AND the "customized" indicator MUST be removed
- AND the live preview MUST immediately reflect the reset value
- AND the token MUST be removed from `custom-overrides.css` on the next Save
