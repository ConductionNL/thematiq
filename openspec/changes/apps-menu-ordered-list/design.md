# Design: apps-menu-ordered-list

## Reference

Giplat/thematiq, an OpenProject plugin in Ruby: `shell/app_switcher.rb`,
`shell/assets/app_switcher.js` and `shell/external_apps.rb`. The behaviour is ported, not the code,
and none of its colours, fonts or zoom. Two things differ on purpose:

- The twin keeps the order in the browser (`localStorage`). Here it is a user setting on the
  server, so it follows the person.
- The twin moves the entries in the DOM. Nextcloud renders the grid with Vue, and moving its nodes
  breaks Vue's patching, so `js/app-menu.js` keeps the tiles in place and orders them with CSS
  `order`, as it already does (workplace-layout). A tile past the nine carries
  `data-thematiq-folded`, never a class: Vue rewrites `class` on every render.

## The list

Every entry has a key. A Nextcloud tile's key is its app id (the segment after `/apps/`). An
external entry's key is `external-<app>`, and for OpenProject `external-openproject-<module>`. The
order is:

1. the keys in the person's stored order that are on the page, in that order;
2. then every other entry in the default order: the token set's group apps in group order, the
   other Nextcloud apps in Nextcloud's order, then the external apps.

The first nine entries show. Past the nine, the Nextcloud apps come first, then the "External apps"
heading with the external entries. An external entry dragged into the nine stays there. "More
apps" and the app store stay out of the list: they are the footer's "manage" link.

## Keyboard

Nextcloud's grid handles the arrow keys over its own data order with four columns. That order is
no longer what is on screen, so the script handles the keys first (capture phase) while it arranges
the menu: the arrow keys, Home and End move between the visible entries in screen order, and Enter
and Space open the focused one. Alt with Arrow up or Arrow left moves the focused entry one place
earlier, Alt with Arrow down or Arrow right one place later. A polite live region says where it went.
Alt with an arrow does not move the browser back or forward here, because the script stops the
event.

## Saving

`POST /apps/thematiq/app-menu/order` with `{order: [keys]}` stores the list as JSON in the user
value `thematiq` / `app_menu_order`. Only the signed-in user's own value is written: the route takes
no user id. Keys must match `^[a-z0-9_-]{1,80}$`; at most 200 are kept and duplicates drop. An empty
list removes the value. The page gets the stored order in the `thematiq/app-menu` initial state as
`order`.

## Opening

Nextcloud 33 and 34 open the menu on a click only. The fork's Nextcloud 35 already opens it on
hover (`.app-menu__trigger` exists there), but closes a click-opened menu when the pointer leaves.
So the script:

- without `.app-menu__trigger`: opens the menu 300 ms after a fine pointer enters the grid button or
  the current-app button, and closes it 180 ms after the pointer has left both the buttons and the
  menu, unless it is pinned;
- with `.app-menu__trigger`: leaves the hover-open to Nextcloud;
- in both: a click on a button while the menu is open by hover pins it instead of closing it, and a
  pinned or click-opened menu ignores the pointer leaving. Escape, a click outside and a click on the
  button of a pinned menu close it, as Nextcloud does.

Hover-open needs `(hover: hover) and (pointer: fine)` and a focused window. With
`prefers-reduced-motion: reduce`, `css/header-workplace.css` turns off the popover's transitions.

## External apps

`external_apps` (app config, JSON `{key: address}`) holds the addresses of `mail` (Open-Xchange),
`chat` (Element), `wiki` (XWiki), `code` (Forgejo) and `video` (BigBlueButton). OpenProject keeps its
existing keys `openproject_url` and `openproject_modules`, so an installation that set it keeps its
links. The admin block saves all addresses at once; one address that is not http or https refuses
the whole save, so nothing half-saved is linked. Each link opens in a new tab with
`rel="noopener noreferrer"`.
