# Tasks: set the active theme from the command line

Tick a box when the work is merged to `development`.

## 1. Shared write path

- [x] 1.1 Move the body of `SettingsController::setTokenSet()` into `TokenSetService::activate($id, $actor)`; the controller calls it. Verify: `SettingsControllerTest` stays green and a new `TokenSetServiceTest` case covers invalid id, unchanged id (no audit entry) and a switch.

## 2. Commands

- [x] 2.1 `nldesign:theme:list` and `nldesign:theme:get`. Verify: `tests/Unit/Command/ThemeListTest.php` and `ThemeGetTest.php` assert the table columns and the group mapping output.
- [x] 2.2 `nldesign:theme:set <token-set> [--sync-core] [--dry-run]`, registered in `appinfo/info.xml`. Verify: `tests/Unit/Command/ThemeSetTest.php` for success, unknown id (non-zero exit), `--dry-run` (no write) and `--sync-core` (calls `ThemingService`).

## 3. Quality

- [x] 3.1 Docs: the three commands in `docs/` with a scripting example. Verify: the docs build.
- [ ] 3.2 Switch to an incomplete custom set from the command line and check the page falls back to defaults. Verify: manual check recorded in the PR.

## Notes at archive (30 Sep 2026)

- 1.1 was already met at HEAD: `ActiveTokenSetService::switchTo()` (added by `apply-scheduled-theme-switch`, #750) is the one write path the controller uses; the commands call it too. The no-audit-on-unchanged rule lives in `ThemeSet`, so the web path keeps its behaviour.
- 2.2 `--sync-core` reuses `ScheduledCoreThemingSync::sync()`, the dialog-free sync the scheduled switch uses, instead of calling `ThemingService` directly.
- 3.2 is a live check owed on a running instance (recipe in PR body); it stays open.
