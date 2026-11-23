---
kind: code
---

# The apps menu as one ordered list, with external apps

## Why

The OpenProject twin shell (Giplat/thematiq, change twin-shell) draws its app switcher as one list.
The first nine apps show, "All apps" unfolds the rest, and a person drags apps into the order they
want. External apps sit under a heading of their own and never show a dead link. Nextcloud's apps
menu in the workplace header should behave the same, so both halves of the shell feel like one
workplace. The coordinator decided the details on Ruben's behalf (22 Nov 2026).

## What changes

- **One ordered list.** The workplace apps menu shows the first nine apps. An "All apps" button
  unfolds the rest and then reads "Hide apps". A token set's `app_menu` groups no longer draw
  headings: they give the default order and each app's second line.
- **Your own order.** Drag an app to another place, or move it with Alt and an arrow key. The order
  is saved in your user settings on the server, so it follows you to every browser.
- **Hover to look, click to pin.** Pointing at the grid button opens the menu after a short pause;
  a click keeps it open until a click outside, Escape or another click on the button. The keyboard
  opens and closes it as before. With reduced motion asked for, the menu opens without animation.
- **External apps.** `openproject-in-the-app-menu` becomes one section "External apps". An
  administrator gives an address per app on the thematiq admin page: OpenProject (its modules, as
  before), Open-Xchange, Element, XWiki, Forgejo and BigBlueButton. Only http and https addresses
  are accepted. An app without an address is left out.

## Not in this change

- A favourites star, or a "+" in the Nextcloud bar.
- The bar itself: its 68 px height, its 42 px items and its colours stay as they are (tokens only).
- The menu of other header styles: all of this lives in the workplace header, where
  `js/app-menu.js` loads.
- Asking an external app whether it is reachable or whether the person may open it.

## Impact

- `lib/Service/AppMenuService.php`, new `lib/Service/AppMenuOrder.php`, new
  `lib/Service/ExternalApps.php`, new `lib/Controller/AppMenuOrderController.php` and
  `lib/Controller/ExternalAppsController.php`, `appinfo/routes.php`.
- `js/app-menu.js`, `css/header-workplace.css`, the admin page block and its script, `l10n`.
- The AppMenu board (`zuiddrecht/AppMenu`) has to follow: see `screens.md`.
