# Spec: Brand stripe

An optional stripe of three colours along the bottom edge of the Nextcloud top bar.

## ADDED Requirements

### Requirement: The Brand Stripe Is An Admin Option
The app MUST store the choice in the app config key `brand_stripe`. The value MUST be `1`, `0`,
or empty for "follow the theme". `POST /settings/layout` MUST store it together with the
workplace layout. The stripe MUST be off for every set that does not turn it on.

#### Scenario: The choice is stored
@e2e exclude Controller logic: PHPUnit tests/Unit/Controller/LayoutControllerTest.php
- GIVEN an administrator
- WHEN `POST /settings/layout` is called with `brandStripe=1`
- THEN `brand_stripe` MUST be stored as `1`

### Requirement: The Stripe Is One Conditional Stylesheet
`CssInjectionService` MUST emit `css/brand-stripe.css` only while the stripe resolves to on. The
stylesheet MUST draw the stripe on the top bar, MUST NOT take pointer events and MUST NOT change
the height of the bar.

#### Scenario: Off loads nothing
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php asserts the emitted links
- GIVEN the stripe resolves to off
- WHEN a page is rendered
- THEN `brand-stripe` MUST NOT be emitted

### Requirement: Colours And Ratio Come From Tokens
The stripe MUST read `--nldesign-brand-stripe-color-1`, `-color-2` and `-color-3`, the unitless
`--nldesign-brand-stripe-ratio-1`, `-ratio-2` and `-ratio-3`, and `--nldesign-brand-stripe-height`.
A set that declares none MUST get three equal bands in the primary colours, 4px high.

#### Scenario: Zuiddrecht draws six, three, one
@e2e exclude Computed from the files: tests/vitest/workplaceLayout.spec.js resolves the stops from the set's tokens
- GIVEN the `zuiddrecht` tokens
- WHEN the stops are resolved
- THEN red MUST run to 60%, blue to 90% and red to the end, 5px high

### Requirement: The Stripe Is Drawn Where A Design System Switches Pseudo-Elements Off
The NL Design and La Suite stylesheets disable the top bar's pseudo-elements with
`content: none !important` and `display: none !important`. The stripe rule MUST declare `content`
and `display` with `!important` at a specificity that is not lower, so the stripe is drawn.

#### Scenario: The stripe outranks the reset
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js compares the stripe rule with every reset of the header pseudo-element in css/systems
- GIVEN every rule under `css/systems/` that sets `content: none` on `#header::after`
- WHEN the stripe rule is read
- THEN it MUST declare `content: ''` and `display: block`, both `!important`
- AND its specificity MUST be at least that of each reset

### Requirement: The Login Card Carries The Stripe
While the stripe is on, the login page MUST draw it along the top edge of the login card, from
the same tokens.

#### Scenario: One set of tokens, two places
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the selectors that share the stripe declarations
- GIVEN `css/brand-stripe.css`
- WHEN its rules are read
- THEN the header stripe and the login card stripe MUST share one rule for the colours, the stops and the height
- AND the login card stripe MUST sit at `top: 0`

### Requirement: The Component Library Gets The Same Tokens
While the stripe is on, the stylesheet MUST set `--cn-brand-stripe-color-1`, `-color-2`, `-color-3`,
`--cn-brand-stripe-ratio-1`, `-ratio-2`, `-ratio-3` and `--cn-brand-stripe-height` on `:root`, each
from its `--nldesign-brand-stripe-*` token with the fallback the top bar stripe uses, so the
library's stripe component draws the same bands.

#### Scenario: A portal header matches the top bar
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the `:root` rule of css/brand-stripe.css
- GIVEN `css/brand-stripe.css`
- WHEN its `:root` rule is read
- THEN it MUST declare the seven `--cn-brand-stripe-*` properties
- AND each MUST read the matching `--nldesign-brand-stripe-*` token
