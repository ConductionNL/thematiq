# Tasks: nextcloud-tokens-recorded-reach

## 1. Test first

- [x] 1.1 `tests/vitest/nextcloudTokenReach.spec.js`: the record covers the 420 Nextcloud component
      and slot tokens of the inventory and nothing else, and equals what the generator writes.
- [x] 1.2 A direct `follows` entry is proved against `css/systems/nldesign/theme.css`; a `literal`
      entry reads no variable; a `use-site` entry has no stock value; an `unmapped` entry reads only
      variables no set token drives.
- [x] 1.3 A token that reads only other internal tokens has the reach of the first one it reads.
- [x] 1.4 The fixed colours of the PDF viewer, code highlighting and the date picker are `literal`.

## 2. The record

- [x] 2.1 `scripts/inventory/generate-nextcloud-token-reach.mjs` and
      `scripts/mapping/nextcloud-token-reach.json`: 39 follow, 252 literal, 56 use-site, 73 unmapped.

## 3. Checks

- [x] 3.1 The touched test, eslint on the touched files.
