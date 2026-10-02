## 1. Extractor

- [ ] 1.1 Write `scripts/inventory/extract-nextcloud-variables.mjs` that reads a Nextcloud release directory and the four theming stylesheets, and verify it reports 111 theme entries for Nextcloud 34.0.0.12
- [ ] 1.2 Add the class rules from design.md and a forced-class override block, and verify the per-class totals match the measured table in proposal.md within 2%
- [ ] 1.3 Read `--cn-*` names from a `@conduction/nextcloud-vue` build, and verify 54 `conduction` entries for the pinned library version
- [ ] 1.4 Record owner component, declaring selectors and per-theme stock values per entry, and verify `--color-main-text` carries distinct light and dark values
- [ ] 1.5 Strip CSS comments and self-references before classifying, with a unit test that a commented-out mapping is not counted

## 2. Inventory and status

- [ ] 2.1 Generate and commit `scripts/mapping/nextcloud-variables.json` with the Nextcloud and library versions stamped, and verify `npm run inventory:extract` reproduces it byte for byte
- [ ] 2.2 Create `scripts/mapping/variable-status.json` seeded from today's state (66 mapped, of which 14 through Nextcloud's own theming, and 45 excluded: 35 with the reasons in `overrides.css`, 10 only re-scoped per component), and verify every theme entry has a status
- [ ] 2.3 Mark icon, runtime and unread entries `excluded` with a reason, and verify no entry is left without a status

## 3. Guard

- [ ] 3.1 Add `npm run test:inventory` (vitest) failing on: missing status, excluded without reason, unknown mapped name, stale status for a missing name, and verify each case with a fixture that fails first
- [ ] 3.2 Add the both-ways baseline count per class, and verify a fixture that removes one mapping fails and names it
- [ ] 3.3 Add the drift check comparing the committed inventory with a fresh extraction, and verify a hand edit fails with the first differing entry
- [ ] 3.4 Wire `test:inventory` into the `Code Quality` workflow and `npm run lint`, and verify it runs in a pushed branch's checks

## 4. Documentation

- [ ] 4.1 Generate `mappings.md` from the inventory and status files, and verify a lookup of `--color-primary-element` shows class, status, token and owner
- [ ] 4.2 Add a developer page under `docs/` explaining how to regenerate the inventory after a Nextcloud release, and verify the docs site builds

## 5. Verification

- [ ] 5.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` and `npm run lint` once before push, and record both exit codes in the PR body

Reminders, not tasks:
- ADR-005: the extractor reads local files only; no network, no secrets.
- ADR-009: the regenerate page is the documentation deliverable.
- ADR-010 and ADR-011: no UI and no schema in this change.
- Inherited findings on untouched lines go in one sentence in the PR body.
