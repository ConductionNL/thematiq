# workplace-layout Specification

## Purpose
The workplace layout is the light layout option that draws Nextcloud the way the workplace designs show it: content as cards on a quiet surface and a top bar of its own. This capability covers the header style option, the workplace header that shows the signed-in person's name and role, and the standard apps (Dashboard, Files and Settings) drawn as cards on that surface. Each part is a layout option an administrator turns on, and none of it applies while the option is off.

## Requirements

### Requirement: The header style is a layout option
The app MUST store a header style in the app config under `header_style`: `default`, `workplace`
or the empty value for "follow the theme". `POST /settings/layout` MUST store it with the other
layout options, MUST refuse an unknown value with HTTP 400 and store nothing, and MUST audit a
change. A token set's `layout` block MAY name it; with nothing stored the set's value applies, and
a set that names none resolves to `default`. `css/header-workplace.css` MUST be emitted only while
the workplace layout resolves to `light` and the header style to `workplace`. The configuration
bundle MUST carry it as `config.layoutOptions.headerStyle`.

#### Scenario: Zuiddrecht wears the workplace bar
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php reads the shipped manifest
- GIVEN no stored choice and the active set `zuiddrecht`
- WHEN the stylesheets are resolved
- THEN `header-workplace` MUST be among them, after `workplace-layout`

#### Scenario: The workplace bar needs the light layout
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php and CssInjectionServiceTest.php
- GIVEN the header style `workplace`
- WHEN the workplace layout resolves to `default`
- THEN `header-workplace` MUST NOT be emitted

#### Scenario: Every other shipped set keeps Nextcloud's bar
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php walks every shipped set
- GIVEN no stored choice
- WHEN the header style is resolved for every shipped set except `zuiddrecht`
- THEN it MUST be `default`

### Requirement: The workplace header shows the name and the role
While `header-workplace` is emitted for a signed-in person, the page MUST carry the initial state
`thematiq/header-user` with the person's display name and the value of their profile's `role`
property (empty when none), and MUST load `js/header-user.js`. The script MUST write both as text,
MUST mark the label `aria-hidden`, MUST leave Nextcloud's menu button and its name in place, and
MUST show the name alone when the role is empty. With nobody signed in, nothing MUST be emitted. A
failure MUST be logged and MUST leave the bar with Nextcloud's avatar.

#### Scenario: The board's person
@e2e exclude Live-checked on :8080 with a role set through `occ user:profile`; unit: PHPUnit HeaderUserServiceTest and vitest tests/vitest/headerUser.spec.js
- GIVEN Pieter Jansen, whose profile role is "Woo-coördinator"
- WHEN a page renders with the workplace bar
- THEN the bar MUST show "PJ", "Pieter Jansen" and "Woo-coördinator"

#### Scenario: No role
@e2e exclude Unit: vitest tests/vitest/headerUser.spec.js
- GIVEN a person without a profile role
- WHEN the bar renders
- THEN it MUST show the initials and the name only

### Requirement: The Light Layout Draws The Standard Apps As Cards On The Surface
While the layout is `light`, the dashboard's panels MUST be cards: the container radius, a 1px border
in the scheme's border colour, the cards' shadow colour, and a title in the card title size
(`--nldesign-component-heading-3-font-size`). The Files list MUST be one card on the surface: its
header and footer rows and the README block MUST take the card's background, its header labels MUST
be 14px, semibold and muted. The settings sections, the personal settings block and the profile
section MUST be cards. The theme picker's token set select MUST be 44px high.

#### Scenario: The dashboard's panels are cards
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the dashboard panel rules are read
- THEN the panel MUST carry the container radius, a 1px border and the card shadow colour, and its title the card title size

#### Scenario: The Files list is one card
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the Files list rules are read
- THEN the list MUST carry the main background, a 1px border and the container radius, its header and footer rows the main background, and its header labels 14px

#### Scenario: Settings sections are cards
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the settings rules are read
- THEN every section MUST carry the main background, a 1px border and the container radius

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
and its top bar rule MUST leave the login page alone. The stylesheet MUST write no colour literal:
every colour MUST come from a Nextcloud scheme variable or a token.

#### Scenario: The default layout loads nothing
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php asserts the emitted links
- GIVEN the resolved layout `default`
- WHEN a page is rendered
- THEN `workplace-layout` MUST NOT be emitted

#### Scenario: The light layout uses the scheme's own colours
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the stylesheet
- GIVEN `css/workplace-layout.css`
- WHEN its declarations are read
- THEN the bar and its text MUST read Nextcloud's main background and main text variables, and no rule MUST carry a colour literal

### Requirement: The Light Layout May Draw A Login Watermark
While the layout is `light`, the login page MUST draw the image a set names in
`--nldesign-login-watermark-image` as a large, faint mark in the bottom corner, behind the login
card, at the opacity in `--nldesign-login-watermark-opacity` (0.07 when unset). A set that names no
image MUST get nothing drawn. A set that ships `img/logos/<set>-emblem-grey.svg` MUST get that
file's absolute url in the logo layer, and no other set's logo layer MUST change.

#### Scenario: A set without a watermark draws nothing
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rule and its fallback
- GIVEN the light layout and a set that declares no watermark image
- WHEN the watermark rule is resolved
- THEN its background image MUST be `none`

#### Scenario: Zuiddrecht draws its grey shield
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/SetLogoReachTest.php asserts the logo layer with and without the emblem file
- GIVEN the set `zuiddrecht`, which ships `img/logos/zuiddrecht-emblem-grey.svg`
- WHEN its logo layer is built
- THEN the layer MUST declare `--nldesign-login-watermark-image` with the absolute url of that file

### Requirement: The Light Layout Draws The Login Page As A Workplace Card
While the layout is `light`, the login page MUST show Nextcloud's own guest logo (`#header.header-guest .logo`)
above the login card, 56px high, with the set's logo (`--nldesign-logo-url`, Nextcloud's `--image-logo`
when a set names none), and MUST NOT draw the in-card logo stamp. The card MUST be 420px wide at most,
MUST carry a 1px border in the login card edge token (the scheme's border when unset), the login card
corner token (the container radius when unset), the cards' shadow colour and 32px of padding. The title
MUST be 24px. The controls MUST be 44px high with a 1px edge. The two text actions under the form MUST be
14px, regular, underlined and in the link colour (`--nldesign-color-link`). The footer line MUST be muted text without a plate.

#### Scenario: The logo moves above the card
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the guest logo rule and the stamp rule are read
- THEN the guest logo MUST be shown at 56px with the set's logo and the stamp MUST be hidden

#### Scenario: The card is a workplace card
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the login card rule is read
- THEN it MUST be at most 420px wide with a 1px border, the container radius and the card shadow colour

### Requirement: A Guest Page Action Keeps Its Label
While the layout is `light`, the "Back to …" action of a guest page (`a.button.primary` inside
`.body-login-container`) MUST take the login button label colour, and the rule MUST outrank the NL
Design link rule `a:not(#header a)…` (one id and three classes at least).

#### Scenario: The label reads on the button
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rule and its specificity
- GIVEN `css/workplace-layout.css`
- WHEN the guest action rule is read
- THEN its colour MUST be the login button label colour and every selector MUST carry one id and three classes

### Requirement: The Light Layout Draws The Top Bar Of The Boards
While the layout is `light`, the top bar MUST be 68px high through `--header-height` on the body, the
app grid button MUST be a 40px square on the workspace colour with the controls' radius, said through
`--button-size`, and the search field MUST be a 44px pill on the workspace colour with a 1px border in
the scheme's border colour and a regular-weight muted label. The dashboard's panel row MUST stay
transparent.

#### Scenario: The bar, the grid button and the search field
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the top bar rules are read
- THEN the body MUST set `--header-height` to 68px, the grid button MUST be 40px square and the search field MUST be a 44px pill with a muted regular label

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
