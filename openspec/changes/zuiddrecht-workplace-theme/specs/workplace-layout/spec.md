# Spec: Workplace layout

A layout option any theme can turn on: the light workplace, with a top bar on the main background.

## ADDED Requirements

### Requirement: The Workplace Layout Is An Admin Option
The app MUST store the choice in the app config key `workplace_layout`. The value MUST be
`default`, `light`, or empty for "follow the theme". `POST /settings/layout` MUST store it, MUST
be admin only, MUST reject any other value with HTTP 400 and MUST write an audit entry.

#### Scenario: The choice is stored
@e2e exclude Controller logic: PHPUnit tests/Unit/Controller/LayoutControllerTest.php asserts the stored value and the audit entry
- GIVEN an administrator
- WHEN `POST /settings/layout` is called with `workplaceLayout=light`
- THEN `workplace_layout` MUST be stored as `light`
- AND the response MUST carry the resolved layout

#### Scenario: An unknown layout is refused
@e2e exclude Controller logic: PHPUnit tests/Unit/Controller/LayoutControllerTest.php
- GIVEN an administrator
- WHEN `POST /settings/layout` is called with `workplaceLayout=wide`
- THEN the response MUST be HTTP 400 and nothing MUST be stored

### Requirement: A Token Set May Carry Layout Defaults
A `token-sets.json` entry MAY carry a `layout` block with `workplace_layout` and `brand_stripe`.
While the administrator's choice is empty, the option MUST follow the token set the page renders
with. A set without the block MUST resolve to `default` and to no stripe. A stored choice MUST
always win over the set.

#### Scenario: Zuiddrecht wears the light layout
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php reads the shipped manifest
- GIVEN no stored choice and the active set `zuiddrecht`
- WHEN the layout is resolved
- THEN it MUST be `light`

#### Scenario: Every other shipped set stays as it was
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php walks every shipped set
- GIVEN no stored choice
- WHEN the layout and the stripe are resolved for every shipped set except `zuiddrecht`
- THEN the layout MUST be `default` and the stripe MUST be off

#### Scenario: The administrator switches it off
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php
- GIVEN the stored choice `default` and the active set `zuiddrecht`
- WHEN the layout is resolved
- THEN it MUST be `default`

### Requirement: The Light Layout Is One Conditional Stylesheet
`CssInjectionService` MUST emit `css/workplace-layout.css` after the set layers and the saved
overrides, and only while the resolved layout is `light`. The stylesheet MUST paint the top bar
with the main background and its text with the main text colour, MUST follow the colour scheme,
and MUST leave the login page alone.

#### Scenario: The default layout loads nothing
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php asserts the emitted links
- GIVEN the resolved layout `default`
- WHEN a page is rendered
- THEN `workplace-layout` MUST NOT be emitted

#### Scenario: The light layout uses the scheme's own colours
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the stylesheet
- GIVEN `css/workplace-layout.css`
- WHEN its declarations are read
- THEN the bar and its text MUST read Nextcloud's main background and main text variables, and no colour literal
