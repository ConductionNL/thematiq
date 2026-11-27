---
kind: code
depends_on: []
---

# The place of the quick actions

## Why

Ruben's review of thematiq on a reference instance (27 November 2026, item 3): the Create button with
the plus, the Quick add menu on the right of the workplace top bar next to the bell, sits in one fixed
place. The review asks for that place to be a setting: in the top bar, as now, or in the apps menu.
Today an administrator can only switch the Create actions off altogether (`showCreateActions`), and
that takes them out of the apps menu too.

## What changes

- **A new menu setting, `quickActionsPlace`.** Two values: `topBar`, the shipped default, and
  `appsMenu`. It is an administrator's setting, kept with the other menu settings as app value
  `thematiq` / `menu.quickActionsPlace`.
- **In the top bar (default).** Nothing changes. Quick add stands before the bell on Mail, Calendar and
  Deck, as the MlInbox, ClWeek and TbBoard boards draw it, and the apps menu keeps its Create row.
- **In the apps menu.** Quick add is not drawn on any page. The quick actions are the Create row at the
  bottom of the apps menu, above the menu footer (Drag an app to reorder, and Menu settings), as the
  AppMenuSnelacties board draws it.
- **The administrator sets it on thematiq's admin page.** In Layout and options, the Menu card gets a
  choice "Quick actions" with "In the top bar" and "In the apps menu". The same choice shows in Menu
  settings, tab Configuration, under the Create switch, for an administrator choosing For everyone.
- **A person cannot override it.** It follows the pattern of the existing menu settings: a person sets
  only the order, pins, hidden apps and own external apps. The place of the quick actions belongs with
  the layout choices an administrator makes for everyone, like the menu style and the Create switch.
  In Only for me the choice is shown disabled, with the note that the administrator sets it.
- **Checked on the server.** A value other than `topBar` or `appsMenu` refuses the save with 400
  naming the setting. A person who sends it gets 400. Reset to default for everyone removes it.

## Not in this change

- Quick add on other apps than Mail, Calendar and Deck. The boards draw it only there, and the setting
  moves the button out of the bar; it does not add the button elsewhere.
- Changing what the Create actions do. The list, the order and the file they make stay as
  apps-menu-create and workplace-quick-add define them.
- A per-user choice. See above; it can be added later as a personal key if Ruben wants that.
- The first-time setup wizard. It may offer this choice later; `SetupRequest::MENU_KEYS` is unchanged here.

## Boards

ConductionNL/design-system, branch `design/thematiq-quick-actions-place`: AppMenuSnelacties (the apps
menu with the Create row as the place of the quick actions) and the changed settings board with the new
choice. Boards are canon. See `screens.md`.

## Impact

- `lib/Service/AppMenuSettings.php`: the key in `DEFAULTS`, its `CHOICES`, not in `PERSONAL`
- `lib/Service/AppMenuDialogLabels.php`: the labels and help of the choice, in English and Dutch
- `js/app-menu.js`: `window.thematiqCreateActions` says where the quick actions go
- `js/header-create.js`: no Quick add with `appsMenu`
- `js/dialogs/menu-settings-dialog.js`: the choice in the Configuration tab
- `templates/settings/admin.php`, `js/admin-layout-panel.js`, `js/admin.js`: the choice in the Menu card
  of Layout and options, saved through `POST /apps/thematiq/app-menu/defaults`
- tests: PHPUnit `AppMenuSettingsTest`, vitest `headerCreate.spec.js`, `menuSettingsDialog.spec.js`,
  a new admin layout test, and `tests/e2e/spec-coverage/quick-actions-place.spec.ts`
- l10n: the new strings in English and Dutch
