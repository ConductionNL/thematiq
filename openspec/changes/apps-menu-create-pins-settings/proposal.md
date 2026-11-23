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
- **Menu settings.** A gear button "Menu settings" replaces "Manage apps" in the footer. Its dialog
  sets the menu style (workplace or classic Nextcloud), the pinned apps, the order and visibility of
  the menu's entries, and switches for external apps and create actions. Admins also see a
  "Manage apps" link. Every value is checked on the server before it is stored.

## Not in this change

- Picking a template: a chip creates an empty file, as the Files "New" menu does without templates.
- Choosing a folder: new files land in the home folder.

## Impact

- `js/app-menu.js`, `css/header-workplace.css`
- `lib/Service/AppMenuListState.php` and the menu's initial state
- l10n: the new strings in English and Dutch
