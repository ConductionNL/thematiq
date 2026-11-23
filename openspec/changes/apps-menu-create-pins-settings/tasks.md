# Tasks: apps-menu-create-pins-settings

## 1. Title, chevron and Create row

- [x] 1.1 `js/app-menu.js`: the "Apps" title and the chevron on the toggle.
- [x] 1.2 `js/app-menu.js`: the Create row from the Files templates API, More, create and open.
- [x] 1.3 `lib/Service/AppMenuListState.php`: the new labels; l10n in English and Dutch.
- [x] 1.4 `css/header-workplace.css`: the title, the chevron button, the chips. Tokens only.
- [x] 1.5 vitest `tests/vitest/appMenuCreate.spec.js`; PHPUnit `AppMenuServiceTest` labels.

## 2. Pinned apps

- [x] 2.1 `lib/Service/AppMenuPins.php`, `lib/Service/AppMenuKeys.php`: read, check and save the pins (user value `app_menu_pins`, renamed `menu.pinned` in 3.1).
- [x] 2.2 `lib/Controller/AppMenuPinsController.php` and the route `POST /app-menu/pins`, signed-in users only.
- [x] 2.3 `js/app-menu.js`: the pins after the grid button, drag on and off with the hints, Alt+P, Alt with an arrow key on a pin.
- [x] 2.4 `css/header-workplace.css`: the pins, the drop slot, the chip and the hints. Tokens only.
- [x] 2.5 PHPUnit `AppMenuPinsTest`, `AppMenuPinsControllerTest`; vitest `tests/vitest/appMenuPins.spec.js`.

## 3. Menu settings (boards of design-system #1201)

- [x] 3.1 `lib/Service/AppMenuSettings.php`: admin defaults (`menu.<key>` app values) and personal keys (`menu.<key>` user values); `order` and `pinned` move to `menu.order` and `menu.pinned`, the old order is still read.
- [x] 3.2 `lib/Service/AppMenuExternalApp.php`, `lib/Service/AppMenuIcons.php`: external apps and uploaded icons checked on the server.
- [x] 3.3 `lib/Controller/AppMenuSettingsController.php`: show, save, reset, defaults, reset defaults, upload and serve icons.
- [x] 3.4 The effective settings and the configured external apps in the menu's state; the classic style; the dialog's texts on open.
- [x] 3.5 `js/app-menu.js`: the menu drawn from the settings, More apps / Fewer apps, the gear.
- [x] 3.6 `js/dialogs/menu-settings-dialog.js`, `css/menu-settings-dialog.css`: the dialog and the external app form.
- [x] 3.7 The menu's CSS moves from css/header-workplace.css to css/app-menu.css.
- [x] 3.8 Zuiddrecht: the Nextcloud apps get a payoff.
- [x] 3.9 PHPUnit `AppMenuSettingsTest`, `AppMenuIconsTest`, `AppMenuSettingsControllerTest`, `AppMenuServiceTest`; vitest `appMenuSettings.spec.js`, `menuSettingsDialog.spec.js`.
- [x] 3.10 Live check on the demo stack (:8076, 23 Nov 2026), menu-check.js as pieter and admin; gaps fixed in #1800.

## 4. The boards as canon

- [x] 4.1 `lib/Service/AppMenuLegacyExternalApps.php`, `lib/Repair/MigrateMenuExternalApps.php`: the admin page's external apps move into `menu.externalApps` once; the admin block points to Menu settings; `ExternalAppsController` and `js/admin-external-apps.js` go.
- [x] 4.2 Icon colour: brand washes the first section's work apps, the rest grey.
- [x] 4.3 Theming: inline colour radios, the size beside its name, the preview sentence; the scope help beside the switch.
- [x] 4.4 Entries 68 px high, as measured on AppMenuAlles.
- [x] 4.5 Zuiddrecht payoffs for Register, Integriq, Mail, Dashboard, Photos, Activity and Office.
