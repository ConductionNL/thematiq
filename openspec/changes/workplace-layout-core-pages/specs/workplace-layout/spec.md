# Spec: Workplace layout

## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: The Light Layout Draws The Login Page As A Workplace Card
While the layout is `light`, the login page MUST show Nextcloud's own guest logo (`#header.header-guest .logo`)
above the login card, 56px high, with the set's logo (`--nldesign-logo-url`, Nextcloud's `--image-logo`
when a set names none), and MUST NOT draw the in-card logo stamp. The card MUST be 420px wide at most,
MUST carry a 1px border in the login card edge token (the scheme's border when unset), the login card
corner token (the container radius when unset), the cards' shadow colour and 32px of padding. The title
MUST be 24px. The controls MUST be 44px high with a 1px edge. The two text actions under the form MUST be
14px, regular and underlined. The footer line MUST be muted text without a plate.

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
