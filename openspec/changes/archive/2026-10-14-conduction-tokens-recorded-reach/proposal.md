---
kind: code
---

## Why

Task 2.1 of `token-set-coverage-next-waves` reads: no shipped set declares the 453 internal tokens,
start with the 33 `--nldesign-cn-*` tokens. That counts a missing declaration as a gap. Measured on
the inventory, most of those 33 are not a gap a set can close:

- 6 already follow the set. The library's own value reads a Nextcloud variable, and the design system
  maps that variable from a token every set declares. A declaration would change nothing.
- 2 of those 6 (`--nldesign-cn-kpi-accent`, `--nldesign-cn-forms-status`) are declared by the library
  on several selectors, one per state. The internal scopes write a set's value onto every one of
  them, so a set-level value would paint success, warning and error the same colour.
- 19 hold a literal size, weight or ratio. The `--nldesign-*` vocabulary has no spacing or type scale,
  so a set has no value of its own to give.
- 8 are declared nowhere by the library and read with a fallback where they are used. Three of those
  fallbacks are fixed greens of the Forms and XWiki apps, which follow no set.

Without that on record, the next wave declares 33 tokens in 63 sets, moves a coverage figure and
changes nothing on screen, or flattens the two tokens with variants.

## What Changes

- `scripts/mapping/conduction-token-reach.json` records the reach of each of the 33 tokens: `follows`
  (with the Nextcloud variable and the set token behind it), `literal` or `use-site`.
- `tests/vitest/conductionTokenReach.spec.js` proves each entry against the inventory and
  `css/systems/nldesign/theme.css`, and keeps the two tokens with variants out of every shipped
  stylesheet.

## Impact

- Nothing on screen changes. No set gains a declaration.
- The three fixed greens are a question for Ruben (Q-thematiq-9): pointing them at each set's
  success colour fails 3:1 in six sets.
- The 420 Nextcloud internal tokens of task 2.1 are a later wave; this record is the shape they take.
