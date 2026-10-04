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
