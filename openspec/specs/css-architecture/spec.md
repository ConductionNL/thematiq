---
status: in-progress
reviewed_date: 2026-02-28
enriched_date: 2026-03-20
---

# CSS Architecture Specification

## Purpose
Defines the layered CSS architecture that transforms NL Design System tokens into Nextcloud-compatible theming.

@e2e exclude CSS-architecture / PHP boot-order spec — all scenarios describe CSS cascade layers, file load order, and server-side PHP logic with no testable UI surface in the admin settings page. The architecture uses a design-system-driven approach: `design-systems.json` declares ordered stylesheet bundles, and `Application::boot()` loads the correct bundle for the active token set. Organization-specific tokens cascade correctly, incomplete token sets fall back gracefully, and NL Design System component tokens (using the `--utrecht-*` prefix) are bridged to the `--nldesign-*` namespace. The load order is critical: each layer builds on the previous one.
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
  1. `systems/nldesign/fonts` (Layer 1 -- @font-face declarations)
  2. `systems/nldesign/defaults` (Layer 2 -- all `--nldesign-*` token defaults)
  3. Token set file loaded separately: `tokens/{resolvedTokenSet}` (Layer 3 -- organization overrides)
  4. `systems/nldesign/utrecht-bridge` (Layer 4 -- `--utrecht-*` to `--nldesign-component-*` mapping)
  5. `systems/nldesign/theme` (Layer 5 -- `--nldesign-*` to Nextcloud element selectors)
  6. `systems/nldesign/overrides` (Layer 6 -- Nextcloud `--color-*` variable mappings)
  7. `systems/nldesign/element-overrides` (Layer 7 -- low-level element styling)

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

@e2e exclude regression invariant — PHPUnit asserts resolved id equals the app value
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
- GIVEN Fira Sans is used as the app's primary font
- WHEN the font is distributed
- THEN it MUST comply with the SIL Open Font License 1.1
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
- GIVEN Layer 7 (`css/systems/nldesign/element-overrides.css`) is loaded
- WHEN the font forcing rules are processed
- THEN `font-family: var(--nldesign-font-family) !important` MUST be applied to specific element selectors (html, body, div, span, p, h1-h6, a, button, input, textarea, select, label, li, ul, ol)
- AND it MUST also be applied via wildcard descendant selectors (`html body *`, `#body-user *`, `#app *`, `#content *`) to ensure complete coverage

#### Scenario: Header icons visible on themed background
- GIVEN the header has a white or light background from the token set
- WHEN Layer 7 is loaded
- THEN `#header .header-end svg` and related selectors MUST have `filter: invert(1) brightness(0) contrast(100)` to make icons visible
- AND avatar images (`#header .header-end .avatardiv img`) MUST be excluded from the filter
- AND user-status icons MUST be excluded from the filter

#### Scenario: App navigation styled as card
- GIVEN the app navigation sidebar renders
- WHEN Layer 7 styles are applied
- THEN `#app-navigation` MUST use `var(--color-main-background)` as background
- AND it MUST have a right margin of 30px (card layout effect)
- AND the closed state (`.app-navigation--close`) MUST have 0 margin

#### Scenario: App-specific exclusions
- GIVEN certain apps have custom widget styling (e.g., LaunchPad)
- WHEN solid background rules are applied
- THEN elements with `.launchpad-widget` or `.tile-widget` classes MUST be excluded
- AND the LaunchPad container MUST have transparent background
- AND these exclusions MUST prevent breaking app-specific layouts

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

- GIVEN a token set references a design system id not in `design-systems.json`
- WHEN `DesignSystemService::getDesignSystem()` is called with the unknown id
- THEN it MUST return a fallback with an empty `stylesheets` array
- AND no CSS MUST be loaded for the design system layers
- AND the app MUST not throw an exception

#### Scenario: Design systems are cached per request

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
- GIVEN `themed_contexts` is `["user","login","guest","error"]`
- WHEN a public share page (`renderAs: public`) is rendered
- THEN no nldesign stylesheet MUST be injected on that page
- AND a user page rendered in the same configuration MUST remain fully themed

#### Scenario: Invalid configuration fails open to themed
@e2e exclude config-validation branch — PHPUnit on CssInjectionService
- GIVEN `themed_contexts` contains unparseable JSON or a non-array value
- WHEN any template renders
- THEN all contexts MUST be treated as themed
- AND no error MUST be raised

#### Scenario: Unknown renderAs values stay themed
@e2e exclude forward-compatibility branch — PHPUnit on the listener mapping
- GIVEN a `BeforeTemplateRenderedEvent` whose response `renderAs` is `blank` or a value unknown
  to the listener
- WHEN the listener handles the event
- THEN injection MUST proceed as themed (fail open)
- AND the unknown value MUST NOT cause the configured context list to strip theming

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
