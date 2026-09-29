# Tasks: restore an earlier version

Tick a box when the work is merged to `development`.

## 1. Versions

- [ ] 1.1 Add `lib/Service/ThemeVersionService.php` (`capture()`, `list()`, `get()`, cap at 50 versions and 20 MB). Verify: `tests/Unit/Service/ThemeVersionServiceTest.php` covers capture, ordering, the count cap, the size cap and a failing app data folder.
- [ ] 1.2 Call `capture()` from `ThemingAuditService::log()` and add `versionId` to the entry. Verify: `ThemingAuditServiceTest` asserts the entry carries the id and that a failing capture still writes the entry.

## 2. Restore

- [ ] 2.1 Version list, preview and restore endpoints on `AuditController`, routes in `appinfo/routes.php`. Verify: `tests/Unit/Controller/AuditControllerTest.php` for the three endpoints, including a non-admin getting 403 and a restore of an unknown id getting 404.
- [ ] 2.2 Restore through `ConfigBundleService::import()` (dry run first), audit action `version_restored`. Verify: unit test that a restore writes exactly one `version_restored` entry and captures a new version.
- [ ] 2.3 `occ nldesign:config:versions` and `occ nldesign:config:restore <id> [--dry-run]`. Verify: command tests in `tests/Unit/Command/`.

## 3. Settings page

- [ ] 3.1 Restore action per audit row that has a version, confirmation dialog listing the dry-run changes. Verify: Playwright scenario "An administrator restores the version from before a bad change".
- [ ] 3.2 Keyboard: the restore button and dialog are reachable and closable by keyboard, focus returns to the row. Verify: Playwright keyboard run.

## 4. Quality

- [ ] 4.1 l10n: new strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run test:l10n`.
- [ ] 4.2 Docs: a "Restore an earlier version" section in `docs/`. Verify: the docs build.
- [ ] 4.3 Test a restore with an incomplete custom token set and with dark mode on. Verify: manual check recorded in the PR.
