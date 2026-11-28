# admin-settings

## MODIFIED Requirements

### Requirement: Template Response and Parameters
The settings form MUST return a `TemplateResponse` with all required parameters for the admin template.
@e2e exclude PHP controller internals (TemplateResponse parameters, default config values) — not testable via browser UI; covered by unit tests.

#### Scenario: Settings panel loads template with all parameters
- GIVEN the admin opens the NL Design settings panel
- WHEN `Admin::getForm()` is called
- THEN it MUST return a `TemplateResponse` for `settings/admin`
- AND the template parameters MUST include `tokenSets` (array of all available token sets from `TokenSetService::getAvailableTokenSets()`)
- AND the template parameters MUST include `currentTokenSet` (string, current active token set id, default `'nextcloud'`)
- AND the template parameters MUST include `hideSlogan` (boolean, from IConfig `hide_slogan` compared with `=== '1'`)
- AND the template parameters MUST include `topBarTitles` (boolean, the administrator's effective `menu.topBarTitles`)

#### Scenario: Token sets include design system metadata
- GIVEN `Admin::getForm()` retrieves token sets
- WHEN the `tokenSets` parameter is populated
- THEN each token set object MUST have `id`, `name`, `description`, and `design_system` fields
- AND token sets with theming metadata MUST include the `theming` object

#### Scenario: Default values for fresh installation
- GIVEN nldesign is freshly installed with no configuration
- WHEN `Admin::getForm()` is called
- THEN `currentTokenSet` MUST be `'nextcloud'`
- AND `hideSlogan` MUST be `false`
- AND `topBarTitles` MUST be `false`

### Requirement: Show Menu Labels Checkbox
The Menu card of Layout and options MUST have the switch "Show titles in the top bar"
(`role="switch"`, with its help text) in place of the former checkbox "Show text labels in app menu
(hide icons)". It MUST be checked from the administrator's effective `menu.topBarTitles`. Toggling it
MUST save at once through `POST /apps/thematiq/app-menu/defaults` with `{topBarTitles}` only, say
"Saved" in the page's polite live region, and on a failure say why in an alert and set the switch
back. The change MUST be listed among the changes in this session.

#### Scenario: Checkbox reflects enabled state
@e2e exclude the checked state of one switch from a stored value; proven by tests/vitest/adminLayoutTopBarTitles.spec.js (the switch is checked from the administrator's topBarTitles)
- GIVEN the administrator's `menu.topBarTitles` is true
- WHEN the settings panel loads
- THEN "Show titles in the top bar" MUST be checked

#### Scenario: Checkbox reflects disabled state
@e2e exclude the unchecked state of one switch from a stored value; proven by tests/vitest/adminLayoutTopBarTitles.spec.js (the switch is unchecked by default)
- GIVEN no `menu.topBarTitles` is stored
- WHEN the settings panel loads
- THEN "Show titles in the top bar" MUST NOT be checked

#### Scenario: Checkbox label text and accessibility
@e2e exclude the label and role of one switch; proven by tests/vitest/adminLayoutTopBarTitles.spec.js (the switch is labelled and has its help text)
- GIVEN the settings panel renders
- THEN the switch MUST read "Show titles in the top bar" (localized via `$l->t()`) with `role="switch"`
- AND its help text MUST be its description
- AND the former text "Show text labels in app menu (hide icons)" MUST NOT be on the page

#### Scenario: Checkbox change triggers API call
@e2e exclude the request a toggle sends; proven by tests/vitest/adminLayoutTopBarTitles.spec.js (a toggle saves topBarTitles through the menu defaults and says Saved)
- GIVEN the administrator toggles "Show titles in the top bar" on
- WHEN the change is saved
- THEN `POST /apps/thematiq/app-menu/defaults` MUST be called with `{topBarTitles: true}`
- AND the live region MUST say "Saved"
