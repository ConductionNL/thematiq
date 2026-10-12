# Tasks: conduction-tokens-recorded-reach

## 1. Test first

- [x] 1.1 `tests/vitest/conductionTokenReach.spec.js`: the record covers the 33 Conduction tokens of
      the inventory and nothing else. Red before the record existed (8 of 8).
- [x] 1.2 A `follows` entry is proved: the stock value reads the Nextcloud variable it names, and
      `css/systems/nldesign/theme.css` maps that variable from the set token it names.
- [x] 1.3 A `literal` entry's stock value reads no variable. A `use-site` entry has no stock value.
- [x] 1.4 A token the library declares on more than one selector is marked `variants`, and no
      stylesheet under `css/` declares it.

## 2. The record

- [x] 2.1 `scripts/mapping/conduction-token-reach.json`: 6 follow, 19 literal, 8 use-site. The
      use-site fallbacks were read from `@conduction/nextcloud-vue` 2.58.0.

## 3. Checks

- [x] 3.1 The touched test, prettier and eslint on the touched files.
