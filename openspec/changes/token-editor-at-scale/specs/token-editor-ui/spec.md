## MODIFIED Requirements

### Requirement: Functional Tab Groups
The token editor MUST organize theme tokens into four functional tabs, and MUST list every component, internal and Conduction token in a group named after the component that owns it.

Tabs and their primary tokens:

**Login page & Branding**: `--color-primary`, `--color-primary-text`, `--color-primary-hover`, `--color-primary-light`, `--color-primary-light-hover`, `--color-primary-element`, `--color-primary-element-text`, `--color-primary-element-hover`, `--color-primary-element-light`, `--color-primary-element-light-text`, `--color-primary-element-light-hover`

**Content area**: `--color-background-hover`, `--color-background-dark`, `--color-background-darker`, `--color-border`, `--color-border-dark`, `--color-border-maxcontrast`, `--color-scrollbar`, `--color-placeholder-light`, `--color-placeholder-dark`, `--color-main-background`, `--color-box-shadow`, `--color-mark`, `--color-text-selection`, `--color-background-selection`, border radius tokens, animation timing tokens

**Buttons & Status**: `--color-error`, `--color-error-hover`, `--color-warning`, `--color-warning-hover`, `--color-success`, `--color-success-hover`, `--color-info`, `--color-info-hover`, `--color-favorite`, status RGB values, semantic element/border variants, the assistant colours

**Typography**: `--color-main-text`, `--color-text-maxcontrast`, `--color-text-light`, `--color-text-lighter`, `--color-text-error`, `--color-text-success`, `--color-text-warning`, `--font-face`, `--default-font-size`, `--font-size-small`, `--default-line-height`

Component groups follow the tabs, one per owning component, each under a translated heading. An Advanced group holds every token the registry flags `advanced`.

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

## ADDED Requirements

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
Rows for the 45 settable theme tokens and for internal colour tokens MUST offer an optional dark value, and brand rows MUST keep the dark value the overrides writer derives.

#### Scenario: Admin gives a dark value
- GIVEN the admin sets `--color-mark` to `#ffe08a` and its dark value to `#5c4a00`
- WHEN the admin saves and switches to Nextcloud's dark theme
- THEN a search highlight MUST show `#5c4a00`

#### Scenario: No dark value given
- GIVEN the admin sets `--color-mark` to `#ffe08a` and leaves the dark value empty
- WHEN the admin switches to Nextcloud's dark theme
- THEN a search highlight MUST show Nextcloud's own dark value

#### Scenario: Brand rows have no dark field
- GIVEN the "Login page & Branding" tab is open
- WHEN the `--color-primary` row renders
- THEN it MUST NOT offer a separate dark value

### Requirement: Groups render their rows when opened
The editor MUST NOT build the rows of a collapsed group until the admin opens it or a search matches it.

#### Scenario: Page load renders the open tab only
- GIVEN the registry holds about 790 tokens
- WHEN the settings page loads
- THEN only the rows of the open tab MUST be in the document

### Requirement: The editor states the registry's count
The editor MUST show the number of editable tokens the registry reports, and the user documentation MUST state the same number.

#### Scenario: The count matches the registry
- GIVEN `TokenRegistry::getTokens()` returns N tokens
- WHEN the editor renders
- THEN the editor MUST state N editable tokens
