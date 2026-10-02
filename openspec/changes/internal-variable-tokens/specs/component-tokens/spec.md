## ADDED Requirements

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
