---
kind: code
---

# The apps menu: create actions, pinned apps and menu settings

## Why

The werkplek app menu boards were redrawn after the Nextcloud 33 app menu (Ruben, 23 Nov 2026,
design-system PR #1197). The boards are the canon, and the menu in the workplace header follows them.

## What changes

- **Title and chevron.** An "Apps" title opens the panel. The All apps / Hide apps toggle carries a
  chevron that turns when the rest unfolds.
- **Create row.** Above the footer, a "Create" row offers New document, New spreadsheet, New
  presentation and New whiteboard, then More. The actions come from the Files templates API
  (`/ocs/v2.php/apps/files/api/v1/templates`), the same list the Files "New" menu shows. A kind that no
  installed app offers is left out. More unfolds every creator and Nextcloud's own navigation actions.
  A chip makes an empty file in your home folder through the templates API and opens it.
- **Pinned apps.** Drag an app onto the top bar to pin it next to the grid button. Drag a pinned app off
  the bar to unpin it. Alt+P pins or unpins the focused app. Pins are stored per person on the server.
- **Menu settings.** A gear button "Menu settings" replaces "Manage apps" in the footer. Its dialog has
  the tabs Menu items, Configuration and Theming (design-system #1201). Administrators set the defaults
  for everyone; a person sets their own order, pins, hidden apps and external apps. External apps get
  their own form with an icon library and an upload. Every value is checked on the server.

## Not in this change

- Picking a template: a chip creates an empty file, as the Files "New" menu does without templates.
- Choosing a folder: new files land in the home folder.

## Impact

- `js/app-menu.js`, `css/header-workplace.css`
- `lib/Service/AppMenuListState.php` and the menu's initial state
- new `lib/Service/AppMenuSettings.php`, `AppMenuExternalApp.php`, `AppMenuIcons.php`, `AppMenuDialogLabels.php`,
  `lib/Controller/AppMenuSettingsController.php` and the `/app-menu/settings`, `/defaults`, `/icons` routes
- new `js/dialogs/menu-settings-dialog.js`, `css/menu-settings-dialog.css`; the menu's CSS moves to `css/app-menu.css`
- `token-sets.json`: payoffs for the Nextcloud apps in Zuiddrecht
- new `lib/Service/AppMenuPins.php`, `lib/Service/AppMenuKeys.php`, `lib/Controller/AppMenuPinsController.php`,
  route `POST /app-menu/pins`
- l10n: the new strings in English and Dutch
