---
kind: code
depends_on:
  - quick-actions-toggles   # the Create button on every page; this change does the same for the bar's start and the app layout
---

# The same shell on every page

## Why

Ruben decided on 27 November 2026 that no thematiq page differs from another. Today the light layout
draws three apps differently from the rest:

- **The start of the top bar.** The organisation and the name of the open app show on Mail, Calendar
  and Deck from the first paint, because only their pages load `css/workplace-apps/brand.css`
  (`LayoutStylesheets::forApp()`, `APP_LAYERS`). Every other page waits for `js/header-brand.js`, and a
  page without an open app, such as Settings, shows no brand at all.
- **The app layout.** Only Mail, Calendar and Deck get the boards' navigation, list rows, panels,
  buttons and empty states: `css/workplace-apps/common.css` plus `mail.css`, `calendar.css` and
  `deck.css` (about 2,700 lines) and `js/workplace-apps.js`, gated by `--thematiq-workplace-apps`.
  Files, Talk, Contacts, Settings and every other app keep Nextcloud's own look under the same theme.

The quick-actions-toggles change already did this for the Create button: one '+' before the bell on
every page. This change does the same for the bar's start and for the layout of the apps.

## What changes

- **The brand start on every page.** With the workplace header, every page shows the organisation
  and the current app's name at the start of the bar: from the first paint through Nextcloud's own
  logo and app name, then as the board's brand block once `js/header-brand.js` has drawn it. A
  settings page names its settings section. An app that draws its own brand still wins.
- **One light layout for every app.** What Mail, Calendar and Deck share moves into the general light
  layout and applies to every app's standard Nextcloud structures: the app navigation, its captions
  and its new-item button, list rows (`NcListItem`), the sidebar panel and its tabs, icon-only
  buttons, empty states (`NcEmptyContent`), links in rows, and the navigation rail. Written once,
  against `#app-navigation-vue`, `#app-content-vue`, `#app-sidebar-vue` and the components'
  own classes, never against an app.
- **Only true app content stays app-specific.** A short, explicit list per app: Deck's card faces and
  columns, Calendar's grid, entries and week day labels, Mail's conversation, attachment rows and
  empty reading pane. Those sheets still load on their own app's pages only.
- **The script follows.** The input-mode attribute and the words mechanism of `js/workplace-apps.js`
  act on every page with the light layout; its Mail, Calendar and Deck parts act only on that app's
  page with that app's sheet.
- **Speed is capped.** No page pays more light-layout CSS than Mail pays today.

## Not in this change

- Changing an app's own markup or code. What a board draws that an app cannot show stays out, as
  before.
- New boards for Files, Talk, Contacts or Settings. Those apps take the shared rules; a board of
  their own is a follow-up when Ruben asks for one.
- The default layout and the default header. Nothing here applies while the layout is not `light`.
- The Create button: quick-actions-toggles owns it.

## Boards

ConductionNL/design-system#1216 (branch `design/thematiq-quick-actions-place`) adds the brand start and
the '+' to the top bars that are not Mail's: `werkplek/DqKop` gains the Create button and the
`werkplek/AppMenu` family draws the bar with both. The app layout keeps the existing boards: MlInbox,
MlThreadList, MlThread, ClWeek, ClMonth, ClEventPopover, TbBoard and TbCard. Boards are canon. See
`screens.md`.

## Impact

- `lib/Service/LayoutStylesheets.php`: `forApp()` returns only `workplace-apps/<app>`; `brand` and
  `common` go
- `lib/Service/HeaderUserService.php`, `lib/Service/CssInjectionService.php`: `js/workplace-apps.js`
  loads with the light layout, not only with the workplace header
- `css/workplace-layout.css`: the shared rules, in one new section
- `css/header-workplace.css`: the brand fallback from `brand.css`, on every page
- `css/workplace-apps/brand.css`, `css/workplace-apps/common.css`: removed
- `css/workplace-apps/mail.css`, `calendar.css`, `deck.css`: shrink to their app content
- `js/workplace-apps.js`, `js/header-brand.js`: the gate, and the settings name
- tests: PHPUnit `LayoutOptionsServiceTest`, `CssInjectionServiceTest`, `HeaderUserServiceTest`;
  vitest `workplaceApps.spec.js`, `workplaceLayout.spec.js`, `headerBrand.spec.js`,
  `headerWorkplace.spec.js`, new `layoutBudget.spec.js`; e2e new
  `tests/e2e/spec-coverage/same-shell-every-page.spec.ts`; `tests/css/check-shell-canon.mjs`
- Risk: Files, Talk, Contacts, Settings and the Conduction apps never had these rules. See
  `design.md`, "Risk to apps that never had a layer".
