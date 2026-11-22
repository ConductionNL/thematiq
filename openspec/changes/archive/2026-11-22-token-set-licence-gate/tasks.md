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
- [x] 2.9 `tests/Server/Controller/RestrictedSetRequestPathTest.php`: the delivered status through
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
- [x] 4.6 The TqTokenSet board shows the block (board drift: paired design-system PR, not opened yet).
      20 Nov: the paired PR is open, https://github.com/ConductionNL/design-system/pull/1188. It
      redraws the board's licence part from `js/admin-set-licence.js` and the shipped nl/en strings
      (the 12 Nov drawing showed a flow that was never built). design-system integrates on `main`
      only, which the cloud builder may not merge into, so it waits on a human merge; tick then.
      22 Nov: merged by the coordinator for Ruben (design-system #1188, c40ab19c). Checked locally
      before the merge: `board_fresh.py` fresh, `build.py --only TqTokenSet` reproduces the board,
      `i18n.py check` 0 errors, screens unit tests 23/23, and a full screenshot shows the licence block.
- [x] 4.7 Browser check of the block with a restricted shipped set (live pass, decision 139; needs the
      change that marks the shipped sets).
      Live pass 17 Nov 2026 (NC 34.0.6): the block lists the 44 restricted sets as "Not confirmed";
      Gemeente Epe opens with the warning naming Gemeente Epe, the source
      `@nl-design-system-unstable/epe-design-tokens 1.0.3` and the full licence text; choosing
      `amsterdam` in the dropdown shows "Confirm first ... Go to the confirmation" and
      `POST /settings/tokenset` answers 403 `restricted_set_not_confirmed`; a confirmed set offers
      "Withdraw confirmation", refused while applied ("This house style is still in use"). Found: the
      apply dialog answered a refusal with "Failed to apply token set."; it now names the
      confirmation (tests/vitest/admin-apply-restricted-set.spec.js).
