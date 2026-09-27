# Tasks: scheduled theme switch

Tick a box when the work is merged to `development`.

## 1. Service and job

- [ ] 1.1 Extract the token set write from `SettingsController::setTokenSet()` into a service method used by both the controller and the job. Verify: existing `SettingsControllerTest` stays green.
- [ ] 1.2 `lib/Service/ScheduledSwitchService.php`: create (with overlap and existence checks), list, cancel, due-switch resolution. Verify: `tests/Unit/Service/ScheduledSwitchServiceTest.php` with a fixed `ITimeFactory`.
- [ ] 1.3 `lib/BackgroundJob/ScheduledSwitchJob.php` (300 s, time sensitive), registered in `appinfo/info.xml`. Verify: unit test applies a due switch, reverts at `endAt`, and marks a switch to a deleted set as `failed`.
- [ ] 1.4 Audit action `scheduled_switch_applied`. Verify: `ThemingAuditServiceTest` accepts the action; job test asserts one entry per switch.

## 2. Endpoints and settings page

- [ ] 2.1 `GET/POST /settings/scheduled-switches`, `DELETE /settings/scheduled-switches/{id}`, all `#[AuthorizedAdminSetting]`. Verify: controller unit tests, including overlap refusal (400) and non-admin (403).
- [ ] 2.2 "Planned switches" block under the token set dropdown: set, start, optional end, sync core theming checkbox, list with cancel. Verify: Playwright scenario "An administrator plans a campaign look".

## 3. Bundle

- [ ] 3.1 `config.scheduledSwitches` in `ConfigBundleService::export()` and `import()`, `bundleVersion` bump. Verify: `ConfigBundleServiceTest` round trip and a refused import with an overlapping entry.

## 4. Quality

- [ ] 4.1 l10n en and nl for all new strings. Verify: `npm run test:l10n`.
- [ ] 4.2 Docs: "Plan a theme switch" in `docs/`, including the cron requirement. Verify: the docs build.
- [ ] 4.3 Test a switch to an incomplete token set and in dark mode. Verify: manual check recorded in the PR.
