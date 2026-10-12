# Spec delta: action colour (action-colour-token)

A token set may give the main button a colour of its own, apart from its primary.

## ADDED Requirements

### Requirement: A token set may declare an action colour

A token set MAY declare `--nldesign-color-action`, `--nldesign-color-action-hover` and
`--nldesign-color-action-text` in its own file. In the NL Design System and La Suite bundles,
`--color-primary-element` MUST read `--nldesign-color-action`, `--color-primary-element-text` MUST
read `--nldesign-color-action-text`, and `--color-primary-element-hover` MUST read
`--nldesign-color-action-hover`, each before the value it read until now. The main button MUST be
painted in the same three values. A set that declares the action colour and no hover MUST get that
colour 15% darker as its hover, never the hover of its primary. `--color-primary`, the header,
links and the light tints MUST NOT follow the action colour. In a dark variant the action colour
MUST get a dark value, and the main button's dark colour MUST be derived from it.

#### Scenario: A set with a green main button
@e2e exclude Stylesheet cascade of a token no shipped set declares; covered by PHPUnit (ActionColourTokenTest) and the browser comparison in design.md
- GIVEN a token set with primary `#1a67a3` that declares `--nldesign-color-action: #1f883d`
- WHEN a page with a main button is drawn
- THEN the main button MUST be `#1f883d`
- AND a checked checkbox MUST be `#1f883d`
- AND the header and the links MUST stay `#1a67a3`

#### Scenario: The set declares no hover step
@e2e exclude Stylesheet cascade of a token no shipped set declares; covered by PHPUnit (ActionColourTokenTest, ShellRoleMapperTest) and the browser comparison in design.md
- GIVEN a token set that declares `--nldesign-color-action: #1f883d` and no `--nldesign-color-action-hover`
- WHEN the main button is hovered
- THEN its fill MUST be `#1f883d` mixed with 15% black
- AND MUST NOT be the hover colour of the primary

### Requirement: A set without an action colour renders as before

The action tokens MUST NOT be given a default value in any stylesheet. Every declaration that
reads one MUST name, as its fallback, the whole value it had before the token existed. No shipped
token set, dark variant or token override file MAY declare an action token in this change. For a
set that declares none, every Nextcloud variable, every component token and every generated dark
variant MUST be what it was.

#### Scenario: Every shipped set
@e2e exclude Regression over all 65 shipped sets, covered by PHPUnit (ActionColourTokenTest) and measured in a browser before and after (design.md, decision 5)
- GIVEN any token set this app ships
- WHEN its stylesheets are loaded, in light and in dark
- THEN `--color-primary-element`, `--color-primary-element-hover` and `--color-primary-element-text` MUST have the values they had before
- AND the main button MUST have the colours it had before

#### Scenario: A reader without a fallback
@e2e exclude Static rule over the stylesheets, covered by PHPUnit (ActionColourTokenTest)
- GIVEN a stylesheet that reads `var(--nldesign-color-action)` with no fallback
- WHEN the tests run
- THEN they MUST fail

### Requirement: The text on the action colour is checked

`--nldesign-color-action-text` on `--nldesign-color-action` MUST be checked against 4.5:1 wherever
the text on the primary is checked: on upload, on a multi-brand import, in the shipped set audit
and in the compliance report of the active configuration. A missing side MUST be read from its
primary counterpart. A pair that cannot be measured MUST be reported as unevaluated, never as a
pass. A set that declares no action token, or an action pair equal to its primary pair, MUST get no
additional warning.

#### Scenario: White text on a light green
@e2e exclude Validation branch, covered by PHPUnit (ActionColourTokenTest)
- GIVEN a token set with white text on its primary that declares `--nldesign-color-action: #7bd88f`
- WHEN the set is checked
- THEN a warning MUST name `--nldesign-color-action-text vs --nldesign-color-action` with a ratio below 4.5

#### Scenario: A set without the token
@e2e exclude Validation branch, covered by PHPUnit (ActionColourTokenTest)
- GIVEN any shipped token set
- WHEN the set is checked
- THEN no warning MUST name the action colour

### Requirement: The shell tokens answer the action colour

In `GET /apps/thematiq/api/shell-tokens`, `action` MUST be the administrator's own value of
`--color-primary-element`, else the active set's `--nldesign-color-action`, else its primary.
`action-deep` MUST be the administrator's own hover value, else the set's
`--nldesign-color-action-hover`, else, for a set with an action colour, that colour 15% darker,
else the hover of the primary. `primary`, `primary-deep` and `link` MUST NOT follow the action
colour. For a set without an action colour both roles MUST be what they were.

#### Scenario: The active set has an action colour
@e2e exclude Mapping rule, covered by PHPUnit (ShellRoleMapperTest)
- GIVEN the active set has primary `#1a67a3`, action `#1f883d` and action hover `#197032`
- WHEN a request is made to `GET /apps/thematiq/api/shell-tokens`
- THEN `tokens.action` MUST be `#1f883d` and `tokens.action-deep` MUST be `#197032`
- AND `tokens.primary` MUST be `#1a67a3`
