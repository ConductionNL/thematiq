# Spec: Workplace layout

## ADDED Requirements

### Requirement: The Navigation Width Is An Admin Option
The app MUST store four further layout choices in the app config: `navigation_width` (a whole
number of pixels from 200 to 480), `navigation_active_style` (`default` or `soft`),
`brand_stripe_placement` (`header-and-login`, `header` or `login`) and `login_watermark` (`1` or
`0`), each also accepting the empty value for "follow the theme". `POST /settings/layout` MUST
store them together with the workplace layout and the brand stripe, MUST reject a request with any
value an option does not accept with HTTP 400 and store nothing, and MUST write an audit entry per
option that changed. The admin page MUST show each with the pattern the two older options use.

#### Scenario: The newer choices are stored and audited
@e2e exclude Controller logic: PHPUnit tests/Unit/Controller/LayoutControllerTest.php asserts the stored values and the audit entries
- GIVEN an administrator
- WHEN `POST /settings/layout` is called with `navigationWidth=300`, `navigationActiveStyle=soft`, `brandStripePlacement=login` and `loginWatermark=0`
- THEN each MUST be stored under its key
- AND the response MUST carry the resolved options

#### Scenario: A width outside the range is refused
@e2e exclude Controller logic: PHPUnit tests/Unit/Controller/LayoutControllerTest.php
- GIVEN an administrator
- WHEN `POST /settings/layout` is called with `navigationWidth=50` beside good values
- THEN the response MUST be HTTP 400 and nothing MUST be stored

### Requirement: A Token Set May Carry The Newer Layout Defaults
A `token-sets.json` entry's `layout` block MAY carry `navigation_width` (a number or a numeric
string), `navigation_active_style`, `brand_stripe_placement` and `login_watermark` (a boolean).
While the administrator's choice is empty, each option MUST follow the set. A set that names one
of them with a value the option does not accept, or does not name it, MUST resolve that option to
the behaviour every set had before it existed: Nextcloud's navigation width, the default entry,
the stripe in both places, the watermark drawn. A stored choice MUST always win over the set.

#### Scenario: Zuiddrecht names a 264px navigation and the soft entry
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php reads the shipped manifest
- GIVEN no stored choice and the active set `zuiddrecht`
- WHEN the options are resolved
- THEN the navigation width MUST be 264, the entry style `soft`, the placement `header-and-login` and the watermark on

#### Scenario: Every other shipped set stays as it was
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php walks every shipped set
- GIVEN no stored choice
- WHEN the four options are resolved for every shipped set except `zuiddrecht`
- THEN the width MUST be Nextcloud's, the entry style `default`, the placement `header-and-login` and the watermark on
- AND no newer stylesheet and no inline width MUST be emitted

### Requirement: Each Newer Option Is One Conditional Stylesheet
`CssInjectionService` MUST emit, after the two older layout stylesheets and in this order:
`css/login-watermark-off.css` only while the layout is `light` and the watermark resolves to off;
`css/brand-stripe-header-only.css` or `css/brand-stripe-login-only.css` after `css/brand-stripe.css`
only while the stripe is on and its placement is `header` or `login`; `css/navigation-width.css`
only while a width resolves, followed by one inline `<style id="thematiq-navigation-width">`
declaring `--thematiq-navigation-width` on `:root`; `css/navigation-active-soft.css` only while
the entry style is `soft`. The width stylesheet MUST carry no width of its own. The soft style
MUST read the accent tint and the accent text, falling back to the primary tint and the primary,
and MUST write no colour literal. The admin page MUST add and drop each of them, and replace the
inline width, when a choice or the set changes.

#### Scenario: A set carrying every newer default loads each stylesheet and the inline width
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php asserts the emitted links in order
- GIVEN a set whose `layout` block names the light layout, the stripe, a 264px width, the soft entry, the `login` placement and the watermark off
- WHEN a page is rendered
- THEN the links MUST be `workplace-layout`, `login-watermark-off`, `brand-stripe`, `brand-stripe-login-only`, `navigation-width`, `navigation-active-soft`, then the inline width `264px`

#### Scenario: The width stylesheet reads the inline variable only
@e2e exclude Static file check: tests/vitest/layoutOptions.spec.js reads the stylesheet
- GIVEN `css/navigation-width.css`
- WHEN its declarations are read
- THEN every value MUST read `--thematiq-navigation-width` and none MUST be a pixel literal

#### Scenario: The soft entry is the accent on its tint, the primary otherwise
@e2e exclude Static file check: tests/vitest/layoutOptions.spec.js reads the stylesheet and the Zuiddrecht set
- GIVEN `css/navigation-active-soft.css`
- WHEN its rules are read
- THEN the entry's ground MUST be `--nldesign-color-accent-light` with the primary tint as fallback, its label `--nldesign-color-accent-text` in 600 with the primary as fallback, and no colour literal
- AND for `zuiddrecht` the pair MUST be #A30000 on #FCEDEC, 7.22:1

#### Scenario: The watermark switch takes the mark off the layout sheet's pseudo-element
@e2e exclude Static file check: tests/vitest/layoutOptions.spec.js compares the selectors
- GIVEN `css/login-watermark-off.css` and the watermark rule in `css/workplace-layout.css`
- WHEN both are read
- THEN the switch MUST address the same pseudo-element and declare `content: none` and `background-image: none`, both `!important`
