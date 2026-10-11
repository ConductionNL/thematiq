# Spec: Brand stripe

## ADDED Requirements

### Requirement: Where The Stripe Is Drawn Is An Admin Option
The app MUST store the placement in the app config key `brand_stripe_placement`:
`header-and-login`, `header`, `login`, or empty for "follow the theme". It MUST only matter while
the stripe is on. `header-and-login` MUST be what a set that names nothing gets. The stripe MUST
keep its one shared rule; a placement other than both MUST load one further stylesheet after it
that takes the other copy off, on the exact selector the shared rule uses, with `content: none`
and `display: none` both `!important`.

#### Scenario: The top bar only
@e2e exclude Static file check: tests/vitest/layoutOptions.spec.js compares the selector with the shared rule
- GIVEN `css/brand-stripe-header-only.css`
- WHEN it is read
- THEN it MUST hold one rule on the login card's pseudo-element with `content: none !important`

#### Scenario: The login card only
@e2e exclude Static file check: tests/vitest/layoutOptions.spec.js compares the selector with the shared rule
- GIVEN `css/brand-stripe-login-only.css`
- WHEN it is read
- THEN it MUST hold one rule on the top bar's pseudo-element with `content: none !important`

#### Scenario: A stored placement wins over the set
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php
- GIVEN the stored placement `login` and the active set `zuiddrecht`
- WHEN the stylesheets are resolved
- THEN they MUST include `brand-stripe` followed by `brand-stripe-login-only`
