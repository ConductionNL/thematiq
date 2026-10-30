---
kind: code
---

# Export every shipped token set as shell roles

## Why

The OpenProject plugin (`ConductionNL/openproject-thematiq`) wears the shell of a themed Nextcloud.
Ruben decided on 12 Oct 2026 that it works on its own, without a live link to thematiq, and ships
every thematiq token set. It needs those sets as data it can bundle: the eighteen shell roles per
set, and a clear answer on which logos and fonts it may carry.

`GET /api/shell-tokens` answers for one instance's active set. Nothing gives all sets at once, and
nothing says which files another product may redistribute.

## What changes

- `php scripts/generate-shell-catalogue.php` (also `composer docs:shell-catalogue`) writes
  `docs/reference/shell-catalogue.json`: every `css/tokens/<id>.css` as the eighteen roles, with a
  logo block per set and a font list.
- The roles come from `ShellRoleMapper`, the class the endpoint uses.
- Each logo and each font family is marked redistributable `true`, `false` or `null`, from what
  this repository itself says.
- `tests/Unit/ShellCatalogueTest.php` fails when the committed catalogue is stale.

## Impact

- Nothing an administrator or a visitor sees changes. No route, no setting.
- A new committed file that a change to any token set must regenerate. The test names the command.
- No screen: the catalogue is a data file for another product.
