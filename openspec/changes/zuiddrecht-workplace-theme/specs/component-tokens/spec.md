# Spec delta: Component tokens (zuiddrecht-workplace-theme)

## ADDED Requirements

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
