# Tasks: quick-actions-place

Nothing here is built until this change and its boards (ConductionNL/design-system, branch
`design/thematiq-quick-actions-place`) are merged. Copy and placement follow the boards.

## 1. Server

- [ ] 1.1 `lib/Service/AppMenuSettings.php`: `quickActionsPlace` => `topBar` in `DEFAULTS`,
      `['topBar', 'appsMenu']` in `CHOICES`, not in `PERSONAL`. REQ-TQQP-001.
- [ ] 1.2 `lib/Service/AppMenuDialogLabels.php`: the labels, help texts and notes of `design.md`.
      REQ-TQQP-002, REQ-TQQP-003.
- [ ] 1.3 `lib/Settings/Admin.php`: the administrator's effective `quickActionsPlace`,
      `showCreateActions` and `style` for the admin page. REQ-TQQP-002.
- [ ] 1.4 PHPUnit `tests/Unit/Service/AppMenuSettingsTest.php` (shipped value, unknown value refused,
      null follows the default, reset of the defaults removes it) and
      `tests/Unit/Controller/AppMenuSettingsControllerTest.php` (a person gets 400). REQ-TQQP-001.

## 2. Scripts

- [ ] 2.1 `js/app-menu.js`: `place` on `window.thematiqCreateActions`; the Create row unchanged in both
      places. REQ-TQQP-004.
- [ ] 2.2 `js/header-create.js`: draw Quick add only when the shared actions are on and `place` is
      `topBar`. REQ-TQQA-001.
- [ ] 2.3 `js/dialogs/menu-settings-dialog.js`: the "Quick actions" `radiogroup` under Show create
      actions in Configuration; editable only for For everyone; disabled while Show create actions is
      off; sent with the For everyone save. REQ-TQQP-003.
- [ ] 2.4 `templates/settings/admin.php`, `js/admin-layout-panel.js`, `js/admin.js`: the radiogroup in the
      Menu card of Layout and options, saved at once through `POST /apps/thematiq/app-menu/defaults`,
      the live region and alert, the disabled states, the session list. REQ-TQQP-002.
- [ ] 2.5 vitest: `headerCreate.spec.js` (no button with `appsMenu`), `appMenuCreate.spec.js` (row in
      both places, `place` shared), `menuSettingsDialog.spec.js` (disabled in Only for me, sent For
      everyone), new `adminLayoutQuickActions.spec.js` (save, failure, disabled states).

## 3. Copy

- [ ] 3.1 l10n: the new strings in English and Dutch; `npm run test:l10n` green.

## 4. End to end

- [ ] 4.1 `tests/e2e/spec-coverage/quick-actions-place.spec.ts` with `@e2e` tags for "The administrator
      moves the quick actions to the apps menu" and "The quick actions are in the apps menu": as admin
      choose In the apps menu on Layout and options, open Mail and check there is no Quick add before
      the bell and the apps menu shows its Create row; put the old value back in a `finally`.
