---
kind: code
depends_on: [quick-actions-toggles]
---

# The contacts menu in the top bar switches off

## Why

Ruben's review of thematiq on a reference instance (27 November 2026): Nextcloud's contacts menu, the
button next to the notifications bell, is not something every organisation wants in the top bar.
Ruben decided it can be switched off completely, the same way as the Create button: every person
decides for themselves, with the administrator setting the default for everyone.

## What changes

- **One switch, on by default.** `showContactsMenu` for Nextcloud's contacts menu in the top bar: the
  workplace top bar and Nextcloud's own header with the classic menu (Ruben, 27 November 2026). On is
  today's bar.
- **Off removes it.** The contacts menu button is not drawn: no focusable element, nothing in the
  accessibility tree, and Tab goes from the bell straight on to the person's menu. Contacts stay
  reachable through the Contacts app and wherever Nextcloud offers them.
- **A person decides.** `showContactsMenu` is a personal key, like `showCreateButton` and
  `showCreateRow` from quick-actions-toggles. In Menu settings, tab Configuration, it sits with the two
  create switches and stays editable in Only for me.
- **The administrator sets the default.** In the Menu card of Layout and options on thematiq's admin
  page, next to the create switches, and in Menu settings with For everyone. A person's own value wins.

## Not in this change

- The contacts menu's content or behaviour while it is on.
- The Contacts app itself.

## Boards

Merged in ConductionNL/design-system#1220: the switch next to the create switches on
werkplek/AppMenuConfiguratie and thematiq/TqLayout. Boards are canon. See `screens.md`.

## Impact

- `lib/Service/AppMenuSettings.php`: `showContactsMenu` in `DEFAULTS` (true), `SWITCHES` and `PERSONAL`
- `lib/Service/AppMenuService.php`: the conditional stylesheet when the effective value is off, in
  either menu style
- `css/top-bar-no-contacts.css`: new, takes the contacts menu out of the bar
- `lib/Service/AppMenuDialogLabels.php`: label and help in English and Dutch
- `lib/Settings/Admin.php`, `templates/settings/admin.php`, `js/admin-layout-panel.js`: the switch in
  the Menu card
- `js/dialogs/menu-settings-dialog.js`: the switch, editable in Only for me
- tests: PHPUnit `AppMenuSettingsTest`, `AppMenuSettingsControllerTest`, `AppMenuServiceTest`; vitest
  `menuSettingsDialog.spec.js`, `adminLayoutCreateSwitches.spec.js`; e2e
  `tests/e2e/spec-coverage/top-bar-contacts-switch.spec.ts`
- l10n: the new strings in English and Dutch
