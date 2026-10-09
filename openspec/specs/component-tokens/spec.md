---
status: in-progress
---

# Component Tokens Specification

## Purpose
Introduces component-level NL Design System tokens using the `--nldesign-component-*` prefix, with a temporary bridge file that maps the current `--utrecht-*` component tokens to the nldesign namespace.

## Requirements

### Requirement: NLDesign Component Token Prefix
All component-level tokens MUST use the `--nldesign-component-*` prefix for consistency with the rest of the nldesign token system.

#### Scenario: Button component token
- GIVEN the NL Design System defines `--utrecht-button-primary-action-background-color`
- WHEN it is used in the nldesign system
- THEN it MUST be available as `--nldesign-component-button-primary-action-background-color`

#### Scenario: Heading component token
- GIVEN the NL Design System defines `--utrecht-heading-1-font-size`
- WHEN it is used in the nldesign system
- THEN it MUST be available as `--nldesign-component-heading-1-font-size`

### Requirement: Utrecht Bridge File
The system MUST include a `utrecht-bridge.css` file that maps `--utrecht-*` component tokens to `--nldesign-component-*` tokens.

#### Scenario: Bridge maps Utrecht tokens to nldesign
- GIVEN an organization token set defines `--utrecht-button-border-radius: 4px`
- WHEN the CSS is loaded with the bridge file
- THEN `--nldesign-component-button-border-radius` MUST resolve to `4px`

#### Scenario: Bridge falls back to defaults
- GIVEN an organization token set does NOT define `--utrecht-button-border-radius`
- WHEN the CSS is loaded with the bridge file
- THEN `--nldesign-component-button-border-radius` MUST fall back to the value from `defaults.css`

#### Scenario: Bridge file is clearly marked as temporary
- GIVEN a developer opens `utrecht-bridge.css`
- WHEN they read the file header
- THEN the header MUST contain a comment explaining that this file is temporary
- AND it MUST state that the file can be removed when the NL Design System adopts a vendor-neutral prefix
- AND it MUST reference the NL Design System themes repository

### Requirement: Component Token Categories
The system MUST support component tokens for the following NL Design System component types.

#### Scenario: Button tokens
- GIVEN the NL Design System defines button component tokens
- WHEN the nldesign app processes them
- THEN it MUST support tokens for: background-color, color, border-radius, border-width, border-color, font-family, font-size, padding, and state variants (hover, active, disabled, focus, primary-action, secondary-action)

#### Scenario: Form input tokens
- GIVEN the NL Design System defines form input component tokens
- WHEN the nldesign app processes them
- THEN it MUST support tokens for: textbox, form-field, form-select, and form-fieldset components
- AND the textbox MUST support state variants (focus, hover, disabled, invalid), and form-select a focus variant

#### Scenario: Typography tokens
- GIVEN the NL Design System defines heading and paragraph component tokens
- WHEN the nldesign app processes them
- THEN it MUST support tokens for heading levels 1-6 (font-size, font-weight, line-height, color)
- AND it MUST support paragraph tokens (font-size, line-height, color)

#### Scenario: Additional component tokens
- GIVEN the NL Design System defines tokens for link, table, badge, separator, ordered-list, and unordered-list
- WHEN the nldesign app processes them
- THEN these MUST be available as `--nldesign-component-{component}-*` tokens

### Requirement: Component Token Defaults
All component tokens MUST have sensible default values in `defaults.css`.

#### Scenario: Component token defaults reference brand tokens
- GIVEN `defaults.css` defines `--nldesign-component-button-primary-action-background-color`
- WHEN no organization token overrides it
- THEN it MUST default to `var(--nldesign-color-primary)` (referencing the brand token)

#### Scenario: Component token defaults are self-consistent
- GIVEN `defaults.css` defines all component token defaults
- WHEN loaded without any organization token file
- THEN all components MUST render with visually consistent styling using the default brand tokens

### Requirement: Every themable internal variable has a token
For every inventory entry of class `component`, `slot` or `conduction` whose status is not `excluded`, the token registry MUST expose one token, named `--nldesign-nc-<name>` for Nextcloud entries and `--nldesign-cn-<name>` for Conduction entries.

@e2e exclude Registry content; the browser-visible effect is tested by the two "reaches" requirements below.

#### Scenario: A date picker variable has a token
- GIVEN the inventory lists `--dp-hover-color` with class `component` and status `settable`
- WHEN the token registry is read
- THEN it MUST contain `--nldesign-nc-dp-hover-color`
- AND the entry MUST name the date picker as its owner

#### Scenario: A KPI tile variable has a token
- GIVEN the inventory lists `--cn-kpi-accent` with class `conduction`
- WHEN the token registry is read
- THEN it MUST contain `--nldesign-cn-kpi-accent`

#### Scenario: A runtime variable has no token
- GIVEN the inventory lists `--systemtag-color`, which the Files app writes with `setProperty`, with class `runtime`
- WHEN the token registry is read
- THEN it MUST NOT contain a token for `--systemtag-color`

### Requirement: An unset internal token leaves the component untouched
A component MUST render exactly as Nextcloud or the shared library styles it when none of its internal tokens is set.

#### Scenario: Code highlighting with no tokens set
- GIVEN the active set declares no `--nldesign-nc-hljs-*` token
- WHEN a code block renders in the Text app
- THEN every syntax colour MUST equal Nextcloud's own

#### Scenario: A slot with no token set uses the component's fallback
- GIVEN the media player reads `var(--plyr-audio-control-background-hover, <fallback>)` and nothing declares it
- AND no set declares `--nldesign-nc-plyr-audio-control-background-hover`
- WHEN an audio control is hovered
- THEN its background MUST be the player's own fallback

### Requirement: A set internal token reaches its component
When a set or the admin gives an internal token a value, the component MUST render with it, whether the component only reads the variable or declares it on its own element.

#### Scenario: A read-only slot is reached
- GIVEN a set declares `--nldesign-nc-plyr-audio-control-background-hover: #f4f1ea`
- WHEN an audio control is hovered
- THEN its background MUST be `#f4f1ea`

#### Scenario: A variable the component declares itself is reached
- GIVEN the date picker declares `--dp-hover-color` on its own element
- AND the admin sets `--nldesign-nc-dp-hover-color` to `#e8eef5`
- WHEN a date in the picker is hovered
- THEN its hover background MUST be `#e8eef5`

#### Scenario: A Conduction dashboard tile is reached
- GIVEN a set declares `--nldesign-cn-kpi-accent: #24578f`
- WHEN a Conduction app shows a KPI tile
- THEN the tile's accent MUST be `#24578f`

### Requirement: Internal scopes are built only for set tokens
The internal scopes MUST contain a rule only for internal tokens that the active set or the admin overrides give a value, MUST be built from the set's files and the overrides at render time, and MUST contain no value from either, only `var()` references to the token.

@e2e exclude Rule content; the visible effect is covered by "A variable the component declares itself is reached".

#### Scenario: Saving an override changes the next render
- GIVEN the active set gives one internal token a value
- WHEN the admin saves an override for `--nldesign-cn-kpi-accent`
- THEN the next render's internal scopes MUST hold both rules

#### Scenario: Nothing set means no rule
- GIVEN neither the active set nor the overrides declare an internal token
- WHEN the internal scopes are built
- THEN they MUST be empty

#### Scenario: A token set only in the dark file leaves light alone
- GIVEN the set's dark variant gives `--nldesign-nc-dp-hover-color` a value and its light file does not
- WHEN the page renders in the light theme
- THEN the date picker MUST keep its own hover colour
- AND in the dark theme it MUST use the set's value

### Requirement: The brand primary can drive every component
The admin settings MUST offer one switch, "Let the primary color drive every component", stored as the app config key `primary_drives_components` and off by default. While it is on, every component token flagged `primary` in `scripts/mapping/component-tokens.json` MUST resolve to the brand value it would take with no per-component value set, and the token editor MUST lock the controls of those tokens. Stored per-component values MUST be kept, so switching it off restores them. Brand tokens and component tokens not flagged `primary` MUST stay editable.

Code today: the checkbox in `templates/settings/admin.php` (`nldesign-primary-drives-components`), saved by `js/admin.js` through `POST /settings/primary-drives-components` (`lib/Controller/SettingsController.php` `setPrimaryDrivesComponentsSetting`, admin only, audit-logged as `toggle_changed`); `lib/Service/CssInjectionService.php` emits `css/primary-lock.css` (generated by `scripts/generate-component-scopes.mjs`) after the overrides and custom CSS while the key is `1`; `js/admin.js` `isTokenLocked()` and `lockReason()` disable a locked row and say why; the key travels in the configuration bundle (`lib/Service/ConfigBundleService.php`) and in theme versions (`lib/Service/ThemeVersionRestoreService.php`).

@e2e exclude Covered by unit tests (tests/Unit/Service/CssInjectionServiceTest.php, tests/Unit/Controller/SettingsControllerEndpointsTest.php, tests/vitest/admin-token-editor.spec.js); no Playwright spec drives the switch yet.

#### Scenario: Off by default leaves stored component colours alone
- GIVEN `primary_drives_components` was never set
- AND the admin stored a background colour for the login button
- WHEN a page renders
- THEN the login button MUST use the stored colour
- AND `css/primary-lock.css` MUST NOT be on the page

#### Scenario: Switching it on makes the primary win
- GIVEN the admin stored a background colour for the login button
- WHEN the admin ticks "Let the primary color drive every component"
- THEN the setting MUST be saved as `1`
- AND the next page MUST load `css/primary-lock.css` after the overrides and custom CSS
- AND the login button MUST take the brand primary element colour

#### Scenario: Locked controls say why
- GIVEN the switch is on
- WHEN the admin opens the token editor
- THEN the colour rows of tokens flagged `primary` MUST be disabled
- AND each MUST explain that the primary color drives it and how to switch that off
- AND a component token not flagged `primary`, such as a border radius, MUST stay editable

#### Scenario: Switching it off restores the stored values
- GIVEN the switch is on and a login button colour is stored
- WHEN the admin unticks the switch
- THEN `css/primary-lock.css` MUST no longer be emitted
- AND the login button MUST use the stored colour again

#### Scenario: Only an admin can change it
- GIVEN a user who is not an admin of the theming settings
- WHEN they post to `/settings/primary-drives-components`
- THEN the request MUST be refused and the setting MUST stay unchanged

#### Scenario: The switch travels with the theme
- GIVEN the switch is on
- WHEN the admin exports the configuration bundle and imports it on another instance
- THEN `config.primaryDrivesComponents` MUST be `true` in the bundle
- AND the importing instance MUST store `primary_drives_components` as `1`

### Requirement: Workplace Component Tokens
`scripts/mapping/component-tokens.json` MUST carry these tokens, and `css/component-scopes.css`
MUST apply them:

- `--nldesign-component-navigation-active-color`: the selected navigation entry's label and icon.
- `--nldesign-component-navigation-badge-background-color` and `-badge-color`: the count badge of a navigation entry, and its number.
- `--nldesign-component-content-card-shadow-color`: the shadow colour inside the content.
- `--nldesign-component-content-surface-background-color`: the surface cards sit on.
- `--nldesign-component-status-badge-{info,success,warning,error}-background-color` and `-color`: the tint and the label of a status pill.

No stylesheet MUST declare any of them by default. With a token unset, its rule MUST resolve to
the value the element had before the rule existed.

#### Scenario: An unset token changes nothing
@e2e exclude Generator logic: tests/vitest/componentScopesGenerator.spec.js resolves each new rule with the token unset
- GIVEN a set that declares none of the new tokens
- WHEN each new rule is resolved
- THEN every redirected variable MUST equal its captured global, or the broader token the rule names as its fallback

#### Scenario: The badge is coloured in the navigation only
@e2e exclude Generator logic: tests/vitest/componentScopesGenerator.spec.js reads the generated selectors
- GIVEN `--nldesign-component-navigation-badge-background-color` is set
- WHEN the generated rules are read
- THEN the redirect MUST be scoped to a counter inside the app navigation, and a counter elsewhere MUST keep its own value

### Requirement: A Surface Token Does Not Reach The Cards
A token flagged `selfOnly` MUST redirect its variable on the component itself and MUST hand the
captured global back to the component's children, in a rule with no specificity.

#### Scenario: Cards keep the main background
@e2e exclude Generator logic: tests/vitest/componentScopesGenerator.spec.js reads the generated rules
- GIVEN the content surface token is set to a grey
- WHEN the generated rules are read
- THEN the content MUST read the grey for `--color-main-background`
- AND its children MUST read the captured global again

### Requirement: A Refining Token Falls Back To The Token It Refines
A token that names a `fallback` token MUST try that token before the captured global. A token
that names `alsoGlobals` MUST redirect each of those variables too.

#### Scenario: The selected entry keeps the navigation label colour
@e2e exclude Generator logic: tests/vitest/componentScopesGenerator.spec.js reads the generated declaration
- GIVEN a set that sets `--nldesign-component-navigation-color` and not the active colour
- WHEN the selected entry's rule is resolved
- THEN its label MUST still read `--nldesign-component-navigation-color`

#### Scenario: The old mapping generates the same files
@e2e exclude Generator logic: tests/vitest/componentScopesGenerator.spec.js runs the generator over a mapping without the new fields
- GIVEN a mapping that uses none of `selfOnly`, `fallback` and `alsoGlobals`
- WHEN the generator runs
- THEN it MUST emit no restore rule and no nested fallback

### Requirement: Component Tokens Are Declared As Data
The component token layer MUST be defined in `scripts/mapping/component-tokens.json`, and both
the PHP registry and the CSS generator MUST read that file rather than restate it.

#### Scenario: One table, two runtimes
- GIVEN a component token is added to the mapping table
- WHEN the registry is built and the stylesheets are regenerated
- THEN the token MUST be editable in the admin panel
- AND a scope rule applying it MUST appear in `css/component-scopes.css`

#### Scenario: Two tokens may not claim one global
- GIVEN a component maps two of its tokens onto the same Nextcloud variable
- WHEN `scripts/generate-component-scopes.mjs` runs
- THEN it MUST refuse to emit and MUST name both tokens
- BECAUSE the rule would declare one property twice and the second would silently win

#### Scenario: The committed stylesheets are drift-checked
- GIVEN the mapping table has changed and the stylesheets have not been regenerated
- WHEN `npm run test:component-scopes` runs
- THEN it MUST fail and MUST report the first differing line

### Requirement: A Component Token Reaches Only Its Own Component
`css/component-scopes.css` MUST redeclare the Nextcloud variable a component consumes inside
that component's own subtree, so that setting a component token repaints that component and no
other.

#### Scenario: Two components sharing one global move independently
- GIVEN `--nldesign-component-login-button-background-color` is set
- AND `--nldesign-component-button-primary-action-background-color` is not
- WHEN the login page and an app page are rendered
- THEN the login button MUST take the new colour
- AND every other primary button MUST keep the brand colour

#### Scenario: An unset component token is indistinguishable from stock
- GIVEN no component token is declared or stored
- WHEN any page is rendered
- THEN every component MUST resolve to the same value it had before this layer existed

#### Scenario: The scope does not need to outrank the brand layer
- GIVEN `custom-overrides.css` declares a global at `:root` with `!important`
- AND a component scope declares the same variable on the component's root
- WHEN the component is rendered
- THEN the component's own declaration MUST win, without `!important`
- BECAUSE an inherited value is only used by an element that has no declaration of its own

### Requirement: The Fallback Is Captured, Not Self-Referential
The scope rules MUST fall back to a `--thematiq-global-*` copy of the global declared at
`:root`, and MUST NOT reference the global they are redeclaring.

#### Scenario: The capture avoids the cycle
- GIVEN a scope rule needs the brand value as its fallback
- WHEN `css/component-scopes.css` is generated
- THEN the fallback MUST be `var(--thematiq-global-<name>)`
- AND `:root` MUST declare that name as `var(--<name>)`

#### Scenario: The capture loses to an admin override
- GIVEN an admin sets a brand global in the token editor
- WHEN a component with no component token of its own is rendered
- THEN it MUST take the admin's value

### Requirement: Component Tokens Are Declared By The Design System, Not The Scope Layer
`css/component-scopes.css` MUST NOT declare a component token. Declarations MUST live in a
design system's own defaults.

#### Scenario: The layer is safe under a design system that knows nothing about it
- GIVEN the active design system is `none`, `summer-breeze`, `high-contrast`, `lasuite` or
  `cunningham`
- WHEN a page is rendered with the component scopes loaded
- THEN every component token MUST be undefined
- AND every component MUST render exactly as it would without the layer

### Requirement: The Registry Carries Both Layers
`TokenRegistry::getTokens()` MUST return Nextcloud's globals AND the component tokens, each
carrying the component it belongs to and whether the brand primary used to drive it.

#### Scenario: A token says which layer it is in
- GIVEN the registry is requested
- WHEN a token is read
- THEN a Nextcloud global MUST carry `group: "brand"`
- AND a component token MUST carry the component's id as its `group`

#### Scenario: A missing table is not a broken panel
- GIVEN `scripts/mapping/component-tokens.json` is absent or malformed
- WHEN the token editor loads
- THEN the brand tokens MUST still be editable
- AND the component layer MUST be empty rather than an error

### Requirement: The Brand Primary May Be Given Its Reach Back, Deliberately
The `primary_drives_components` appconfig MUST default to off. While it is on, every component
token flagged `primary` MUST resolve to the captured brand value, and its editor row MUST
render disabled.

#### Scenario: Off is a no-op on an instance that never themed a component
- GIVEN no per-component value has ever been stored
- WHEN the setting is off
- THEN every component MUST render in the brand primary
- BECAUSE the component defaults already resolve to it

#### Scenario: On overrules a stored per-component value
- GIVEN a per-component colour is stored in `custom-overrides.css`
- WHEN the setting is on
- THEN `css/primary-lock.css` MUST be emitted after `custom-overrides.css`
- AND the component MUST render in the brand primary

#### Scenario: On does not destroy what it overrules
- GIVEN the setting is turned on and then off again
- WHEN the page is rendered
- THEN the previously stored per-component values MUST take effect again

#### Scenario: Only the tokens the primary owns are locked
- GIVEN the setting is on
- WHEN the token editor renders
- THEN the rows for `primary`-flagged component tokens MUST be disabled
- AND rows for radii, font weights and durations MUST stay editable
- AND the brand globals MUST stay editable, because the setting exists to make them win

### Requirement: No Playground Chip May Write A Nextcloud Global
Every token named by a component chip in `js/playground/components.json` MUST be a component
token.

#### Scenario: A chip reaches its own component
- GIVEN the `Primary button` chip
- WHEN its rows are read
- THEN every token MUST be a `--nldesign-component-*` name
- AND none MUST be `--color-primary-element`

#### Scenario: The brand globals stay reachable, from the list rather than a chip
- GIVEN a Nextcloud global carried by the registry
- WHEN the inventory coverage guard runs
- THEN the global MUST be exempt from the chip-coverage rule
- AND it MUST remain editable from the token editor's four-tab list
