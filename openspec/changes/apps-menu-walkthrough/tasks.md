# Tasks: apps-menu-walkthrough

Nothing here is built until this change and its boards (ConductionNL/design-system#1213) are merged.
Copy and placement follow the boards; each task names its board.

## 1. The tour as data and the bundle

- [ ] 1.1 `src/walkthrough/apps-menu.json`: the `walkthrough` block with tour `apps-menu`, its seven
      steps with the copy and key hints of TqMenuRondleiding to TqMenuRondleidingInstellingen, targets, placements, `optional` on `more` and `create`, `sinceVersion` the release that
      ships this change (version core). REQ-TQWT-001, REQ-TQWT-005.
- [ ] 1.2 `src/menuWalkthrough.js`: mount `CnWalkthrough` with `useWalkthrough`, pass the version core
      as `appVersion`, the seen version and progress from the menu state as preloaded preferences, the
      reduced-motion look; the card's labels Step n of total, Back, Next, Done, Close walkthrough; expose `window.thematiqMenuWalkthrough.start({mode: 'auto'|'replay'})`.
      REQ-TQWT-002, REQ-TQWT-006, REQ-TQWT-007.
- [ ] 1.3 `webpack.menu-walkthrough.config.js` building `js/vendor/menuWalkthrough.js` (gitignored);
      `npm run build` runs it; the l10n strings are translated with `t('thematiq', ...)`.
- [ ] 1.4 vitest `tests/vitest/menuWalkthrough.spec.js`: step order and skipping, version-core gating
      (a 1.3.0 viewer gets only a 1.4.0 step; a nightly seen version does not hide the next release's
      step), replay shows every step, the texts go through `t('thematiq')`.

## 2. The menu script

- [ ] 2.1 `js/app-menu.js`: set the `data-walkthrough-id` hooks on the grid button, the list, the More
      toggle, the first entry, the pins (or the grid button when none), the Create row and the gear.
- [ ] 2.2 `js/app-menu.js`: on the first open of the menu with a tour due, add the bundle and start the
      tour; never on page load; not while the setup wizard is open. REQ-TQWT-002.
- [ ] 2.3 `js/app-menu.js`: hold the menu open while the tour runs (dimmer click, pointer leave and
      hover timeout do not close it); Escape closes the tour first. REQ-TQWT-003.
- [ ] 2.4 `js/dialogs/menu-settings-dialog.js`: the Replay walkthrough button between Reset to default
      and Cancel (TqMenuRondleidingOpnieuw), asking before unsaved changes are dropped; not with the
      classic style. REQ-TQWT-008.
- [ ] 2.5 `js/header-user.js`: the Replay walkthrough entry under Nextcloud's Help entry, or last; not
      with the classic style. REQ-TQWT-006.
- [ ] 2.6 vitest `tests/vitest/appMenuWalkthroughHooks.spec.js` (hooks, due logic, held open, Escape
      order), additions to `menuSettingsDialog.spec.js` and a new `headerUser.spec.js`.

## 3. Server

- [ ] 3.1 `lib/Service/WalkthroughPreferences.php`: read and write the two tour keys as user values of
      the signed-in person; the version core of the installed app. REQ-TQWT-004, REQ-TQWT-005.
- [ ] 3.2 `lib/Controller/PreferencesController.php` and routes `GET` / `PUT /api/preferences/{key}`,
      `#[NoAdminRequired]`, session required, two keys only, 404 otherwise. REQ-TQWT-004.
- [ ] 3.3 `lib/Service/AppMenuService.php`: `walkthrough: {seen, progress, appVersion}` in the
      `thematiq/app-menu` initial state. REQ-TQWT-002.
- [ ] 3.4 PHPUnit `tests/Unit/Service/WalkthroughPreferencesTest.php`,
      `tests/Unit/Controller/PreferencesControllerTest.php`, `AppMenuServiceTest` for the new state.
- [ ] 3.5 l10n: the step copy and labels of the boards in English and Dutch;
      `npm run test:l10n` green.

## 4. End to end

- [ ] 4.1 `tests/e2e/spec-coverage/apps-menu-walkthrough.spec.ts` (`@e2e` tags per scenario): clear the
      admin's tour keys and order in setup and put them back in a `finally`; open the menu and walk all
      steps, checking each spotlight target and the counter; on the reorder step move an app with Alt
      and Arrow up and check it after a reload; on the pin step pin and unpin with Alt+P and check the
      top bar; on the more step unfold and move an app past the fold; Escape closes the tour and leaves
      the menu open; close, then a new browser context gets no tour; replay from the account menu and
      from Menu settings; the create-off variant shows 6 steps; the live region announces step 2.
- [ ] 4.2 Needs at least 10 apps on the instance so the fold toggle shows (the existing apps-menu
      e2e condition); the spec skips with that reason, never passes silently, when fewer are there.

## 5. Live check

- [ ] 5.1 On the reference instance with the Zuiddrecht set: as a new non-admin person and as admin,
      the tour starts on the first menu open, every step lands on its part, Alt+P and Alt with an arrow
      key work under the tour, replay works from both places; screenshots against the boards.
- [ ] 5.2 Speed: a page without a due tour loads no `menuWalkthrough.js`; record the bundle size and the
      first-open delay in the PR.
