# Tasks: The apps menu of the board

## 1. Spec
- [x] 1.1 Proposal and the workplace-layout delta.

## 2. Service, script and styles
- [x] 2.1 `AppMenuService`: the set's `app_menu` block, the `app_menu_config` override.
- [x] 2.2 `CssInjectionService`: the layer beside the person's.
- [x] 2.3 `js/app-menu.js` and `css/header-workplace.css`.
- [x] 2.4 `token-sets.json`: zuiddrecht ships the board's section.

## 3. Tests
- [x] 3.1 PHPUnit: `AppMenuServiceTest`, the layer in `CssInjectionServiceTest`.
- [x] 3.2 Vitest: `tests/vitest/appMenu.spec.js`.

## 4. Verify
- [x] 4.1 Live on :8080 with the menu open (`screens-diff.mjs --click .app-menu__waffle`), against the AppMenu board: 9.30% -> 8.41% as Pieter; the rest is the top bar and the app data (see the PR).
