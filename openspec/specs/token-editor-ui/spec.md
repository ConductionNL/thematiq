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
The token editor MUST organize theme tokens and the curated component tokens into four functional tabs, and MUST list every internal and Conduction token in a group named after the component or app that owns it.

Tabs and their primary tokens:

**Login page & Branding**: `--color-primary`, `--color-primary-text`, `--color-primary-hover`, `--color-primary-light`, `--color-primary-light-hover`, `--color-primary-element`, `--color-primary-element-text`, `--color-primary-element-hover`, `--color-primary-element-light`, `--color-primary-element-light-text`, `--color-primary-element-light-hover`

**Content area**: `--color-background-hover`, `--color-background-dark`, `--color-background-darker`, `--color-border`, `--color-border-dark`, `--color-border-maxcontrast`, `--color-scrollbar`, `--color-placeholder-light`, `--color-placeholder-dark`, `--color-main-background`, `--color-box-shadow`, `--color-mark`, `--color-text-selection`, `--color-background-selection`, border radius tokens, animation timing tokens

**Buttons & Status**: `--color-error`, `--color-error-hover`, `--color-warning`, `--color-warning-hover`, `--color-success`, `--color-success-hover`, `--color-info`, `--color-info-hover`, `--color-favorite`, status RGB values, semantic element/border variants, the assistant colours

**Typography**: `--color-main-text`, `--color-text-maxcontrast`, `--color-text-light`, `--color-text-lighter`, `--color-text-error`, `--color-text-success`, `--color-text-warning`, `--font-face`, `--default-font-size`, `--font-size-small`, `--default-line-height`

Component groups follow the tabs, one per owning component or app, each under a translated heading. An Advanced group holds every token the registry flags `advanced`, and those tokens leave their tab.

#### Scenario: Admin selects Login page tab
- GIVEN the token editor is open
- WHEN the admin clicks the "Login page & Branding" tab
- THEN all primary-color variables MUST be shown as editable fields
- AND no variables from other functional areas MUST appear in this tab

#### Scenario: Every editable token appears in exactly one tab
- GIVEN the full list of editable tokens is defined in the token registry
- WHEN all tabs and groups are rendered
- THEN every editable token MUST appear in exactly one tab or group
- AND no token MUST appear in more than one

#### Scenario: The date picker has its own group
- GIVEN the registry holds `--nldesign-nc-dp-hover-color` owned by the date picker
- WHEN the admin opens the "Date picker" group
- THEN the token MUST be listed there with `--dp-hover-color` as its row label

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

### Requirement: Colour fields accept transparency

Every `color` row MUST offer an opacity control next to the colour picker. The text field MUST
accept `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`, `rgb()`, `rgba()`, `hsl()`, `hsla()`, a named colour
and `transparent`. Moving the picker MUST keep the current alpha. At full opacity the editor MUST
write `#rrggbb`, below it `#rrggbbaa`. The swatch MUST show the colour over a checkerboard.

#### Scenario: An administrator makes the focus colour half transparent
- GIVEN an administrator on Settings > Administration > Theming, in the token editor
- WHEN the administrator sets the opacity of "Primary light" to 50% and saves
- THEN `custom-overrides.css` MUST declare `--color-primary-light` with an 8-digit hex ending in `80`
- AND the page MUST show the translucent colour without a reload

#### Scenario: Moving the picker keeps the alpha
- GIVEN "Primary light" holds `#15427380`
- WHEN the administrator picks `#01689b` with the colour picker
- THEN the text field MUST read `#01689b80`

#### Scenario: A typed 8-digit hex updates both controls
- GIVEN the token editor is open
- WHEN the administrator types `#154273cc` into the text field of "Primary color"
- THEN the picker MUST show `#154273`
- AND the opacity control MUST show 80%

### Requirement: Each colour token has an optional dark value

Every `color` row MUST offer a "Dark" value with its own picker, opacity control and text field.
When the dark value is empty, the field MUST show the derived dark value as its placeholder, and
saving MUST store that derived value. The derived value MUST come from the same code that
generates the set's dark stylesheet. `GET /apps/thematiq/settings/overrides` MUST return the dark
values, and `POST` MUST accept them as `darkOverrides`.

#### Scenario: An administrator gives the primary colour its own dark value
- GIVEN an administrator in the token editor
- WHEN the administrator sets "Primary color" to `#154273` and its dark value to `#8fb8e6`, and saves
- THEN a user who chose the dark theme MUST see `#8fb8e6` as the primary colour
- AND a user on the light theme MUST see `#154273`

#### Scenario: An empty dark value is derived and shown
- GIVEN an administrator sets "Background hover" to `#f0f4f8` and leaves its dark value empty
- WHEN the row renders
- THEN the dark field's placeholder MUST show the value the dark palette derives for `#f0f4f8`
- AND saving MUST store that value as the dark override

### Requirement: Motion tokens are typed and reach every transition

The editor MUST list `--animation-quick` and `--animation-slow` as `duration` rows: a number
field and a unit select (`ms`, `s`). Saving one MUST write the Nextcloud name and the matching
thematiq name (`--nldesign-animation-quick`, `--nldesign-animation-slow`) with the same value. A new
editable token `--nldesign-animation-easing` MUST be an `easing` row: a select with `linear`,
`ease`, `ease-in`, `ease-out`, `ease-in-out` and "Custom curve", which opens four number fields for
`cubic-bezier()`. Every transition in `css/systems/nldesign/theme.css` that uses `ease` today MUST
read `--nldesign-animation-easing` instead. A preview MUST move a block with the chosen duration
and easing, and MUST stay still under `prefers-reduced-motion: reduce`.

#### Scenario: An administrator slows down quick animations
- GIVEN an administrator in the token editor, content area tab
- WHEN the administrator sets "Animation quick" to 150 ms and saves
- THEN `custom-overrides.css` MUST declare `--nldesign-animation-quick: 150ms !important`
- AND it MUST declare `--animation-quick: 150ms !important`
- AND the hover transition of a primary button MUST take 150 ms

#### Scenario: An administrator picks a custom easing curve
- GIVEN an administrator in the token editor
- WHEN the administrator chooses "Custom curve" and enters 0.2, 0, 0, 1, and saves
- THEN `--nldesign-animation-easing` MUST be `cubic-bezier(0.2, 0, 0, 1)`
- AND the hover transition of a primary button MUST use that curve

#### Scenario: Existing motion overrides survive the upgrade
@e2e exclude Upgrade step, covered by PHPUnit on the repair step
- GIVEN `custom-overrides.css` declares `--animation-quick: 200ms !important` before the upgrade
- WHEN the app is upgraded
- THEN the file MUST declare `--nldesign-animation-quick: 200ms !important` and `--animation-quick: 200ms !important`

#### Scenario: Reduced motion still wins
- GIVEN "Animation quick" is set to 400 ms
- AND a user whose system asks for reduced motion
- WHEN the user hovers a primary button
- THEN no transition MUST run, as the reduced motion rules in `css/systems/nldesign/theme.css` require

### Requirement: The server checks each value against its token type

`POST /apps/thematiq/settings/overrides` MUST check every value against its token's type:
`color`, `duration` (0 to 5000 ms, or 0 to 5 s), `easing` (a keyword, or `cubic-bezier()` with both
x values in 0..1) or `text`. A value that fails MUST be refused with 400 naming the token and the
type, and nothing MUST be written. A token name the registry does not list MUST also be refused
with 400.

#### Scenario: A duration without a unit is refused
@e2e exclude API validation branch, covered by PHPUnit on OverridesController and the Newman collection
- GIVEN an administrator
- WHEN they post `{"overrides": {"--nldesign-animation-quick": "150"}}`
- THEN the response MUST be 400 naming `--nldesign-animation-quick` and the type `duration`
- AND `custom-overrides.css` MUST be unchanged

#### Scenario: A malformed colour is refused
@e2e exclude API validation branch, covered by PHPUnit on OverridesController
- GIVEN an administrator
- WHEN they post `{"overrides": {"--color-primary": "#15427"}}`
- THEN the response MUST be 400 naming `--color-primary` and the type `color`

#### Scenario: An easing curve out of range is refused
@e2e exclude API validation branch, covered by PHPUnit on TokenValueValidator
- GIVEN an administrator
- WHEN they post `{"overrides": {"--nldesign-animation-easing": "cubic-bezier(1.5, 0, 0, 1)"}}`
- THEN the response MUST be 400, because x1 is outside 0..1

### Requirement: Search finds any token
The editor MUST offer one search field that filters every tab and group by label, CSS name and owning component.

#### Scenario: Search by CSS name
- GIVEN the editor is open
- WHEN the admin types `dp-hover` in the search field
- THEN `--nldesign-nc-dp-hover-color` MUST be shown
- AND groups without a match MUST be hidden

#### Scenario: Search by component name
- GIVEN the editor is open in Dutch
- WHEN the admin types `datumkiezer`
- THEN every token of the date picker group MUST be shown

#### Scenario: The search field has a label
- GIVEN a screen reader user reaches the search field
- WHEN the field gets focus
- THEN its accessible name MUST state that it searches the tokens

### Requirement: Advanced tokens warn before they are edited
The Advanced group MUST be collapsed by default and MUST show a warning, in text, that its values change Nextcloud's layout and can break it.

#### Scenario: The group starts collapsed
- GIVEN the editor is opened
- WHEN the page renders
- THEN the Advanced group MUST be collapsed
- AND its toggle MUST expose `aria-expanded="false"`

#### Scenario: Opening shows the warning first
- GIVEN the admin opens the Advanced group
- WHEN its content renders
- THEN the warning MUST appear above the first field
- AND it MUST NOT rely on colour alone

### Requirement: Each row shows Nextcloud's own value and the token's note
Each row MUST show the value Nextcloud gives the variable under the admin's current theme, and the inventory note when one exists.

#### Scenario: Stock value of a hardcoded colour
- GIVEN the admin uses the default light theme
- WHEN the "Date picker" group is opened
- THEN the row for `--dp-hover-color` MUST show Nextcloud's own light value next to the field

#### Scenario: A note is shown
- GIVEN the inventory note for `--color-text-selection` says it pairs with the selection wash
- WHEN its row renders
- THEN the note MUST be shown with the row

### Requirement: Settable rows take a separate dark value
Rows for the 45 settable theme tokens and for internal colour tokens MUST offer an optional dark value that, left empty, keeps Nextcloud's own dark value. Brand rows MUST keep the optional dark value from `authoring-token-value-types` (#809), which, left empty, is derived.

#### Scenario: Admin gives a dark value
- GIVEN the admin sets `--color-mark` to `#ffe08a` and its dark value to `#5c4a00`
- WHEN the admin saves and switches to Nextcloud's dark theme
- THEN a search highlight MUST show `#5c4a00`

#### Scenario: No dark value given
- GIVEN the admin sets `--color-mark` to `#ffe08a` and leaves the dark value empty
- WHEN the admin switches to Nextcloud's dark theme
- THEN a search highlight MUST show Nextcloud's own dark value

#### Scenario: A brand row's empty dark field is derived
- GIVEN the "Login page & Branding" tab is open
- WHEN the `--color-primary` row renders
- THEN its dark field MUST show the derived dark value as its placeholder

#### Scenario: An internal size has no dark field
- GIVEN the "Date picker" group is open
- WHEN the `--dp-font-size` row renders
- THEN it MUST NOT offer a separate dark value

### Requirement: Groups render their rows when opened
The editor MUST NOT build the rows of a collapsed group until the admin opens it or a search matches it.

#### Scenario: Page load builds no group's rows
- GIVEN the registry holds 453 internal tokens
- WHEN the settings page loads
- THEN no row of a collapsed group MUST be in the document

### Requirement: The editor states the registry's count
The editor MUST show the number of editable tokens the registry reports, and the user documentation MUST state the same number.

#### Scenario: The count matches the registry
- GIVEN `TokenRegistry::countEditable()` returns N tokens
- WHEN the editor renders
- THEN the editor MUST state N editable tokens
