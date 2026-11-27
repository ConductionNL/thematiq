# Tasks: same-shell-every-page

Nothing here is built until this change is merged. Ruben's decisions of 27 November 2026 are in
`design.md` (the selected entry, the row height, the panel title, the Conduction apps). The top bar
follows ConductionNL/design-system#1216 once it is merged. Before the first move, record the compdiff
result of every board in `screens.md` on development, so "stays matched" has a baseline.

## 1. Server

- [ ] 1.1 `lib/Service/LayoutStylesheets.php`: `forApp()` returns `['workplace-apps/' . $appId]` or
      nothing; `brand` and `common` go; the docblock says what `APP_LAYERS` now means. workplace-layout,
      REQ-TQSS-006.
- [ ] 1.2 `lib/Service/CssInjectionService.php`, `lib/Service/HeaderUserService.php`: load
      `js/workplace-apps.js` with the light layout, whatever the header style, and no longer from
      `HeaderUserService::emitScript()`. REQ-TQSS-005.
- [ ] 1.3 PHPUnit `LayoutOptionsServiceTest` (Files links no app sheet), `CssInjectionServiceTest`
      (Deck links only `workplace-apps/deck`; the script with the light layout and the default header,
      not with the default layout), `HeaderUserServiceTest`.

## 2. The brand start

- [ ] 2.1 `css/header-workplace.css`: the rules of `css/workplace-apps/brand.css`, stepping back for
      `data-thematiq-brand`, `#header > .cn-brand-bar-host` and `.cn-app-nav__brand`; remove
      `css/workplace-apps/brand.css`. REQ-TQWH-005.
- [ ] 2.2 `js/header-brand.js`: the page's name from the active settings entry when no app is open
      (confirm `core/settingsNavEntries` on every supported Nextcloud version first); emblem and
      organisation alone without a name. REQ-TQWH-001.
- [ ] 2.3 vitest `headerBrand.spec.js` (settings name, no name), `headerWorkplace.spec.js` (fallback
      rules and their step-back conditions, no colour literal).

## 3. The shared layout

Each step moves one group from the three app sheets into the section "Every app's standard
structures" of `css/workplace-layout.css`, deletes the copies, and runs compdiff on the boards of the
three apps before the next step.

- [ ] 3.1 Navigation rows, captions and the new-item button (`NcAppNavigationNew`); Mail's and
      Calendar's own buttons keep only their mapping. REQ-TQSS-001.
- [ ] 3.2 The selected entry: remove the accent redeclaration of `common.css`; every app follows the
      navigation entry style. REQ-TQSS-001, decision 1.
- [ ] 3.3 Links in rows, list rows, initials avatars in rows. REQ-TQSS-002.
- [ ] 3.4 The sidebar title and tabs, icon-only buttons, the pointer-only focus rule, empty states.
      Deck keeps its 480 px panel; its 18 px title rule goes, it takes the shared 20 px. REQ-TQSS-003.
- [ ] 3.5 The rail: one `--thematiq-rail` and one set of folded rules; Mail and Deck keep only their
      trigger. REQ-TQSS-004.
- [ ] 3.6 Remove `css/workplace-apps/common.css`; each app sheet sets `--thematiq-app-layer`;
      `workplace-layout.css` sets `--thematiq-light-layout: 1`. REQ-TQSS-005, REQ-TQSS-006.
- [ ] 3.7 vitest `workplaceLayout.spec.js`: the shared section's root, no `.app-` class, no colour
      literal, no `display`, `overflow` or `visibility` in the list-row rules, the pointer rule.
      `workplaceApps.spec.js`: the app sheets hold no shared rule, no rail property, the roots as before.

## 4. The script

- [ ] 4.1 `js/workplace-apps.js`: gate on `--thematiq-light-layout`; the pointer mark and the words
      mechanism on every page; an app's words and redraw only on its page with its
      `--thematiq-app-layer`; the Deck API on a Deck page only. REQ-TQSS-005, REQ-TQAS-005.
- [ ] 4.2 vitest `workplaceApps.spec.js`: the input mode on a Files page, nothing without the light
      layout, no Deck fetch outside Deck, no Mail word on a Deck page.

## 5. Speed

- [ ] 5.1 New vitest `tests/vitest/layoutBudget.spec.js`: for Files, Mail, Calendar, Deck and a page
      without an app, under Zuiddrecht, the sum of the files `LayoutStylesheets` names, raw and
      gzipped at level 9, at most 94,592 and 24,669 bytes. REQ-TQSS-007.
- [ ] 5.2 `tests/e2e/performance.spec.ts` stays green.

## 6. Verification

- [ ] 6.0 Board follow-ups in ConductionNL/design-system: MlNav, ClNav and TbNav (and the boards that
      show them) with the selected entry of the navigation entry style (decision 1); TbCard with a
      20 px title (decision 3). Compdiff in 6.3 runs against the redrawn boards.

- [ ] 6.1 `tests/css/check-shell-canon.mjs`: `CANON.navigationItemHeight` from 42 to 34 (decision 2);
      the OpenProject plugin's canon file (`spike/OP-TWIN-canon.md` in
      ConductionNL/openproject-thematiq) follows in a PR there.
- [ ] 6.2 New `tests/e2e/spec-coverage/same-shell-every-page.spec.ts` with `@e2e` tags for "Files has
      the boards' navigation", "Contacts' list rows", "A help link in Settings keeps its underline",
      "The Files sidebar", "Settings names its section" and "The four apps in the light layout"; an
      app the instance lacks is skipped with its name; every theme change is put back through
      `withThemeState()`.
- [ ] 6.3 Compdiff on the demo stack under Zuiddrecht: MlInbox, MlThreadList, MlThread, ClWeek,
      ClMonth, ClEventPopover, TbBoard and TbCard against the baseline; DqKop and the #1216 top bars
      on Files and Settings; dossiq/DqZaken. Write each result in this task.
- [ ] 6.4 l10n: no new strings expected (the settings name is Nextcloud's own); `npm run test:l10n`
      green.
