# Tasks: workplace-top-bar-brand-and-quick-add

## 1. Brand block

- [x] 1.1 `js/header-brand.js`: the brand block for an app without its own, from the capabilities and Nextcloud's navigation (#1793).
- [x] 1.2 `css/header-workplace.css`: the 237 px block, emblem, organisation, app name and divider. Tokens only (#1793).
- [x] 1.3 `lib/Service/HeaderUserService.php`: load `js/header-brand.js` with the workplace header (#1793).
- [x] 1.4 Draw only once the sheet styles it (`--thematiq-brand-block`) (#1804).
- [x] 1.5 Sync again only when the bar or an app brand changes (#1806).
- [x] 1.6 `css/workplace-apps/brand.css`: the fallback logo and name on Mail, Calendar and Deck (#1783).
- [x] 1.7 vitest `tests/vitest/headerBrand.spec.js`, `tests/vitest/headerWorkplace.spec.js` (#1793, #1804, #1806).

## 2. Quick add

- [x] 2.1 `js/header-create.js`: the button before the bell on Mail, Calendar and Deck, the menu, keyboard (#1807).
- [x] 2.2 `js/app-menu.js`: share the Create actions as `window.thematiqCreateActions` (#1807).
- [x] 2.3 `css/header-workplace.css`: the 44 px button and its menu. Tokens only (#1807).
- [x] 2.4 `lib/Service/HeaderUserService.php`: load `js/header-create.js`; l10n "Quick add", "Nothing to create here." (#1807).
- [x] 2.5 vitest `tests/vitest/headerCreate.spec.js`, `tests/vitest/appMenuCreate.spec.js` (#1807).
