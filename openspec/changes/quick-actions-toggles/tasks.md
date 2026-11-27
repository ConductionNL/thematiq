# Tasks: quick-actions-toggles

Nothing here is built until this change and its boards (ConductionNL/design-system#1216) are merged.
Copy and placement follow the boards.

## 1. Server

- [ ] 1.1 `lib/Service/AppMenuSettings.php`: `showCreateButton` and `showCreateRow` in `DEFAULTS`
      (true), `SWITCHES` and `PERSONAL`; remove `showCreateActions`. REQ-TQQT-001.
- [ ] 1.2 `lib/Service/AppMenuSettings.php`: the one-time move of `menu.showCreateActions` into both
      keys, run where the external apps move runs; a defaults save with `showCreateActions` sets both
      and stores nothing under the old key. REQ-TQQT-002.
- [ ] 1.3 `lib/Service/SetupRequest.php`: `MENU_KEYS` names the two new keys. REQ-TQQT-002.
- [ ] 1.4 `lib/Service/AppMenuDialogLabels.php`: the labels and notes of `design.md`. REQ-TQQT-003.
- [ ] 1.5 `lib/Settings/Admin.php`: the administrator's two values and the menu style for the admin
      page. REQ-TQQT-003.
- [ ] 1.6 PHPUnit `AppMenuSettingsTest` (defaults, a person's value wins, the move, the old key on a
      save, reset), `AppMenuSettingsControllerTest` (a person saves both keys), `SetupRequestTest`.

## 2. Scripts

- [ ] 2.1 `js/app-menu.js`: the row follows `showCreateRow`; share the actions while either switch is
      on; no templates API call with both off. apps-menu-create.
- [ ] 2.2 `js/header-create.js`: Quick add on every page with the workplace top bar, following
      `showCreateButton`; remove `APPS` and the open-app check; no request until the first open.
      REQ-TQQA-003.
- [ ] 2.3 `js/dialogs/menu-settings-dialog.js`: the two switches in Sections, editable in Only for me
      and saved as personal keys; the rest of Configuration stays locked. apps-menu-settings.
- [ ] 2.4 `templates/settings/admin.php`, `js/admin-layout-panel.js`, `js/admin.js`: the two switches
      in the Menu card of Layout and options, saved at once, live region, alert, classic disables,
      session list. REQ-TQQT-003.
- [ ] 2.5 vitest: `headerCreate.spec.js`, `appMenuCreate.spec.js` (shares with the row off),
      `menuSettingsDialog.spec.js` (switches editable in Only for me), new
      `adminLayoutCreateSwitches.spec.js`.

## 3. Copy

- [ ] 3.1 l10n: the new strings in English and Dutch; `npm run test:l10n` green.

## 4. End to end

- [ ] 4.1 `tests/e2e/spec-coverage/quick-actions-toggles.spec.ts` with `@e2e` tags for "Both on", "Only
      the Create button", "Only the Create row", "Neither", "The administrator switches the Create
      row off for everyone", "Files has the button" and "Settings has the button": set the person's own
      keys through `POST /apps/thematiq/app-menu/settings`, open Files and Settings, check Quick add before the bell and the Create row in the open apps menu; put every
      value back in a `finally`.
