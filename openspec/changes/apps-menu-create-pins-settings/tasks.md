# Tasks: apps-menu-create-pins-settings

## 1. Title, chevron and Create row

- [x] 1.1 `js/app-menu.js`: the "Apps" title and the chevron on the toggle.
- [x] 1.2 `js/app-menu.js`: the Create row from the Files templates API, More, create and open.
- [x] 1.3 `lib/Service/AppMenuListState.php`: the new labels; l10n in English and Dutch.
- [x] 1.4 `css/header-workplace.css`: the title, the chevron button, the chips. Tokens only.
- [x] 1.5 vitest `tests/vitest/appMenuCreate.spec.js`; PHPUnit `AppMenuServiceTest` labels.

## 2. Pinned apps

- [x] 2.1 `lib/Service/AppMenuPins.php`, `lib/Service/AppMenuKeys.php`: read, check and save the pins (user value `app_menu_pins`).
- [x] 2.2 `lib/Controller/AppMenuPinsController.php` and the route `POST /app-menu/pins`, signed-in users only.
- [x] 2.3 `js/app-menu.js`: the pins after the grid button, drag on and off with the hints, Alt+P, Alt with an arrow key on a pin.
- [x] 2.4 `css/header-workplace.css`: the pins, the drop slot, the chip and the hints. Tokens only.
- [x] 2.5 PHPUnit `AppMenuPinsTest`, `AppMenuPinsControllerTest`; vitest `tests/vitest/appMenuPins.spec.js`.
