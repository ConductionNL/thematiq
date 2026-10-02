---
status: in-progress
reviewed_date: 2026-02-28
enriched_date: 2026-03-20
---

# CSS Architecture Specification

## Purpose
Defines the layered CSS architecture that transforms NL Design System tokens into Nextcloud-compatible theming.

The architecture uses a design-system-driven approach: `design-systems.json` declares ordered stylesheet bundles, and `CssInjectionService::inject()` loads the correct bundle for the active token set. Organization-specific tokens cascade correctly, incomplete token sets fall back gracefully, and NL Design System component tokens (using the `--utrecht-*` prefix) are bridged to the `--nldesign-*` namespace. The load order is critical: each layer builds on the previous one.

## Requirements

### Requirement: Design System Driven Stylesheet Loading

The app MUST resolve which design system a token set belongs to and load the corresponding
stylesheet bundle in declared order. The active token set for a request MUST be obtained
through `GroupThemingService::resolveTokenSetForRequest()` (precedence: admin preview → group
mapping → instance default `token_set`, per the `per-group-theming` spec) instead of reading
the `token_set` app value directly; with an empty group mapping and no active preview the
resolved set MUST be identical to the `token_set` app value, preserving prior behavior exactly.
Resolution affects ONLY which token set (and thus which design-system bundle) is chosen; the
layer order, the custom-overrides layer, and the conditional stylesheets are unchanged and
identical for all users.

#### Scenario: Standard CSS load order for nldesign design system

- GIVEN the nldesign app boots via `Application::boot()`
- AND the token set resolved for the request belongs to the `nldesign` design system
- WHEN `injectThemeCSS()` is called
- THEN the `DesignSystemService` MUST resolve the design system from `design-systems.json`
- AND CSS files MUST be loaded in the order declared in the design system's `stylesheets` array via `\OCP\Util::addStyle()`
- AND the standard nldesign order MUST be:
  1. `systems/nldesign/fonts` (Layer 1: @font-face declarations)
  2. `systems/nldesign/defaults` (Layer 2: all `--nldesign-*` token defaults)
  3. `systems/nldesign/utrecht-bridge` (Layer 4: `--utrecht-*` to `--nldesign-component-*` mapping)
  4. `systems/nldesign/theme` (Layer 5: `--nldesign-*` to Nextcloud element selectors)
  5. `systems/nldesign/overrides` (Layer 6: Nextcloud `--color-*` variable mappings)
  6. `systems/nldesign/element-overrides` (Layer 7: low-level element styling)
  7. `tokens/{resolvedTokenSet}` (Layer 3: organization overrides), emitted by `CssInjectionService::designSystemLayers()` directly after the last bundle stylesheet, not from the bundle
- AND the layer numbers MUST name each file's role, not its load position: the token file is Layer 3 because it overrides the Layer 2 defaults
- AND the token file MUST come after `systems/nldesign/defaults`, because both declare the same `--nldesign-*` properties on `:root` and the later declaration wins; the bridge, theme and override layers only read those properties through `var()`, which resolves against the final cascaded value, so they do not need to follow the token file
- AND the token file MUST stay out of the bundle's `stylesheets` array, because the bundle is shared by every token set on the design system while the token file is the set-dependent layer the admin panel swaps without a reload

#### Scenario: Stock Nextcloud design system loads no stylesheets

- GIVEN the token set resolved for the request has `design_system: "none"`
- WHEN `injectThemeCSS()` is called
- THEN the design system's `stylesheets` array MUST be empty
- AND no nldesign CSS files MUST be loaded for layers 1-7
- AND Nextcloud's default theming MUST remain untouched

#### Scenario: Token set CSS loaded after design system stylesheets

- GIVEN the design system stylesheets have been loaded
- AND the design system is not `"none"`
- WHEN the token set file is loaded
- THEN `tokens/{resolvedTokenSet}` MUST be loaded after all design system stylesheets
- AND before the custom-overrides layer

#### Scenario: Custom overrides always loaded last

- GIVEN all design system stylesheets and token set CSS are loaded
- WHEN `injectThemeCSS()` continues
- THEN `custom-overrides` MUST be loaded after all design system and token layers
- AND `CustomOverridesService::ensureExists()` MUST be called before loading
- AND custom overrides MUST override all previous layers in the cascade
- AND the custom-overrides layer MUST be the same instance-global file for every user,
  whichever token set was resolved for the request

#### Scenario: Conditional CSS loading

- GIVEN the hide_slogan setting is enabled (value `'1'`)
- WHEN `injectThemeCSS()` is called
- THEN `hide-slogan` CSS MUST be loaded after all core and custom-override layers
- AND if show_menu_labels is also enabled, `show-menu-labels` CSS MUST also be loaded
- AND the conditional stylesheets MUST be instance-global (not per-group)

#### Scenario: Empty mapping preserves legacy resolution byte-for-byte

@e2e exclude the GIVEN is an absent or empty group mapping, which a shared instance cannot guarantee; PHPUnit tests/Unit/Service/GroupThemingServiceTest.php::testResolveReturnsDefaultForEmptyMappingWithoutGroupOrCacheAccess asserts the resolved id equals the token_set app value
- GIVEN `group_token_sets` is absent or an empty array and no preview is active
- WHEN any request resolves its token set
- THEN the resolved id MUST equal the `token_set` app value (default `nextcloud`)
- AND the set of stylesheets injected MUST be identical to the pre-change behavior

### Requirement: Layer 1 -- Font Declarations
The fonts layer MUST declare Fira Sans @font-face rules for all required weights and styles.

#### Scenario: Fira Sans font faces registered
- GIVEN the `css/systems/nldesign/fonts.css` file is loaded
- WHEN the browser processes the @font-face rules
- THEN it MUST register `'Fira Sans'` at weight 400 normal
- AND it MUST register `'Fira Sans'` at weight 400 italic
- AND it MUST register `'Fira Sans'` at weight 700 normal
- AND it MUST register `'Fira Sans'` at weight 700 italic
- AND each @font-face MUST use `font-display: swap` for performance

#### Scenario: Font file formats supported
- GIVEN each @font-face declaration
- WHEN the `src` descriptor is processed
- THEN it MUST specify `local()` first (for system-installed fonts)
- AND it MUST specify woff2 format as the primary web font
- AND it MUST specify woff format as fallback
- AND font files MUST be in the `css/systems/nldesign/fonts/` directory

#### Scenario: Font licensing compliance
@e2e exclude licensing is a property of the distributed files, not of a rendered page; vitest tests/vitest/fontLicences.spec.js asserts LICENSES/OFL-1.1.txt, an OFL.txt naming each holder in every font directory, and an OFL-1.1 REUSE.toml annotation for every font file
- GIVEN Fira Sans is used as the app's primary font
- WHEN the font is distributed
- THEN it MUST comply with the SIL Open Font License 1.1
- AND every font directory that ships Fira Sans (`css/systems/nldesign/fonts/`, `css/fonts/`) MUST carry an `OFL.txt` with the copyright notice and the licence text, because the OFL requires both to travel with the fonts
- AND `REUSE.toml` MUST label the font files `OFL-1.1` with their upstream copyright holder, overriding the EUPL-1.2 blanket, and `LICENSES/OFL-1.1.txt` MUST hold the licence text
- AND the font MUST be a suitable open-source alternative to RijksoverheidSansWebText

### Requirement: Layer 2 -- Default Token Definitions
The defaults layer MUST define ALL `--nldesign-*` tokens on `:root` with Rijkshuisstijl-based values as the foundation for all theming.

#### Scenario: Brand color tokens defined
- GIVEN the `css/systems/nldesign/defaults.css` file is loaded
- WHEN the `:root` rule is processed
- THEN it MUST define `--nldesign-color-primary: #154273` (Rijkshuisstijl blue)
- AND `--nldesign-color-primary-text: #ffffff`
- AND `--nldesign-color-primary-hover: #1d5499`
- AND `--nldesign-color-primary-light: #e8f0f8`
- AND `--nldesign-color-primary-light-hover: #d4e4f2`

#### Scenario: Status color tokens defined
- GIVEN the defaults CSS is loaded
- WHEN the `:root` rule is processed
- THEN it MUST define error (`#d52b1e`), warning (`#e17000`), success (`#39870c`), and info (`#007bc7`) colors
- AND each status color MUST also have an `-rgb` variant for use in rgba() expressions

#### Scenario: All token categories defined
- GIVEN the defaults CSS is loaded
- THEN it MUST define tokens for: brand colors, status colors, background colors (hover, dark, darker, header, nav), text colors (text, text-muted, text-light), border colors, focus colors, link colors, button colors, typography (font-family), border-radius (default, small, large, rounded, pill), animation timing, placeholder colors, and logo/lint variables

#### Scenario: Component tokens defined
- GIVEN the defaults CSS is loaded
- THEN it MUST define `--nldesign-component-*` tokens for: button (base, hover, active, disabled, focus, primary-action, secondary-action), textbox (base, states), form field/select/fieldset, headings (h1-h6 with font-size, font-weight, line-height, color), paragraph, link, table, badge, separator, and ordered/unordered lists

#### Scenario: Defaults serve as fallback for incomplete token sets
- GIVEN an incomplete token set is loaded in Layer 3
- AND that token set does NOT define `--nldesign-color-error`
- WHEN the error color is used in Layers 5-7
- THEN it MUST resolve to the Rijkshuisstijl default `#d52b1e` from Layer 2
- AND no visual errors or missing styles MUST occur

### Requirement: Layer 3 -- Organization Token Overrides
Token set CSS files MUST override `--nldesign-*` variables on `:root` for organization-specific values.

#### Scenario: Organization colors applied
- GIVEN the active token set is `amsterdam`
- AND `css/tokens/amsterdam.css` defines `--nldesign-color-primary: #004699`
- WHEN the CSS cascade resolves `--nldesign-color-primary`
- THEN the resolved value MUST be `#004699` (Amsterdam blue)
- AND all variables in Layers 4-7 referencing `--nldesign-color-primary` MUST use this value

#### Scenario: Rijkshuisstijl lint tokens
- GIVEN the active token set is `rijkshuisstijl`
- AND it defines `--nldesign-color-logo-background: #154273`, `--nldesign-size-lint: 48px`, `--nldesign-size-lint-height: 96px`
- WHEN the header renders
- THEN a colored lint/ribbon MUST appear behind the logo

#### Scenario: Non-lint theme (no logo background)
- GIVEN the active token set does NOT define `--nldesign-color-logo-background`
- WHEN the header renders
- THEN the lint pseudo-element MUST be invisible (0px width, transparent background from defaults)
- AND the logo MUST display in its natural colors without a filter

#### Scenario: Token set only overrides `:root` scope
- GIVEN a token set CSS file is loaded
- WHEN it declares CSS custom properties
- THEN all declarations MUST be on the `:root` selector
- AND no element-level selectors MUST be present in token set files
- AND this ensures clean override semantics with Layer 2

### Requirement: Layer 4 -- Utrecht Bridge Mapping
The Utrecht bridge MUST map `--utrecht-*` component tokens to `--nldesign-component-*` tokens with fallback to Layer 2 defaults.

#### Scenario: Utrecht token present in token set
- GIVEN a token set defines `--utrecht-button-primary-action-background-color: #123456`
- WHEN Layer 4 processes the bridge mapping
- THEN `--nldesign-component-button-primary-action-background-color` MUST resolve to `#123456`

#### Scenario: Utrecht token absent (fallback to defaults)
- GIVEN a token set does NOT define any `--utrecht-*` button tokens
- WHEN Layer 4 processes the bridge mapping
- THEN `--nldesign-component-button-primary-action-background-color` MUST fall back to `var(--nldesign-color-primary)` from Layer 2 defaults

#### Scenario: No circular references
- GIVEN the bridge CSS uses `var()` with fallback values
- WHEN fallback values are specified
- THEN fallback values MUST NOT self-reference (e.g. `var(--nldesign-foo, var(--nldesign-foo))` is forbidden)
- AND fallback values MUST reference either a concrete value or a variable defined in Layer 2

#### Scenario: Component categories bridged
- GIVEN the bridge CSS is loaded
- THEN it MUST map `--utrecht-*` tokens for: button (base, hover, active, disabled, focus, primary-action, secondary-action), textbox, form field/select, headings (h1-h6), paragraph, link, table, badge, separator, lists, breadcrumb, and code

#### Scenario: Bridge is a temporary layer
- GIVEN the utrecht-bridge layer exists
- WHEN upstream NL Design System alignment is achieved
- THEN the bridge MUST be removable without affecting other layers
- AND components MUST natively use `--nldesign-component-*` tokens after alignment

### Requirement: Layer 5 -- Theme Element Mapping
The theme layer MUST apply `--nldesign-*` tokens to Nextcloud element selectors and override Nextcloud CSS variables at high specificity.

#### Scenario: Nextcloud CSS variables overridden on body
- GIVEN Layer 5 (`css/systems/nldesign/theme.css`) is loaded
- WHEN the `body` and `body[data-themes]` rules are processed
- THEN `--color-primary` MUST be set to `var(--nldesign-color-primary) !important`
- AND `--color-primary-text` MUST be set to `var(--nldesign-color-primary-text) !important`
- AND status colors (error, warning, success, info) MUST be mapped
- AND border-radius variables MUST be mapped

#### Scenario: Header styled from tokens
- GIVEN Layer 5 is loaded
- WHEN the `#header` element renders
- THEN background MUST use `var(--nldesign-color-header-background)`
- AND text color MUST use `var(--nldesign-color-header-text)`
- AND the header MUST have `overflow: visible` (for lint bar to hang below)

#### Scenario: Login page styled with government branding
- GIVEN the user is on the login page (`#body-login`)
- WHEN Layer 5 styles are applied
- THEN the original Nextcloud header MUST be hidden (`display: none`)
- AND the guest-box MUST have a white background with no shadows
- AND the lint/ribbon pseudo-elements MUST render on the login box
- AND primary buttons MUST use `--nldesign-component-button-primary-action-*` tokens

#### Scenario: Focus states for accessibility
- GIVEN any interactive element receives keyboard focus
- WHEN `:focus-visible` is triggered
- THEN the element MUST show a 2px solid outline using `var(--nldesign-color-focus)`
- AND the outline offset MUST be 2px
- AND this MUST satisfy WCAG 2.1 AA SC 2.4.7 (Focus Visible)

### Requirement: Layer 6 -- Nextcloud Variable Overrides
The overrides layer MUST map Nextcloud `--color-*` CSS variables to `--nldesign-*` tokens on `:root`, while preserving dark mode compatibility.

#### Scenario: Primary color variables mapped
- GIVEN Layer 6 (`css/systems/nldesign/overrides.css`) is loaded
- WHEN the `:root` rule is processed
- THEN all primary-related Nextcloud variables (--color-primary, --color-primary-text, --color-primary-hover, --color-primary-element, etc.) MUST be mapped to corresponding `--nldesign-*` tokens with `!important`

#### Scenario: Main background intentionally NOT overridden
- GIVEN Layer 6 is loaded
- WHEN the `:root` rule is processed
- THEN `--color-main-background` MUST NOT be overridden
- AND `--color-main-background-rgb` MUST NOT be overridden
- AND `--color-main-background-translucent` MUST NOT be overridden
- AND `--color-background-plain` MUST NOT be overridden
- AND each intentionally-unset variable MUST have a comment explaining why

#### Scenario: Dark mode compatibility preserved
- GIVEN a user has Nextcloud dark mode enabled
- WHEN the nldesign overrides are applied
- THEN `--background-invert-if-dark` MUST NOT be overridden
- AND `--background-invert-if-bright` MUST NOT be overridden
- AND the dark mode auto-calculated variables MUST continue to function

#### Scenario: Typography variable mapped
- GIVEN Layer 6 is loaded
- THEN `--font-face` MUST be mapped to `var(--nldesign-font-family) !important`
- AND the Fira Sans font from Layer 1 MUST be the resolved value

### Requirement: Layer 7 -- Element-Level Overrides
The element-overrides layer MUST apply NL Design styling to specific HTML elements and Nextcloud components.

#### Scenario: Font family forced on all elements
@e2e exclude browser-observable (computed font-family on a button, input, textarea, select and label, and on an icon-font glyph that must keep its own), but no browser test asserts it yet; the test is owed under #897
- GIVEN Layer 5 (`css/systems/nldesign/theme.css`) sets `font-family: var(--nldesign-font-family)` on `body`, `#body-user`, `#body-login`, `#body-public`, `#app`, `#content` and `.app-content`, and every other element inherits it
- AND Layer 7 (`css/systems/nldesign/element-overrides.css`) is loaded
- WHEN the browser resolves the font of a `button`, `input`, `textarea`, `select` or `label`
- THEN Layer 7 MUST set `font-family: var(--nldesign-font-family)` on exactly those five element types, without `!important`, because form controls take their font from the browser's own stylesheet instead of inheriting it
- AND no layer MUST set `font-family` on a universal or wildcard descendant selector (`*`, `html body *`, `#body-user *`, `#app *`, `#content *`), because that clobbers icon fonts, monospace code editors and any component that declares its own font (ADR-CSS-001)

#### Scenario: Header icons visible on themed background
@e2e exclude browser-observable (computed color and filter on a header-end svg, and the avatar keeping its own colour), but no browser test asserts it yet; the test is owed under #897
- GIVEN the header has a white or light background from the token set (Rijkshuisstijl, Amsterdam and Cunningham paint it `#ffffff`), while Nextcloud ships every header glyph white
- WHEN Layer 7 is loaded
- THEN the header glyphs (`#header .header-end svg`, `.button-vue__icon`, `.icon-vue`, `.unified-search__button`, the same glyphs in `.header-start`, and `.app-menu__waffle`) MUST take `color: var(--nldesign-component-header-color, var(--nldesign-color-header-text))`, which their `currentColor` fill follows
- AND those glyphs MUST carry `filter: none`, not `filter: invert(1) brightness(0) contrast(100)`: that filter forced every glyph to pure black whatever the header text token said, and because a filter rasterises its whole subtree it flattened the avatar inside the user menu trigger to a black square that no descendant `filter: none` could undo
- AND the avatar (`.avatardiv`, `[class*='avatar' i]`) and the user-status icon MUST be excluded from the forced glyph fill and MUST carry `filter: none`, so the avatar keeps its generated colour or photo and the status badge its own status colour

#### Scenario: App navigation styled as card
@e2e exclude browser-observable (computed background and a 0px margin-right on #app-navigation, and no gap between it and #app-content), but no browser test asserts it yet; the test is owed under #897
- GIVEN the app navigation sidebar renders inside `#content`, which is `display: flex`, clips its children to `--body-container-radius` and has no background of its own
- WHEN Layer 7 styles are applied
- THEN `#app-navigation`, `.app-navigation` and `#app-navigation-vue` MUST use `var(--color-main-background)` as background
- AND Layer 7 MUST NOT set a margin or a border-radius on them, so the navigation and the app content sit flush as two panels that `#content` clips into one rounded container
- AND the former 30px right margin MUST NOT return: it opened empty flex space inside `#content`, and the page background showed through it as a vertical strip between the menu and the content

#### Scenario: App-specific exclusions
@e2e exclude browser-observable (computed color of a span, div and link inside a .tile-widget against the same element outside one), but no shipped page renders a .tile-widget, so a test needs a fixture element; the test is owed under #897
- GIVEN Layer 7 forces `color: var(--nldesign-color-on-surface, var(--nldesign-color-text))` with `!important` onto `body`, `#app`, `#content`, `.app-content`, `p`, `span`, `div`, `li` and `a`, and the link colour onto `a`
- WHEN an element renders that is a `.tile-widget` or sits inside one
- THEN its `span`, `div` and `a` elements MUST be excluded from both rules, so the tile keeps the colours its app paints
- AND Layer 7 MUST NOT add other per-app `:not()` exclusions: there is no `.launchpad-widget` rule, and widgets are not excluded from the solid background rule, which paints every panel and widget with `var(--color-main-background)`
- AND a new surface that needs its own foreground MUST opt out by setting `--nldesign-color-on-surface` on itself, because a `:not()` list only covers the surfaces somebody remembered, while a custom property reaches every descendant without competing on specificity

### Requirement: Custom Overrides Layer (Layer 8)

An 8th layer MUST load admin-defined CSS overrides that always win over all design system and
token layers.

#### Scenario: Custom overrides file loaded
- GIVEN `CssInjectionService::inject()` runs for a themed render context
- WHEN all design system and token set CSS has been loaded
- THEN `CustomOverridesService::ensureExists()` MUST be called to create the file if missing
- AND `custom-overrides` CSS MUST be loaded via `\OCP\Util::addStyle()`
- AND this MUST happen after Layer 7 and before conditional stylesheets

#### Scenario: Custom overrides cascade priority
- GIVEN a custom override defines `--color-primary: #ff0000 !important`
- AND the token set defines `--nldesign-color-primary: #004699`
- WHEN the CSS cascade resolves
- THEN the custom override value MUST win because it loads later in the cascade

#### Scenario: Custom overrides file initially empty
- GIVEN no admin customizations have been made
- WHEN `CustomOverridesService::ensureExists()` creates the file
- THEN the file MUST contain valid CSS (possibly just a comment)
- AND it MUST not affect any styling

### Requirement: WCAG AA Contrast Requirements
All color token combinations used for text-on-background MUST meet WCAG 2.1 AA minimum contrast ratios.

#### Scenario: Primary text on primary background
- GIVEN `--nldesign-color-primary` and `--nldesign-color-primary-text` are defined
- WHEN these colors are used together (e.g., primary buttons)
- THEN the contrast ratio MUST be at least 4.5:1 for normal text
- AND at least 3:1 for large text (18px or 14px bold)

#### Scenario: Default text on default background
- GIVEN `--nldesign-color-text` (#333333) on a white background
- WHEN body text is rendered
- THEN the contrast ratio MUST be at least 4.5:1

#### Scenario: Muted text meets minimum contrast
- GIVEN `--nldesign-color-text-muted` (#696969) on a white background
- WHEN secondary text is rendered
- THEN the contrast ratio MUST be at least 4.5:1 for normal text

#### Scenario: Focus indicator visible
@e2e exclude known defect, a browser test would fail: the shipped --nldesign-color-focus rgba(0, 123, 199, 0.5) composites to about 2.0:1 on white, below 3:1; the test lands with the token fix (see the #263 PR)
- GIVEN `--nldesign-color-focus` is used for keyboard focus outlines
- WHEN a focus outline appears on any background
- THEN the outline MUST have at least 3:1 contrast against the adjacent background

### Requirement: Design System Resolution

The app MUST support multiple design systems and resolve the correct one for each token set.
Shipped design systems are `none`, `nldesign`, `summer-breeze`, `high-contrast`, `lasuite`, and
(optionally) `cunningham`.

#### Scenario: Design system resolved from token set metadata

- GIVEN `token-sets.json` contains an entry with `design_system: "nldesign"`
- WHEN `DesignSystemService::getTokenSetMeta()` is called for that token set
- THEN the `design_system` field MUST be returned
- AND `DesignSystemService::getDesignSystem("nldesign")` MUST return the nldesign stylesheet bundle

#### Scenario: La Suite design system resolves

- GIVEN `token-sets.json` contains the `lasuite` entry with `design_system: "lasuite"`
- WHEN `DesignSystemService::getDesignSystem("lasuite")` is called
- THEN it MUST return the lasuite bundle with exactly five stylesheets in order:
  `systems/lasuite/fonts`, `systems/lasuite/defaults`, `systems/lasuite/brand-override`,
  `systems/lasuite/bridge`, `systems/lasuite/element-overrides`
- AND activating the `lasuite` token set MUST load that bundle followed by `tokens/lasuite`

#### Scenario: Cunningham blue-base design system resolves

- GIVEN `token-sets.json` contains a `cunningham` entry with `design_system: "cunningham"`
- WHEN `DesignSystemService::getDesignSystem("cunningham")` is called
- THEN it MUST return a bundle of exactly four stylesheets in order: `systems/lasuite/fonts`,
  `systems/lasuite/defaults`, `systems/lasuite/bridge`, `systems/lasuite/element-overrides`
  (the same shared files as `lasuite`, **without** `systems/lasuite/brand-override`)
- AND activating the `cunningham` token set MUST resolve the blue base (`--color-primary #1A509F`
  — brand-650, the same scale step the shared bridge/element-overrides derive `--color-primary`
  from for lasuite's violet `#4844AD`; `#0659C5` is brand-600, a different, unrendered step)

#### Scenario: Unknown design system falls back safely
@e2e exclude no shipped token set names an unknown design system, so no page can reach this branch; PHPUnit tests/Unit/DesignSystemServiceTest.php::testGetDesignSystemUnknownIdFallsBackToNoStylesheets asserts the empty-stylesheets fallback

- GIVEN a token set references a design system id not in `design-systems.json`
- WHEN `DesignSystemService::getDesignSystem()` is called with the unknown id
- THEN it MUST return a fallback with an empty `stylesheets` array
- AND no CSS MUST be loaded for the design system layers
- AND the app MUST not throw an exception

#### Scenario: Design systems are cached per request
@e2e exclude in-process caching inside one PHP request is not observable from a browser; PHPUnit tests/Unit/DesignSystemServiceTest.php::testGetDesignSystemsReadsTheManifestOncePerInstance asserts the second call does not re-read design-systems.json

- GIVEN `DesignSystemService::getDesignSystems()` is called multiple times in one request
- WHEN the second call is made
- THEN the cached result MUST be returned without re-reading `design-systems.json`

### Requirement: CSS Files in Systems Directory Structure

Design system CSS files MUST be organized in a `css/systems/{designSystemId}/` directory
structure, one directory per shipped design system. The `lasuite` and `cunningham` design systems
share a single `css/systems/lasuite/` directory (the `cunningham` bundle reuses the lasuite files
minus the brand override); no separate `css/systems/cunningham/` directory is required.

#### Scenario: NL Design system files in correct directory

- GIVEN the nldesign design system is active
- WHEN stylesheets are loaded
- THEN all CSS files MUST be located in `css/systems/nldesign/` (fonts.css, defaults.css,
  utrecht-bridge.css, theme.css, overrides.css, element-overrides.css)
- AND token set files MUST remain in `css/tokens/` regardless of design system

#### Scenario: La Suite system files in correct directory

- GIVEN the lasuite design system is active
- WHEN stylesheets are loaded
- THEN all CSS files MUST be located in `css/systems/lasuite/` (fonts.css, defaults.css,
  brand-override.css, bridge.css, element-overrides.css) with its font binaries under
  `css/systems/lasuite/fonts/`
- AND the lasuite files MUST NOT conflict with any other system's files (the `--lasuite-*` and
  `--lasuite--*` namespaces are exclusive to this directory)

#### Scenario: Cunningham reuses the lasuite directory

- GIVEN the cunningham design system is active
- WHEN stylesheets are loaded
- THEN they MUST resolve to files under `css/systems/lasuite/` (fonts, defaults, bridge,
  element-overrides), reusing the shared generated defaults
- AND `systems/lasuite/brand-override` MUST NOT be loaded for the cunningham bundle

#### Scenario: Future design systems have separate directories

- GIVEN a new design system "custom-ds" is added
- WHEN its stylesheets are declared in `design-systems.json`
- THEN its CSS files MUST be in `css/systems/custom-ds/`
- AND they MUST NOT conflict with nldesign files

### Requirement: Render-Context Discrimination

Style injection MUST be per-render-context. The listener MUST derive a context from the event:
`BeforeLoginTemplateRenderedEvent` ⇒ `login`; `BeforeTemplateRenderedEvent` ⇒ the response's
`renderAs` value mapped to `user`, `guest`, `public`, or `error`; any other or future `renderAs`
value MUST be treated as themed (fail open). The appconfig key `themed_contexts` (JSON array of
the five context names) selects which contexts receive nldesign CSS. An absent, empty, or
unparseable value MUST theme ALL contexts — the default behavior is byte-identical to the
previous boot-time injection on every surface. This change ships no admin UI for the key
(occ-only); ambiguity always resolves to themed because theming is presentation, not security.

#### Scenario: Default themes every context
- GIVEN the `themed_contexts` appconfig key is absent
- WHEN a login page, a user page, a guest page, a public share page, and an app-framework error
  page are each rendered
- THEN every one of them MUST receive the full nldesign stylesheet set exactly as before this
  change

#### Scenario: A context can be deliberately unthemed
@e2e exclude themed_contexts has no admin UI or HTTP endpoint (occ only, per the requirement), so a browser test cannot set it; PHPUnit tests/Unit/Service/CssInjectionServiceTest.php::testConfiguredListExcludesUnlistedContexts asserts the unlisted context gets no stylesheet
- GIVEN `themed_contexts` is `["user","login","guest","error"]`
- WHEN a public share page (`renderAs: public`) is rendered
- THEN no nldesign stylesheet MUST be injected on that page
- AND a user page rendered in the same configuration MUST remain fully themed

#### Scenario: Invalid configuration fails open to themed
@e2e exclude themed_contexts is occ-only, so a browser cannot store invalid JSON in it; PHPUnit tests/Unit/Service/CssInjectionServiceTest.php::testInvalidJsonThemedContextsFailsOpen and ::testNonArrayJsonThemedContextsFailsOpen assert every context stays themed
- GIVEN `themed_contexts` contains unparseable JSON or a non-array value
- WHEN any template renders
- THEN all contexts MUST be treated as themed
- AND no error MUST be raised

#### Scenario: Unknown renderAs values stay themed
@e2e exclude no Nextcloud page renders with an unknown renderAs, so no browser can reach this branch; PHPUnit tests/Unit/Listener/ThemeInjectionListenerTest.php::testUnknownRenderAsStillInjects asserts injection proceeds
- GIVEN a `BeforeTemplateRenderedEvent` whose response `renderAs` is `blank` or a value unknown
  to the listener
- WHEN the listener handles the event
- THEN injection MUST proceed as themed (fail open)
- AND the unknown value MUST NOT cause the configured context list to strip theming

### Requirement: The theme scopes layer sits between the design system and the component scopes
The app MUST inject `css/theme-scopes.css` after every stylesheet of the active design system and before `css/component-scopes.css`, in every themed render context.

@e2e exclude Stylesheet order is a server-side injection fact; the browser-visible consequence is tested in `nextcloud-variable-mapping` ("A set value reaches the page").

#### Scenario: Order on a workspace page
- GIVEN the nldesign design system is active
- WHEN a workspace page is rendered
- THEN the injected stylesheets MUST list `systems/nldesign/element-overrides` before `theme-scopes`
- AND they MUST list `theme-scopes` before `component-scopes`

#### Scenario: Order on the login page
- GIVEN the lasuite design system is active
- WHEN the login page is rendered
- THEN `theme-scopes` MUST follow the last lasuite stylesheet
- AND it MUST precede `component-scopes`

#### Scenario: A component scope falls back to a theme-scoped value
- GIVEN a set declares `--nldesign-nc-color-warning-hover` and no component token for the warning button
- WHEN a page with a warning button renders
- THEN the button's hover colour MUST be the set's value

### Requirement: Dark-mode compatibility variables follow Nextcloud in dark unless given a dark value
REQ-CSS-007 MUST keep holding for every layer: `--color-main-background`, `--color-main-background-rgb`, `--color-main-background-translucent`, `--color-background-plain`, `--background-invert-if-dark` and `--background-invert-if-bright` MUST resolve to Nextcloud's own value in every dark theme unless a set's dark variant file or a dark admin value provides one.

@e2e exclude The browser check is "Unset under the dark theme" in `nextcloud-variable-mapping`, run once per variable in this list.

#### Scenario: A light-only admin value leaves dark mode alone
- GIVEN the admin saves `--color-main-background: #fdfcf8` with no dark value
- AND the user has chosen Nextcloud's dark theme
- WHEN a page renders
- THEN the computed `--color-main-background` MUST equal Nextcloud's dark value

#### Scenario: The rule for every other admin override is unchanged
- GIVEN the admin saves `--color-primary: #24578f`
- AND the user has chosen Nextcloud's dark theme
- WHEN a page renders
- THEN the computed `--color-primary` MUST be the dark value the overrides writer derives from `#24578f`, as for every brand override

### Requirement: The internal scopes sit after the component scopes
The app MUST emit the internal scopes as an inline `<style id="thematiq-internal-scopes">` directly after `css/component-scopes.css`, in every themed render context, and MUST emit nothing for them when the set and the overrides give no internal token a value. The app MUST NOT write a file for them.

@e2e exclude Stylesheet order is a server-side injection fact; the browser-visible effect is tested in `component-tokens`.

#### Scenario: Order on a workspace page
- GIVEN the active set gives `--nldesign-nc-dp-hover-color` a value
- WHEN a workspace page is rendered
- THEN the stylesheet manifest MUST list `component-scopes`, then `internal-scopes`, as an inline layer

#### Scenario: Custom overrides still come last
- GIVEN the admin has saved overrides
- WHEN a page is rendered
- THEN the `custom-overrides` link MUST follow the internal scopes

#### Scenario: Nothing set adds nothing
- GIVEN neither the active set nor the overrides declare an internal token
- WHEN a page is rendered
- THEN the page MUST carry no internal scopes layer

## Current Implementation Status

**Fully implemented:**
- Design system driven loading: `Application.php` uses `DesignSystemService` to resolve which design system a token set uses and loads stylesheets from `design-systems.json` in declared order (lines 88-99)
- Token set CSS loaded separately after design system stylesheets when design system is not "none" (line 102-104)
- Custom overrides loaded after all layers via `CustomOverridesService` (lines 106-109)
- Conditional CSS loading for `hide-slogan` and `show-menu-labels` after all other layers (lines 112-118)
- CSS files organized in `css/systems/nldesign/` directory: fonts.css, defaults.css, utrecht-bridge.css, theme.css, overrides.css, element-overrides.css
- Layer 1 (fonts): `css/systems/nldesign/fonts.css` declares Fira Sans at weights 400/700, normal/italic, with `font-display: swap` and woff2+woff formats, plus `local()` hint
- Layer 2 (defaults): `css/systems/nldesign/defaults.css` defines all `--nldesign-*` tokens on `:root` including brand, status, background, text, border, focus, link, button colors, typography, border-radius, animation timing, placeholder colors, and `--nldesign-component-*` tokens
- Layer 3 (token sets): 39+ CSS files in `css/tokens/` directory
- Layer 4 (utrecht-bridge): `css/systems/nldesign/utrecht-bridge.css` maps `--utrecht-*` to `--nldesign-component-*` with fallbacks
- Layer 5 (theme): `css/systems/nldesign/theme.css` applies tokens to Nextcloud element selectors
- Layer 6 (overrides): `css/systems/nldesign/overrides.css` maps Nextcloud `--color-*` variables
- Layer 7 (element-overrides): `css/systems/nldesign/element-overrides.css` applies element-level styling
- `DesignSystemService` with caching, fallback for unknown design systems, and `readJsonManifest()` helper

**Not yet implemented:**
- All requirements in this spec are fully implemented.

## ADR-CSS-001: Font Application via Body Inheritance (not universal selector)

**Decision (2026-05-27):** NL Design font is applied to `body` and key Nextcloud
containers without `!important`. Form elements (`button`, `input`, `textarea`,
`select`, `label`) that resist inheritance are targeted explicitly without
`!important`. The universal selector `* { font-family: ... !important }` MUST NOT
be used because it clobbers icon fonts (Material Design Icons, Font Awesome),
monospace fonts in code editors, and any consumer component that explicitly
declares its own font family.

**Rationale:** CSS cascade inheritance from `body` is the correct mechanism.
Using `!important` on `*` (all elements) breaks consumer apps silently and
violates the principle of least surprise.

**References:** Issues #116, #117.

## ADR-CSS-002: !important Usage Restricted to Essential Overrides

**Decision (2026-05-27):** `!important` MUST only be used in two categories:

1. **Essential structural rules** — rules that must win over Nextcloud's
   own `body[data-themes]` CSS variable assignments (e.g. remapping
   `--color-primary` to `--nldesign-color-primary`). These require
   `!important` because Nextcloud sets the same variables with equal
   specificity.

2. **Accessibility rules** — focus outlines, contrast-critical colours.

`!important` MUST NOT be used on:
- Generic typographic preferences (font-size, line-height on h1–h6, p).
- Layout preferences that consumer apps may legitimately override.
- Any rule that is also set by `body` or `:root` in this app's own
  stylesheets (redundant escalation).

**Rationale:** ~280 `!important` declarations (issue #117) prevent consumer apps
from overriding theme values even with correct specificity. Reducing this to
essential-only allows consuming apps to use normal specificity to customise.

**References:** Issues #116, #117.

## ADR-CSS-003: --color-focus Semi-Transparency is a Deliberate Exception

**Decision (2026-05-27):** `--nldesign-color-focus: rgba(0, 123, 199, 0.5)` uses
an rgba (semi-transparent) value. This is a DELIBERATE EXCEPTION to the
Conduction brand rule "solid colours only". Rationale: focus outlines must be
visible on any background colour without requiring separate light/dark theme
variants. The translucent value composites naturally against the element's
background, satisfying WCAG 2.4.7 (focus visible) and 2.4.11 (focus appearance)
on both light and dark surfaces with a single token value.

**Revisit if:** the brand rule is updated to explicitly cover focus indicators,
or if a contrast audit shows the current value fails on specific backgrounds.

**References:** Issue #131.

## Standards & References
- CSS Custom Properties (CSS Variables) specification: https://www.w3.org/TR/css-variables-1/
- NL Design System community design tokens: https://nldesignsystem.nl/
- Rijkshuisstijl (Dutch government visual identity): https://www.rijkshuisstijl.nl/
- W3C Design Tokens specification (community group): https://design-tokens.github.io/community-group/format/
- Utrecht Design System component tokens (`--utrecht-*` namespace): https://nl-design-system.github.io/utrecht/
- WCAG 2.1 AA contrast requirements: https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html
- WCAG 2.1 AA focus indicator requirements: https://www.w3.org/WAI/WCAG21/Understanding/focus-visible.html
- CSS Cascade and Specificity: https://www.w3.org/TR/css-cascade-5/
