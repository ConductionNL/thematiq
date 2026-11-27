---
kind: code
depends_on: []
---

# The quick actions switch on and off in two places

## Why

Ruben's review of thematiq on a reference instance (27 November 2026, item 3): the quick actions sit
in two places, the Create button with the plus in the top bar next to the bell, and the Create row at
the bottom of the apps menu. Today one administrator switch (`showCreateActions`) turns both on or off
together. Ruben decided that each place gets its own switch, and that every person decides for
themselves, with the administrator setting the default for everyone.

## What changes

- **Two switches, both on by default.** `showCreateButton` for the Quick add button in the top bar,
  `showCreateRow` for the Create row in the apps menu, above the menu footer (Drag an app to reorder,
  and Menu settings). Both on is today's behaviour. A person can have both, either or neither.
- **A person decides.** Both switches are personal keys, like the order, pins and hidden apps. In Menu
  settings, tab Configuration, they stay editable in Only for me, while the rest of that tab stays the
  administrator's. The server accepts them from a person.
- **The administrator sets the default.** In the Menu card of Layout and options on thematiq's admin
  page, and in Menu settings with For everyone. A person's own value wins.
- **`showCreateActions` goes.** An administrator who switched it off keeps both new switches off: the
  stored value moves once into both keys and is removed. A save that still sends the old key sets both.
- **The Create button is on every page.** Like the apps menu button, the plus with the Create actions
  stands before the bell on every Nextcloud page: Files, Settings, Mail and every other app. Mail,
  Calendar and Deck no longer differ from the rest; they get the same button. Drawing it asks the
  server nothing; the Create actions load on the first open.

## Not in this change

- Create entries of one app's own, such as a new message or event. The button and the row list the
  shared Create actions only.
- Changing what the Create actions do. The list, the order and the file they make stay as
  apps-menu-create and workplace-quick-add define them.
- The walkthrough. Its create step is already optional and skipped when the Create row is not drawn.

## Boards

ConductionNL/design-system#1216, revised to two switches: werkplek/AppMenuSnelacties,
thematiq/TqLayout and werkplek/AppMenuConfiguratie. Boards are canon. See `screens.md`.

## Impact

- `lib/Service/AppMenuSettings.php`: the two keys in `DEFAULTS`, `SWITCHES` and `PERSONAL`;
  `showCreateActions` removed; the one-time move and the old key read on a save
- `lib/Service/SetupRequest.php`: `MENU_KEYS` names the two new keys
- `lib/Service/AppMenuDialogLabels.php`: labels and help texts in English and Dutch
- `js/app-menu.js`: the row follows `showCreateRow`; the actions are shared while either switch is on
- `js/header-create.js`: Quick add on every page, following `showCreateButton`; the app list goes
- `js/dialogs/menu-settings-dialog.js`: the two switches, editable in Only for me
- `templates/settings/admin.php`, `js/admin-layout-panel.js`, `js/admin.js`: the switches in the Menu card
- tests: PHPUnit `AppMenuSettingsTest`, `AppMenuSettingsControllerTest`, `SetupRequestTest`; vitest
  `headerCreate.spec.js`, `appMenuCreate.spec.js`, `menuSettingsDialog.spec.js`, new
  `adminLayoutCreateSwitches.spec.js`; e2e `tests/e2e/spec-coverage/quick-actions-toggles.spec.ts`
- l10n: the new strings in English and Dutch
