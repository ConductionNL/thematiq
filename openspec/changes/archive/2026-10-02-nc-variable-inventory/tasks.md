## 1. Extractor

- [x] 1.1 Write `scripts/inventory/extract-nextcloud-variables.mjs` that reads a Nextcloud release directory and the four theming stylesheets, and verify it reports 111 theme entries for Nextcloud 34.0.0.12
- [x] 1.2 Add the class rules from design.md and a forced-class override block, and verify the per-class totals match the measured table in proposal.md within 2%
  - Measured on 34.0.0.12: theme 111, icon 485, runtime 57, component 377, slot 65, unread 9. Two classes are outside 2% of proposal.md. icon is 438 by name prefix plus 47 pdf.js and viewer properties whose value is a `url()`, which the design table also classes `icon`. slot is 65 against 140: 18 Vue `v-bind()` hashes (`--a475b540`) that would otherwise read as slots are classed `runtime`, and no source for the other 57 was found in core/, apps/ or dist/, PHP templates included. component plus runtime is 434 against 438.
- [x] 1.3 Read `--cn-*` names from a `@conduction/nextcloud-vue` build, and verify 54 `conduction` entries for the pinned library version
  - 52 `conduction` entries for the pinned 2.57.1, against 54 in proposal.md.
- [x] 1.4 Record owner component, declaring selectors and per-theme stock values per entry, and verify `--color-main-text` carries distinct light and dark values
- [x] 1.5 Strip CSS comments and self-references before classifying, with a unit test that a commented-out mapping is not counted

## 2. Inventory and status

- [x] 2.1 Generate and commit `scripts/mapping/nextcloud-variables.json` with the Nextcloud and library versions stamped, and verify `npm run inventory:extract` reproduces it byte for byte
- [x] 2.2 Create `scripts/mapping/variable-status.json` seeded from today's state (66 mapped, of which 14 through Nextcloud's own theming, and 45 excluded: 35 with the reasons in `overrides.css`, 10 only re-scoped per component), and verify every theme entry has a status
  - Measured, not copied: 64 mapped (52 set by thematiq's stylesheets, 12 that Nextcloud derives, found by applying two different primary and background colours to a stock 34.0.0.12 container and diffing the served theme stylesheets, then following `var()` references), 47 excluded. proposal.md says 66 and 45; the two-variable difference sits in the derived group.
- [x] 2.3 Mark icon, runtime and unread entries `excluded` with a reason, and verify no entry is left without a status

## 3. Guard

- [x] 3.1 Add `npm run test:inventory` (vitest) failing on: missing status, excluded without reason, unknown mapped name, stale status for a missing name, and verify each case with a fixture that fails first
- [x] 3.2 Add the both-ways baseline count per class, and verify a fixture that removes one mapping fails and names it
- [x] 3.3 Add the drift check comparing the committed inventory with a fresh extraction, and verify a hand edit fails with the first differing entry
- [x] 3.4 Wire `test:inventory` into the `Code Quality` workflow and `npm run lint`, and verify it runs in a pushed branch's checks
  - Wired as a named `frontend-checks` leg and as `npm run lint`. It also runs inside "Frontend Tests (unit)", which collects tests/vitest/. The run on the pushed branch is the PR's own check list.

## 4. Documentation

- [x] 4.1 Generate `mappings.md` from the inventory and status files, and verify a lookup of `--color-primary-element` shows class, status, token and owner
- [x] 4.2 Add a developer page under `docs/` explaining how to regenerate the inventory after a Nextcloud release, and verify the docs site builds
  - docs/reference/nextcloud-variable-inventory.md.

## 5. Verification

- [x] 5.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` and `npm run lint` once before push, and record both exit codes in the PR body

Reminders, not tasks:
- ADR-005: the extractor reads local files only; no network, no secrets.
- ADR-009: the regenerate page is the documentation deliverable.
- ADR-010 and ADR-011: no UI and no schema in this change.
- Inherited findings on untouched lines go in one sentence in the PR body.
