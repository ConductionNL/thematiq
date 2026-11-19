# component-tokens

## ADDED Requirements

### Requirement: Each Nextcloud internal token has a recorded reach

The app MUST record, for every Nextcloud component and slot token of the internal token map, what
a token set can do for it: `follows` when the stock value reads a Nextcloud variable the design
system maps from a set token, `literal` when the stock value is fixed (a colour included),
`use-site` when nothing declares the variable, and `unmapped` when the stock value reads only
variables no set token drives. A token whose stock value reads only other internal tokens SHALL
take the reach of the first one it reads. The record SHALL be proved against the inventory and the
design system's stylesheet, never asserted.

@e2e exclude Record content; nothing on screen changes, and tests/vitest/nextcloudTokenReach.spec.js proves every entry.

#### Scenario: The record covers the inventory

- GIVEN the internal token map
- WHEN its Nextcloud component and slot tokens are listed
- THEN the record holds exactly those tokens
- AND each has one of the four reaches

#### Scenario: A fixed colour counts as literal

- GIVEN a PDF viewer, code highlighting or date picker token whose stock value is a fixed colour
- WHEN the record is read
- THEN the token is recorded as `literal`, not as a gap a set must fill

#### Scenario: A token that reads another internal token inherits its reach

- GIVEN `--nldesign-nc-alt-text-active-bg-color`, whose stock value reads `--alt-text-opacity`
- WHEN the record is read
- THEN the token is recorded as `literal`
- AND it names `--nldesign-nc-alt-text-opacity` as the token it inherits from
