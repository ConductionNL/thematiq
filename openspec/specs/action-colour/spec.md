# action-colour Specification

## Purpose
Bring an action colour in with an imported theme. Created by change `import-action-colour`, extended by `cunningham-action-green`, `action-colour-main-button-only` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: An imported theme may bring an action colour; The Cunningham set has a green main button; The action colour reaches the main button and nothing else; The shell tokens answer the main button, not the element colour.

## Requirements

### Requirement: An imported theme may bring an action colour

The converter MUST write `--nldesign-color-action` when a theme declares `--<prefix>-color-action`,
`--nldesign-color-action-hover` when it declares `--<prefix>-color-action-hover`, and
`--nldesign-color-action-text` when it declares `--<prefix>-color-action-text`. A design tokens
(DTCG) document MUST bring them by the paths `color.action`, `color.action-hover` and
`color.action-text` (or `color.on-action`). A theme that names an action colour and no text MUST get
white or black as its action text, by the rule that gives the text on the primary. A theme that
names no action colour MUST get none of the three tokens: the Utrecht primary-action button colour
MUST stay the source of the primary and MUST NOT become an action colour.

#### Scenario: A theme with a green main button
@e2e exclude Converter rule, covered by PHPUnit (TokenSetConverterServiceTest)
- GIVEN a theme that declares `--demo-color-primary: #1a67a3` and `--demo-color-action: #116329`
- WHEN it is converted
- THEN the set MUST declare `--nldesign-color-action: #116329`
- AND `--nldesign-color-action-text` MUST be `#ffffff`
- AND `--nldesign-color-primary` MUST stay `#1a67a3`

#### Scenario: A theme without an action colour
@e2e exclude Converter rule, covered by PHPUnit (TokenSetConverterServiceTest) and the converter fixtures
- GIVEN a theme that declares only the Utrecht primary-action button colours
- WHEN it is converted
- THEN the set MUST NOT declare an action token

#### Scenario: A design tokens file
@e2e exclude Mapper rule, covered by PHPUnit (DesignTokensMapperTest, TokenSetConverterServiceTest)
- GIVEN a DTCG document with `color.action` and `color.on-action`
- WHEN it is imported
- THEN the set MUST declare `--nldesign-color-action` and `--nldesign-color-action-text`

### Requirement: The Cunningham set has a green main button

The `cunningham` token set MUST declare `--nldesign-color-action: #1f883d`,
`--nldesign-color-action-hover: #197032` and `--nldesign-color-action-text: #ffffff`. The text MUST
reach 4.5:1 on the action colour. Its primary MUST stay `#1a509f`. In its dark variant the action
colour MUST keep its value. No other shipped set MAY declare an action colour without being named
beside it in `ActionColourTokenTest`.

#### Scenario: A Cunningham instance
@e2e exclude Token values of one set, covered by PHPUnit (ActionColourTokenTest, ShellCatalogueTest) and a browser comparison recorded in the pull request
- GIVEN the active token set is Cunningham
- WHEN a page with a main button and a checked checkbox is drawn
- THEN the main button MUST be `#1f883d`
- AND the checked checkbox, the header and the links MUST stay in the set's blue

#### Scenario: Every other set
@e2e exclude Regression over all shipped sets, covered by PHPUnit (ActionColourTokenTest)
- GIVEN any shipped token set other than Cunningham
- WHEN its stylesheets are loaded
- THEN it MUST declare no action colour

### Requirement: The action colour reaches the main button and nothing else

A set's action colour MUST be worn by the filled primary button: NcButton with the primary variant
(`.button-vue--primary`, `.button-vue--vue-primary`), `button.primary`, `input.primary`, the
confirm button of a dialog and the login button, at rest and on hover, with its label in the
action text. `--color-primary-element`, `--color-primary-element-hover` and
`--color-primary-element-text` MUST NOT read an action token, and MUST be what they were before the
action colour existed. A checked checkbox, a selected radio button, a switch that is on, a progress
bar, the selected navigation entry, a counter badge, a focused input, a folder icon and a link MUST
NOT change when a set declares an action colour. A bundle that does not read the action tokens
MUST NOT change at all.

#### Scenario: A set with a green main button
@e2e tests/e2e/spec-coverage/action-colour-unset.spec.ts
- GIVEN a token set that declares `--nldesign-color-action: #12ab34`
- WHEN a page is drawn
- THEN the primary button MUST be `#12ab34`
- AND a checked checkbox MUST keep the colour it had
- AND `--color-primary-element` MUST keep the value it had

#### Scenario: A set without an action colour
@e2e tests/e2e/spec-coverage/action-colour-unset.spec.ts
- GIVEN any shipped token set that declares no action colour
- WHEN its stylesheets are loaded, in light and in dark
- THEN every custom property and every button colour MUST be what it was

### Requirement: The shell tokens answer the main button, not the element colour

In the shell tokens, `action` MUST be the administrator's own value for the main button's fill
(`--nldesign-component-button-primary-action-background-color`), else the set's
`--nldesign-color-action`, else its primary. It MUST NOT follow an administrator's value for
`--color-primary-element`. `action-deep` MUST be the administrator's own hover value for the main
button, else the set's `--nldesign-color-action-hover`, else a main button colour of its own 15%
darker, else the hover of the primary.

#### Scenario: An administrator changed the element colour
@e2e exclude Mapping rule, covered by PHPUnit (ShellRoleMapperTest)
- GIVEN an administrator set `--color-primary-element` to `#aa0000`
- AND the active set has action colour `#1f883d`
- WHEN the shell tokens are read
- THEN `tokens.action` MUST be `#1f883d`
