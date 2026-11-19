---
kind: code
---

## Why

Task 2.1 of `token-set-coverage-next-waves` still held the 420 Nextcloud component and slot tokens
of the internal token map. The 33 Conduction tokens got a recorded reach in
`conduction-tokens-recorded-reach`; the Nextcloud ones had none, so a missing declaration in a set
still read as a gap to close.

Measured on the inventory, most of them are not a gap a set can close. Ruben decided on 19 Nov
2026 how two groups count:

- A fixed colour (the PDF viewer, code highlighting, the date picker) is `literal`, not a gap that
  sets must fill.
- A token whose stock value reads only other internal tokens takes the reach of the first one it
  reads.

## What changes

- `scripts/mapping/nextcloud-token-reach.json` records one reach per token: `follows` (with the
  Nextcloud variable and the set token behind it), `literal`, `use-site` or `unmapped`, plus
  `inherits` for a token that reads only other internal tokens.
- `unmapped` is new: the stock value reads only variables no set token drives, such as Nextcloud's
  grid baseline and clickable area, or a library variable outside the inventory.
- `scripts/inventory/generate-nextcloud-token-reach.mjs` writes the record, with `--check` to
  compare.
- `tests/vitest/nextcloudTokenReach.spec.js` proves each entry against the inventory and
  `css/systems/nldesign/theme.css`.

## Impact

- Nothing on screen changes. No set gains a declaration.
- Measured on Nextcloud 34 and the current inventory: 39 follow, 252 literal, 56 use-site and 73
  unmapped.
