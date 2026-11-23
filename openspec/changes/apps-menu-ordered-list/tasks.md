# Tasks: apps-menu-ordered-list

## 1. Server

- [x] 1.1 `lib/Service/AppMenuOrder.php`: read, clean and save the user's order (user value `app_menu_order`).
- [x] 1.2 `lib/Controller/AppMenuOrderController.php` and the route `POST /app-menu/order`, signed-in users only.
- [x] 1.3 `lib/Service/ExternalApps.php`: the addresses in `external_apps`, OpenProject's modules through `OpenProjectModules`, the section.
- [x] 1.4 `lib/Controller/ExternalAppsController.php` and the routes `GET`/`POST /settings/external-apps`, admin only.
- [x] 1.5 `AppMenuService::inject()` adds `order`, `external` and the labels to the menu's state.
- [x] 1.6 PHPUnit: `AppMenuOrderTest`, `AppMenuOrderControllerTest`, `ExternalAppsTest`, `ExternalAppsControllerTest`, `AppMenuServiceTest`.

## 2. Admin page

- [x] 2.1 The block "External apps in the apps menu": one address field per app, one save button, English and Dutch.

## 3. The menu

- [x] 3.1 `js/app-menu.js`: one ordered list, the first nine, "All apps" and "Hide apps", the external apps heading.
- [x] 3.2 Drag to reorder, Alt with an arrow key to reorder, the live region, saving.
- [x] 3.3 Keyboard navigation over the visible entries in screen order.
- [x] 3.4 Hover to open, click to pin, on Nextcloud with and without its own hover-open.
- [x] 3.5 `css/header-workplace.css`: three columns, the fold, the toggle, the heading, reduced motion. Tokens only.
- [x] 3.6 vitest: `tests/vitest/appMenu.spec.js` and `tests/vitest/appMenuOpening.spec.js`.

## 4. Live

- [x] 4.1 Playwright: `tests/e2e/spec-coverage/apps-menu-ordered-list.spec.ts`.
- [x] 4.2 Looked at live with the Zuiddrecht set on :8076 (Nextcloud 34.0.6), 23 Nov 2026: 7/7 Playwright green, pointer over to shown 154 ms, click to shown 36 to 62 ms, no request on open. The AppMenu board still has to follow (`screens.md`).
