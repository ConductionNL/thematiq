# frankendesk-token-set Specification

## Purpose

La Frankendesk (`frankendesk`) is Conduction's own brand on the La Suite look. It runs on the
`lasuite` design system, keeps every value of the `lasuite` token set, and adds two things the
`lasuite` set may not or does not carry: the original La Frankendesk mark as its logo, and a
public-portal layer (footer, hero, cards, rhythm) for a portal that renders with the set.

This spec states what the set changes over its parent and why, how a page loads it, and the
contrast it must reach. It builds on `lasuite-stack` (the bundle), `token-sets` (the manifest),
`app-token-set-selection` (the logo layer) and `dark-mode` (the generated dark variant).

## Requirements

### Requirement: La Frankendesk is the lasuite set plus a declared delta

The `frankendesk` entry in `token-sets.json` MUST declare `design_system: "lasuite"` and
`extends: "lasuite"`. `css/tokens/frankendesk.css` MUST declare every custom property that
`css/tokens/lasuite.css` declares, with the same value, except the departures this requirement
lists. Its brand, text, surface, border and link colours MUST equal what
`css/systems/lasuite/bridge.css` derives from the Cunningham ramp for the plain `lasuite` pick, and
its primary MUST be La Suite violet `brand-650` (`#4844ad`), which is also the manifest's
`theming.primary_color`.

The one departure is the font stack. `--nldesign-font-family` and `--nldesign-body-font-family`
MUST start with `Inter` and MUST NOT name Marianne. Reason: the app ships no ungated
`@font-face` for Marianne (see `marianne-font`), so `Marianne, Inter, sans-serif` renders a third,
machine-dependent face wherever Marianne happens to be installed locally. Inter is self-hosted and
is what Cunningham falls back to anyway.

Status colours are not part of the parity with the ramp: the parent restates the values of
thematiq#1006 (`#d80000`, `#836703`, `#427816`, `#1167d4`), which the ramp's current status steps
no longer match. That drift belongs to `lasuite`; this set follows its parent.

#### Scenario: The set carries every lasuite token

- GIVEN `css/tokens/lasuite.css` and `css/tokens/frankendesk.css`
- WHEN every custom property of the parent is looked up in the set
- THEN each one MUST be declared there with the same value, except `--nldesign-font-family`
- AND on a page with `frankendesk` active, each of those properties MUST resolve on `:root` to the
  same colour or value as in the parent file

#### Scenario: The font stack is the one departure

- GIVEN `frankendesk` is the active token set
- WHEN `--nldesign-font-family` and `--nldesign-body-font-family` are resolved on `:root`
- THEN both MUST start with `Inter`
- AND neither MUST contain `Marianne`

### Requirement: The renderer loads La Frankendesk as one self-contained file

`extends` is lineage, not a load instruction. A page with `frankendesk` active MUST load the
`lasuite` design-system stylesheets in the order `design-systems.json` declares, then
`tokens/frankendesk`, then `token-overrides/frankendesk`, then `tokens/dark/frankendesk` when dark
variants are on. It MUST NOT load `tokens/lasuite`. Because only one token file loads, the set
file MUST be self-contained, which is why the parity requirement above exists and is tested.

#### Scenario: Selecting frankendesk loads the lasuite bundle and one token file

- GIVEN an administrator has applied the `frankendesk` token set
- WHEN an authenticated page renders
- THEN the Thematiq stylesheets MUST include, in this order, every `lasuite` bundle stylesheet,
  `tokens/frankendesk`, `token-overrides/frankendesk` and `tokens/dark/frankendesk`
- AND `tokens/lasuite` MUST NOT be loaded

### Requirement: The public-portal layer

The set MUST declare the surfaces a public portal reads and the parent does not: a dark footer
(`--nldesign-color-footer-background` `#1b1b23` with white `--nldesign-color-footer-text`), the hero
foregrounds (`--nldesign-hero-title-color`, `--nldesign-hero-body-color`) for a hero band painted
in the primary colour, and the `--frankendesk-*` geometry and colour tokens for chrome, cards,
rhythm and the footer's brand column, headings, links and legal bar. Card body copy MUST use
`--frankendesk-card-body-color` (`#6b6b80`, 5.20:1 on white) and not the parent's muted grey
`#75758a`, which measures 4.499:1 on white and fails AA by a thousandth. Spacing values MUST sit on
Cunningham's 8px grid. These tokens are inert inside Nextcloud: nothing in the Nextcloud shell
paints them, so they change nothing there.

#### Scenario: The delta declares the portal surfaces

- GIVEN `frankendesk` is the active token set
- WHEN the portal-layer tokens are resolved on `:root`
- THEN `--nldesign-color-footer-background` MUST resolve to a colour with relative luminance below
  0.05
- AND `--frankendesk-card-radius` MUST be `8px`
- AND `--frankendesk-card-body-color` MUST resolve to `#6b6b80`

### Requirement: The La Frankendesk logo replaces the Nextcloud mark

The set MUST ship `img/logos/frankendesk.svg`, named by `theming.logo` in the manifest and listed in
`img/ICONS.md`. It is Conduction's own original mark, not a La Suite or French-state logo, so the
`lasuite-stack` rule that keeps the `lasuite` set's logo slot empty does not apply to it.
`--nldesign-logo-url` in the token file MUST point at that file relative to `css/tokens/`.

On the header, `LogoLayerService` MUST find the shipped file and declare the absolute logo url plus
the three `--nldesign-header-logo-*` variables (#968), so the shared lasuite element overrides show
the image instead of the brand-coloured Nextcloud mask. `css/token-overrides/frankendesk.css`, which
loads after them, MUST also show the image with no mask and a transparent fill, because the mark is
two-tone and a single-colour mask would flatten it.

In dark mode the header MUST show `img/logos/frankendesk-dark.svg`, named by `theming.logo_dark`.
It is the light mark with only the stitches and neck bolts recoloured from `#111111` (1.03:1 on the
dark header `#141414`) to `#c8c8d0`, which reaches at least 3:1 there; every other shape and colour
is unchanged. The generated dark file names it as a relative url, which resolves against the
stylesheet that uses it and so breaks from `css/token-overrides/`, and `--nldesign-header-logo-image`
is substituted on `:root`. So `LogoLayerService::darkLayer()` MUST restate both
`--nldesign-logo-url` and `--nldesign-header-logo-image` as the absolute dark url, in both dark
scopes, in an inline layer emitted after the dark file. A set ships a dark logo as
`img/logos/<set>-dark.<ext>`, which is also what its `logo_dark` names.

#### Scenario: The header shows the La Frankendesk mark unmasked

- GIVEN `frankendesk` is the active token set
- WHEN an authenticated page renders
- THEN the computed `background-image` of `#header .logo` MUST be a url ending in
  `img/logos/frankendesk.svg`, and that url MUST be served as an SVG
- AND its computed `mask-image` MUST be `none`
- AND its computed `background-color` MUST be transparent

#### Scenario: The header shows the dark mark in dark mode

- GIVEN `frankendesk` is the active token set
- WHEN the page is in dark mode, by OS preference or by an explicit dark theme
- THEN the computed `background-image` of `#header .logo` MUST end in
  `img/logos/frankendesk-dark.svg`, and that url MUST be served as an SVG
- AND its computed `mask-image` MUST be `none`
- AND back in light mode it MUST end in `img/logos/frankendesk.svg` again

### Requirement: Every foreground of the set reaches WCAG AA in light and dark

Every foreground the set names MUST reach 4.5:1 on the surface it sits on, with a translucent
foreground composited over that surface first: body text and links (and link hover) on the page,
primary text on the primary fill, header text on the header, navigation links on the navigation,
the hero title and body on the primary fill, card headings and body on the card, and the footer
text, wordmark, brand line, headings, links, legal line, legal links and social icons on the footer.
This MUST hold in light mode and in both scopes of the generated dark variant: the
`@media (prefers-color-scheme: dark)` block and the explicit `body[data-themes*=dark]` block, which
MUST declare the same values.

In dark mode the footer MUST stay a dark band. The dark generator leaves a band whose surface is
already as dark as a dark page out of the dark variant (`DarkPaletteService::DARK_BANDS`), so the
footer keeps its light values, surface and text together; inverting it had produced a `#cbcbd6`
footer under a `#9e9e9e` label (1.67:1, thematiq#1021). The hero foregrounds and the semantic link
colour and its hover are repaired against their surfaces like the other control pairs.

#### Scenario: Light mode text reaches AA

- GIVEN `frankendesk` is the active token set and the page is in light mode
- WHEN each foreground/surface pair above is resolved on the page
- THEN each pair MUST measure at least 4.5:1

#### Scenario: Dark mode text reaches AA in both dark scopes

- GIVEN `frankendesk` is the active token set
- WHEN the page is in dark mode by OS preference, and again by an explicit dark theme
- THEN each foreground/surface pair above MUST measure at least 4.5:1 in both

#### Scenario: The footer stays a dark band in dark mode

- GIVEN `frankendesk` is the active token set and the page is in dark mode
- WHEN `--nldesign-color-footer-background` and `--nldesign-color-footer-text` are resolved on `body`
- THEN the footer background MUST have relative luminance below 0.05
- AND the footer text MUST measure at least 4.5:1 on it

## Known gaps

These are recorded, not required, and belong to other capabilities or need a decision:

- The parent's `--nldesign-color-text-muted` `#75758a` measures 4.499:1 on white (inherited from
  `lasuite`). This set keeps it for parity and uses `#6b6b80` for card body copy.
- The parent's status colours no longer match the ramp's status steps (see the first requirement).
- No public portal links the dark variant yet (portaliq `templates/site.php` explains why), so the
  dark values of the portal layer are reached only by a consumer that reads the tokens directly.
