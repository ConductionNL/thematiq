# Tasks: shipped-set-licences

## 1. Audit

- [x] 1.1 Read the licence of every shipped set at its source (nl-design-system/themes at 9cfc4c5a, the
      organisation packages read on 12 October, `@gemeente-rotterdam/design-tokens@1.1.0`, the vendored
      Den Haag packages). Table in `docs/reference/token-set-licences.md`.
- [x] 1.2 `token-sets.json`: a `licence` block for 48 sets; `css/tokens/<id>.LICENSE.md` for the 44 restricted.
- [x] 1.3 `tests/Unit/ShippedSetLicencesTest.php`: the restricted list, every converted set declares a
      licence, every restricted set ships its text, the table lists every declared set.
- [x] 1.4 `scripts/sync-upstream-tokens.mjs` writes the converted version into `licence.version` as well
      as `upstreamVersion`; `tests/vitest/syncUpstreamTokens.spec.js` (mergeEntry).

## 2. Upgrade

- [x] 2.1 `lib/Repair/RecordRestrictedSetsAwaitingConfirmation.php`, last in `<post-migration>`.
- [x] 2.2 `TokenSetLicenceService::recordAwaitingConfirmation()`; a confirmation ends the record.
- [x] 2.3 `tests/Unit/Repair/RecordRestrictedSetsAwaitingConfirmationTest.php`, started from info.xml.
- [x] 2.4 Version 1.2.28; upgrade notes in `CHANGELOG.md`.
- [x] 2.5 Checked on a Nextcloud 34 instance: `occ upgrade` with `epe` applied kept `epe` and recorded it.
- [ ] 2.6 Browser check of the notice and the confirmation (live pass, decision 139).
