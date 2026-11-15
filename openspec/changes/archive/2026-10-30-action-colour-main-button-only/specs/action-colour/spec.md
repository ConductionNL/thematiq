# Spec delta: action colour (action-colour-main-button-only)

## ADDED Requirements

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
