# Tasks: token-set-licence-gate

## 1. Declare

- [x] 1.1 `lib/Service/RestrictedSetGate.php`: the licence a shipped set declares, its text beside the
      set, restricted unless declared open, the confirmation per set, `assertMayApply()`.
- [x] 1.2 `TokenSetService::licenceGate()`; each entry of the set list carries `licence` with `confirmed`.
- [x] 1.3 `tests/Unit/Service/RestrictedSetGateTest.php`.

## 2. Refuse on every path

- [x] 2.1 `ActiveTokenSetService::switchTo()` (settings page, command line, planned switch).
- [x] 2.2 `GroupThemingService::validateEntry()` (mapping and delegated choice).
- [x] 2.3 `ThemePreviewService`: start, publish, and the preview that is drawn.
- [x] 2.4 `ScheduledSwitchService::create()` and the reason when a due switch is refused.
- [x] 2.5 `AppBrandService::setBrand()`.
- [x] 2.6 `ConfigBundleService`: active set, planned switches and app brands of a bundle.
- [x] 2.7 Controllers answer 403 with `restricted_set_not_confirmed`.
- [x] 2.8 `tests/Unit/Service/RestrictedSetWiringTest.php`: each caller refuses and then accepts.
      Removing the gate call from any one caller turns it red (checked per caller).
- [x] 2.9 `tests/Unit/Controller/RestrictedSetRequestPathTest.php`: the delivered status through
      Nextcloud's Dispatcher, with the OCS 403-to-200 control.

## 3. Confirm and withdraw

- [x] 3.1 `lib/Service/TokenSetLicenceService.php`: confirm, withdraw (refused while applied), where a
      set is applied, sets awaiting confirmation; audit entries and log lines.
- [x] 3.2 `lib/Controller/TokenSetLicenceController.php` and four routes.
- [x] 3.3 `ThemingAuditService`: `restricted_set_confirmed`, `restricted_set_confirmation_withdrawn`.
- [x] 3.4 `tests/Unit/Service/TokenSetLicenceServiceTest.php`, `tests/Unit/Controller/TokenSetLicenceControllerTest.php`.

## 4. Show

- [x] 4.1 `js/admin-set-licence.js`, the block in `templates/settings/admin.php`, styles in `css/admin.css`.
- [x] 4.2 `js/admin.js` names the confirmation when the server refuses a set or a preview.
- [x] 4.3 Texts in Dutch and English; every locale file has the keys.
- [x] 4.4 `tests/vitest/admin-set-licence.spec.js`.
- [x] 4.5 `docs/features/token-set-licences.md`.
- [ ] 4.6 The TqTokenSet board shows the block (board drift: paired design-system PR, not opened yet).
- [ ] 4.7 Browser check of the block with a restricted shipped set (live pass, decision 139; needs the
      change that marks the shipped sets).
