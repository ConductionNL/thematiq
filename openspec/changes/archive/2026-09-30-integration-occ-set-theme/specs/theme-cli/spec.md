# Spec delta: theme command line (theme-cli)

A new capability. Operators list, read and switch the active token set with occ.

## ADDED Requirements

### Requirement: Operators list and read token sets

`occ nldesign:theme:list` MUST print every available token set with its id, name and whether it is shipped or custom. `occ nldesign:theme:get` MUST print the active instance-wide token set and every group mapping in priority order.

#### Scenario: An operator looks up the id of a set

- GIVEN a server with the shipped sets and one custom set `custom-gemeente-x`
- WHEN an operator runs `occ nldesign:theme:list`
- THEN the output MUST list `custom-gemeente-x` marked as custom
- AND the command MUST exit 0

### Requirement: Operators switch the active token set

`occ nldesign:theme:set <token-set>` MUST validate the id exactly as the Design token set dropdown does, make it the active instance-wide set, and write a `token_set_changed` audit entry with actor `cli`. An unknown id MUST exit non-zero, name the id and change nothing. Setting the set that is already active MUST exit 0 and write no audit entry. `--dry-run` MUST validate and print what would change without writing.

#### Scenario: An operator switches the house style from a script

- GIVEN `rijkshuisstijl` is active
- WHEN an operator runs `occ nldesign:theme:set amsterdam`
- THEN the command MUST exit 0
- AND a user who opens the dashboard next MUST see the Amsterdam colours
- AND the audit log on Settings > Administration > Theming MUST show a token set change by `cli`

#### Scenario: A typo does not change the theme

- GIVEN `rijkshuisstijl` is active
- WHEN an operator runs `occ nldesign:theme:set amsterdm`
- THEN the command MUST exit non-zero with a message naming `amsterdm`
- AND `rijkshuisstijl` MUST stay active

### Requirement: Core theming is changed only on request

Without `--sync-core`, `nldesign:theme:set` MUST NOT change Nextcloud core theming values. With `--sync-core`, it MUST apply the set's theming metadata (primary colour, background, logo) the way the theming-sync dialog does, and the output MUST say which values were applied.

#### Scenario: An operator also updates the Nextcloud logo and colours

- GIVEN a token set whose theming metadata has a primary colour and a logo
- WHEN an operator runs `occ nldesign:theme:set <that set> --sync-core`
- THEN the Nextcloud core primary colour and logo MUST match the set's metadata
- AND the output MUST list the primary colour and logo as applied
