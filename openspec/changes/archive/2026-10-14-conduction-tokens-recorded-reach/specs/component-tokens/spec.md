# component-tokens

## ADDED Requirements

### Requirement: Each Conduction Component Token Has A Recorded Reach

The app MUST record, for every `--nldesign-cn-*` token of the internal token map, what a token set
can do for it: `follows` when the library's own value reads a Nextcloud variable the design system
maps from a set token, `literal` when that value is a size, weight or ratio, and `use-site` when the
library declares the variable nowhere and reads it with a fallback. The record SHALL be proved against
the inventory and the design system's stylesheet, never asserted.

#### Scenario: The record covers the inventory

- GIVEN the internal token map
- WHEN its tokens owned by the shared library are listed
- THEN the record holds exactly those tokens
- AND each has one of the three reaches

#### Scenario: A token that follows the set is proved to

- GIVEN a token recorded as `follows`
- WHEN its stock value and `css/systems/nldesign/theme.css` are read
- THEN the stock value reads the Nextcloud variable the record names
- AND the stylesheet maps that variable from the set token the record names

### Requirement: A Token With Variants Is Not Declared By A Shipped Stylesheet

A Conduction component token the library declares on more than one selector carries one value per
state. A shipped token set or design system stylesheet MUST NOT declare such a token, because the
internal scopes write a declared value onto every one of those selectors.

#### Scenario: The status colours keep their own values

- GIVEN `--nldesign-cn-kpi-accent` and `--nldesign-cn-forms-status`
- WHEN every stylesheet under `css/` is searched
- THEN none declares either token
- AND a KPI tile marked as an error keeps the error colour of the set
