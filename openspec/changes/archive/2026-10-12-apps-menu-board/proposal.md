---
kind: code
---

## Why

Ruben, 11 October 2026: "we also need a design for the apps menu". The grid button in the
workplace top bar opened Nextcloud's apps menu: four columns of round primary-blue icons,
underlined labels that broke mid-word ("Zaak Afhan-del App"), a scrollbar inside the grid, and the
last row cut off. The AppMenu board draws a 640px menu: a section of the organisation's own apps
("Werk") with a description under each name and a link beside the heading, every other app under
"Nextcloud" in quiet chips, and a footer with the keyboard hint and "Apps beheren".

## What changes

- **`css/header-workplace.css`** (loaded with the workplace header style only) draws Nextcloud's
  menu as the board: 640px, no inner scrollbar (the menu scrolls as a whole on a short window),
  names never underlined and never broken mid-word, chips in the background-hover colour and, once
  the script has run, icons coloured by tokens through a mask. The app on screen takes the
  accent's light wash and an accent circle.
- **An `app_menu` block per token set** in token-sets.json: `groups` (each a `title`, an optional
  `link` `{label, url}` and `apps`, a list of app ids with an optional `description`),
  `others_title`, `hint` and `manage_label`. Texts follow the login copy's rules. Zuiddrecht ships
  the board's section of eight apps. Which apps belong together is not something Nextcloud knows,
  so it is configuration, not code.
- **An administrator override** in the app config key `app_menu_config`, key by key.
- **`AppMenuService` and `js/app-menu.js`.** The service runs with the workplace header style. The
  script arranges the open grid by CSS `order` and leaves the tiles where Nextcloud put them in the
  DOM, so Nextcloud's keyboard handling keeps working. "More apps" and the app store tile become
  the footer's "Apps beheren" link, with the address of Nextcloud's own tile.

**Out of scope:** the arrow keys still move through Nextcloud's own order of the tiles, not the
visual sections. Changing that needs Nextcloud's menu to know about sections.

## Capabilities

### Modified capabilities
- `workplace-layout`: the workplace header draws the apps menu of the board.

## Impact
- `lib/Service/AppMenuService.php` (new), `lib/Service/CopyValues.php` (the same file as in
  not-found-page-board), `lib/Service/CssInjectionService.php`, `js/app-menu.js` (new),
  `css/header-workplace.css`, `token-sets.json`, `.gitignore`.
- Sets without the workplace header style: unchanged; the sheet and the script are not loaded.
