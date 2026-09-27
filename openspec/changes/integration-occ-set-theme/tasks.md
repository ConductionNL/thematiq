# Tasks: set the active theme from the command line

Tick a box when the work is merged to `development`.

## 1. Shared write path

- [ ] 1.1 Move the body of `SettingsController::setTokenSet()` into `TokenSetService::activate($id, $actor)`; the controller calls it. Verify: `SettingsControllerTest` stays green and a new `TokenSetServiceTest` case covers invalid id, unchanged id (no audit entry) and a switch.

## 2. Commands

- [ ] 2.1 `nldesign:theme:list` and `nldesign:theme:get`. Verify: `tests/Unit/Command/ThemeListTest.php` and `ThemeGetTest.php` assert the table columns and the group mapping output.
- [ ] 2.2 `nldesign:theme:set <token-set> [--sync-core] [--dry-run]`, registered in `appinfo/info.xml`. Verify: `tests/Unit/Command/ThemeSetTest.php` for success, unknown id (non-zero exit), `--dry-run` (no write) and `--sync-core` (calls `ThemingService`).

## 3. Quality

- [ ] 3.1 Docs: the three commands in `docs/` with a scripting example. Verify: the docs build.
- [ ] 3.2 Switch to an incomplete custom set from the command line and check the page falls back to defaults. Verify: manual check recorded in the PR.
