---
kind: code
depends_on: []
chain:
  - apps-menu-walkthrough   # this change
  - first-time-setup        # its finish step offers this tour
---

# The apps menu walkthrough

## Why

Ruben's review of thematiq on a reference instance (27 November 2026, item 1): the workplace apps menu
does a lot, and none of it explains itself. You can open it by pointing, clicking or with the keyboard,
fold apps away under More, reorder them by dragging or with Alt and an arrow key, pin them to the top
bar by dragging or with Alt+P, create a document from it and change it in Menu settings. Nobody finds
Alt+P on their own. Every other Conduction app teaches its screens with the nextcloud-vue walkthrough
(`CnWalkthrough`, ADR-043); thematiq has no walkthrough at all.

## What changes

- **A tour of the apps menu, one step per feature.** Opening the menu, the app list, More and moving
  apps between the list and More, reordering, pinning and unpinning, the Create row and Menu settings.
  Each step spotlights the real part of the menu and leaves it working, so you can try what the step
  explains while it is open.
- **It starts by itself the first time you open the menu.** Not on page load: the tour waits until you
  open the menu yourself, and then starts with the menu open.
- **You can replay it.** From Menu settings ("Replay walkthrough"), from under Help in your account
  menu, and for administrators from thematiq's version card.
- **New features get their own step.** Every step names the thematiq version that brought it. After an
  upgrade that adds a menu feature, the next time you open the menu you get a short tour of only the new
  steps, not the whole tour again.
- **Copy in English and Dutch** as the boards write it.
- **The nextcloud-vue engine, not a hand-written one.** The tour is `CnWalkthrough` with
  `useWalkthrough`, built into one script bundle that loads only when a tour is due or asked for, so a
  page without a tour carries no Vue.
- **An e2e test that walks every step** and checks that the move, reorder and pin actions each step
  explains actually work while the tour is open.

## Not in this change

- A tour of thematiq's admin pages. This tour covers the apps menu, which every person uses.
- Steps for the classic menu style. With the classic style thematiq leaves Nextcloud's menu alone and
  loads no menu script, so there is nothing to tour.
- Changing what the menu does. The tour explains the menu as the apps-menu specs define it.

## Boards

The boards are ConductionNL/design-system#1213: TqMenuRondleiding, TqMenuRondleidingApps,
TqMenuRondleidingMeer, TqMenuRondleidingOrdenen, TqMenuRondleidingVastzetten, TqMenuRondleidingNieuw,
TqMenuRondleidingInstellingen and TqMenuRondleidingOpnieuw, with the replay button added to
AppMenuInstellingen. Boards are canon; `design.md` carries their copy. See `screens.md`.

## Impact

- new `src/menuWalkthrough.js` and `webpack.menu-walkthrough.config.js`, bundled to
  `js/vendor/menuWalkthrough.js` (gitignored, built by `npm run build`)
- new `src/walkthrough/apps-menu.json`: the tour as manifest data (ADR-043 `walkthrough` block)
- `js/app-menu.js`: loads the bundle on the first open when a tour is due, the replay entry, the
  `data-walkthrough-id` hooks on the menu's parts, the menu held open while the tour runs
- `js/dialogs/menu-settings-dialog.js`: the Replay walkthrough button
- `js/header-user.js`: the account menu entry under Help
- new `lib/Service/WalkthroughPreferences.php`, `lib/Controller/PreferencesController.php`, routes
  `GET` / `PUT /api/preferences/{key}` (the nextcloud-vue preference contract, two keys only)
- `lib/Service/AppMenuService.php`: the walkthrough preferences and the app version in the menu's
  initial state
- l10n: the step copy in English and Dutch
- new `tests/e2e/spec-coverage/apps-menu-walkthrough.spec.ts`
