---
kind: code
depends_on: []
---

## Why

Nobody can check whether thematiq supports "all" Nextcloud variables, because nothing records what "all" is. The `nextcloud-variable-mapping` spec asks for an entry per theming variable in `overrides.css`, and that list is kept by hand. It covers the 111 variables Nextcloud's theming app declares. It says nothing about the 1,027 more that Nextcloud's own code reads. It also misses the 54 `--cn-*` variables of our shared `@conduction/nextcloud-vue` library.

Extracted on 2 October 2026 from Nextcloud 34.0.0.12 and `@conduction/nextcloud-vue` 2.57.1:

| Class | Count |
|---|---|
| Theme vocabulary (declared by the theming app) | 111 |
| Internal to one component (set and read in shipped code) | 380 |
| Icon images | 484 |
| Read with a fallback, declared nowhere | 83 |
| Written by JavaScript at render time | 36 |
| Declared, never read | 10 |
| Conduction `--cn-*` (shared library) | 51 |

A first hand count put components at 438, slots at 140 and `--cn-*` at 54. The extractor classes more carefully. Values that are `url()` count as icons, JavaScript writes count as runtime, and template-string fragments such as `--cn-kpi-` are not names. Its numbers replace the hand count.

This change makes that table a committed, regenerable file and puts a guard on it. It is the head of a four-change chain that takes thematiq from 66 of 111 theme variables to all of them, plus the internal and `--cn-*` layers:

1. `nc-variable-inventory` (this change): the inventory and its guard.
2. `theme-vocabulary-complete`: every one of the 111 theme variables becomes settable.
3. `internal-variable-tokens`: the themable internal and `--cn-*` variables become component tokens.
4. `token-editor-at-scale`: the token editor stays usable at roughly 790 rows.

## What Changes

- **A generated inventory.** `scripts/mapping/nextcloud-variables.json` lists every custom property a Nextcloud release declares or reads, and every `--cn-*` property the shared library uses. Each entry records its class, the component that owns it, the selectors it is declared on, its stock value per built-in theme, and what thematiq does with it.
- **An extractor.** `scripts/inventory/extract-nextcloud-variables.mjs` builds that file from a Nextcloud release directory and a `@conduction/nextcloud-vue` build. The Nextcloud version and the library version are stamped in the file.
- **Exclusions carry a reason.** Icon images, properties set at runtime by JavaScript, and keyword-only switches are listed with `excluded` and a reason. They are part of the inventory, so excluding one is a visible decision.
- **A guard that fails both ways.** A unit test fails when an inventory entry has no status, when thematiq maps a name the inventory does not know, and when the entry count drops without a recorded removal.
- **The mapping documentation is generated.** `mappings.md` is written from the inventory, so the table and the file cannot disagree.

## Capabilities

### New Capabilities

- `nextcloud-variable-inventory`: the generated, versioned inventory of every Nextcloud and Conduction custom property, and the guard that keeps thematiq's coverage claim checkable.

### Modified Capabilities

- `nextcloud-variable-mapping`: "Complete Nextcloud Variable Audit" and "Mappings Documentation" move from a hand-kept comment list to the generated inventory, and widen from the 111 theming variables to every class above.

## Impact

- New: `scripts/inventory/extract-nextcloud-variables.mjs`, `scripts/mapping/nextcloud-variables.json`, a vitest guard under `tests/js/`, `npm run inventory:extract` and `npm run test:inventory`.
- Changed: `mappings.md` becomes generated output; `css/systems/nldesign/overrides.css` comments stay, and the guard cross-checks them against the inventory.
- No runtime change. Nothing is injected, and no instance renders differently.
- Other Conduction apps are unaffected. The `--cn-*` names are read, never written.
