# Spec delta: Component Tokens (component-scoped tokens)

The `--nldesign-component-*` vocabulary keeps everything it has. What is added is a way for a
component token to REACH its component without being painted by hand, a registry that exposes
those tokens for editing, and a deliberate way to give the brand primary its reach back.

@e2e exclude CSS-cascade and registry spec — the scenarios are custom-property resolution and
file-structure assertions. The one user-visible surface, the admin toggle and its locked rows,
is on the admin theming page already covered by admin-settings tests.

## ADDED Requirements

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
