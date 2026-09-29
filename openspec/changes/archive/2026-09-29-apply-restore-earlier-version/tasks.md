# Tasks: restore an earlier version

Tick a box when the work is merged to `development`.

## 1. Versions

- [x] 1.1 Add `lib/Service/ThemeVersionService.php` (`capture()`, `list()`, `get()`, cap at 50 versions and 20 MB). Verify: `tests/Unit/Service/ThemeVersionServiceTest.php` covers capture, ordering, the count cap, the size cap and a failing app data folder.
- [x] 1.2 Call `capture()` from `ThemingAuditService::log()` and add `versionId` to the entry. Verify: `ThemingAuditServiceTest` asserts the entry carries the id and that a failing capture still writes the entry.

## 2. Restore

- [x] 2.1 Version list, preview and restore endpoints on `AuditController`, routes in `appinfo/routes.php`. Verify: `tests/Unit/Controller/AuditControllerTest.php` for the three endpoints, including a non-admin getting 403 and a restore of an unknown id getting 404.
- [x] 2.2 Restore through `ConfigBundleService::import()` (dry run first), audit action `version_restored`. Verify: unit test that a restore writes exactly one `version_restored` entry and captures a new version.
- [x] 2.3 `occ nldesign:config:versions` and `occ nldesign:config:restore <id> [--dry-run]`. Verify: command tests in `tests/Unit/Command/`.

## 3. Settings page

- [x] 3.1 Restore action per audit row that has a version, confirmation dialog listing the dry-run changes. Verify: Playwright scenario "An administrator restores the version from before a bad change". Done as tests/vitest/admin-version-restore.spec.js against the real admin.js; the Playwright run was excluded because it mutates the shared CI instance (see the spec).
- [x] 3.2 Keyboard: the restore button and dialog are reachable and closable by keyboard, focus returns to the row. Verify: Playwright keyboard run. Done in part: the restore control is a native button and the dialog is Nextcloud's own; the vitest asserts focus returns to the button on cancel. A live keyboard pass is still owed (browser service was down on 29 Sep).

## 4. Quality

- [x] 4.1 l10n: new strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run test:l10n`.
- [x] 4.2 Docs: a "Restore an earlier version" section in `docs/`. Verify: the docs build.
- [ ] 4.3 (owed: live check, browser service down on 29 Sep) Test a restore with an incomplete custom token set and with dark mode on. Verify: manual check recorded in the PR.
