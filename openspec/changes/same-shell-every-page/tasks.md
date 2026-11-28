# Tasks: same-shell-every-page

Nothing here is built until this change is merged. Ruben's decisions of 27 November 2026 are in
`design.md` (the selected entry, the row height, the panel title, the Conduction apps). The top bar
follows ConductionNL/design-system#1216 once it is merged. Before the first move, record the compdiff
result of every board in `screens.md` on development, so "stays matched" has a baseline.

## 1. Server

- [x] 1.1 `lib/Service/LayoutStylesheets.php`: `forApp()` returns `['workplace-apps/' . $appId]` or
      nothing; `brand` and `common` go; the docblock says what `APP_LAYERS` now means. workplace-layout,
      REQ-TQSS-006.
- [x] 1.2 `lib/Service/CssInjectionService.php`, `lib/Service/HeaderUserService.php`: load
      `js/workplace-apps.js` with the light layout, whatever the header style, and no longer from
      `HeaderUserService::emitScript()`. REQ-TQSS-005.
- [x] 1.3 PHPUnit `LayoutOptionsServiceTest` (Files links no app sheet), `CssInjectionServiceTest`
      (Deck links only `workplace-apps/deck`; the script with the light layout and the default header,
      not with the default layout), `HeaderUserServiceTest`.

## 2. The brand start

- [x] 2.1 `css/header-workplace.css`: the rules of `css/workplace-apps/brand.css`, stepping back for
      `data-thematiq-brand`, `#header > .cn-brand-bar-host` and `.cn-app-nav__brand`; remove
      `css/workplace-apps/brand.css`. REQ-TQWH-005.
- [x] 2.2 `js/header-brand.js`: the page's name from the active settings entry when no app is open
      (confirm `core/settingsNavEntries` on every supported Nextcloud version first); emblem and
      organisation alone without a name. REQ-TQWH-001.
- [x] 2.3 vitest `headerBrand.spec.js` (settings name, no name), `headerWorkplace.spec.js` (fallback
      rules and their step-back conditions, no colour literal).

## 3. The shared layout

Each step moves one group from the three app sheets into the section "Every app's standard
structures" of `css/workplace-layout.css`, deletes the copies, and runs compdiff on the boards of the
three apps before the next step.

- [x] 3.1 Navigation rows, captions and the new-item button (`NcAppNavigationNew`); Mail's and
      Calendar's own buttons keep only their mapping. REQ-TQSS-001.
- [x] 3.2 The selected entry: remove the accent redeclaration of `common.css`; every app follows the
      navigation entry style. REQ-TQSS-001, decision 1.
- [x] 3.3 Links in rows, list rows, initials avatars in rows. REQ-TQSS-002.
- [x] 3.4 The sidebar title and tabs, icon-only buttons, the pointer-only focus rule, empty states.
      Deck keeps its 480 px panel; its 18 px title rule goes, it takes the shared 20 px. REQ-TQSS-003.
- [x] 3.5 The rail: one `--thematiq-rail` and one set of folded rules; Mail and Deck keep only their
      trigger. REQ-TQSS-004.
- [x] 3.6 Remove `css/workplace-apps/common.css`; each app sheet sets `--thematiq-app-layer`;
      `workplace-layout.css` sets `--thematiq-light-layout: 1`. REQ-TQSS-005, REQ-TQSS-006.
- [x] 3.7 vitest `workplaceLayout.spec.js`: the shared section's root, no `.app-` class, no colour
      literal, no `display`, `overflow` or `visibility` in the list-row rules, the pointer rule.
      `workplaceApps.spec.js`: the app sheets hold no shared rule, no rail property, the roots as before.

## 4. The script

- [x] 4.1 `js/workplace-apps.js`: gate on `--thematiq-light-layout`; the pointer mark and the words
      mechanism on every page; an app's words and redraw only on its page with its
      `--thematiq-app-layer`; the Deck API on a Deck page only. REQ-TQSS-005, REQ-TQAS-005.
- [x] 4.2 vitest `workplaceApps.spec.js`: the input mode on a Files page, nothing without the light
      layout, no Deck fetch outside Deck, no Mail word on a Deck page.

## 5. Speed

- [x] 5.1 New vitest `tests/vitest/layoutBudget.spec.js`: for Files, Mail, Calendar, Deck and a page
      without an app, under Zuiddrecht, the sum of the files `LayoutStylesheets` names, raw and
      gzipped at level 9, at most 94,592 and 24,669 bytes. REQ-TQSS-007.
- [ ] 5.2 `tests/e2e/performance.spec.ts` stays green.

## 6. Verification

- [ ] 6.0 Board follow-ups in ConductionNL/design-system: MlNav, ClNav and TbNav (and the boards that
      show them) with the selected entry of the navigation entry style (decision 1); TbCard with a
      20 px title (decision 3). Compdiff in 6.3 runs against the redrawn boards.

- [x] 6.1 `tests/css/check-shell-canon.mjs`: `CANON.navigationItemHeight` from 42 to 34 (decision 2);
      the OpenProject plugin's canon file (`spike/OP-TWIN-canon.md` in
      ConductionNL/openproject-thematiq) follows in a PR there. (thematiq's canon moved to 34 in
      the build PR; the plugin's canon file, geometry variables, sidebar rule and shell e2e followed in
      ConductionNL/openproject-thematiq#169.)
- [x] 6.2 New `tests/e2e/spec-coverage/same-shell-every-page.spec.ts` with `@e2e` tags for "Files has
      the boards' navigation", "Contacts' list rows", "A help link in Settings keeps its underline",
      "The Files sidebar", "Settings names its section" and "The four apps in the light layout"; an
      app the instance lacks is skipped with its name; every theme change is put back through
      `withThemeState()`.
      (Run live by the live lane on a Nextcloud 34 sandbox, sqlite + php -S: Files navigation, the
      Files sidebar, "Settings names its section" and the four apps in the light layout pass; Contacts
      and the Settings help link skip, because this instance has no Contacts app and no external help
      link. "Settings names its section" was red: Nextcloud 34 marks no settings entry active on
      Personal or Administration settings, fixed in js/header-brand.js.)
- [ ] 6.3 Compdiff on the demo stack under Zuiddrecht: MlInbox, MlThreadList, MlThread, ClWeek,
      ClMonth, ClEventPopover, TbBoard and TbCard against the baseline; DqKop and the #1216 top bars
      on Files and Settings; dossiq/DqZaken. Write each result in this task.
      (Live A/B by the live lane, 28 Nov, NC 34.0.6 + PostgreSQL 16, Zuiddrecht, Mail stable5.13 on a
      local Dovecot inbox, Calendar stable6.7, Deck stable34, all from git; compdiff live captures of
      the pre-change build 1311240d against development, same seed: MlInbox/MlThreadList change only
      in the navigation: the selected entry follows the admin's style and rows are 34 px (decisions 1
      and 2), plus one bug in the move, Mail's New message 48 px instead of 34 px (shared new-item rule
      targets NcAppNavigationNew only), fixed with this note. MlThread 0.11% of pixels, navigation only.
      ClWeek/ClMonth/ClEventPopover: Calendar's navigation rows 34 px (decision 2); the Deck card list
      Calendar draws over the week now takes the shared list-row look (44 -> 39 px, radius 3 -> 0, 1 px
      row edge); no Cl board draws that list, left for the owner. TbBoard navigation only; TbCard 0.10%
      (header pill 2.7 px). DqKop on Files: no pixel changed. Board against live still pairs nothing: the
      Ml/Cl/Tb boards are multi-frame sheets drawn for the OpenProject canon, so the board half of this
      task waits for 6.0's redraws; dossiq/DqZaken not run, dossiq is not on this sandbox.)
- [x] 6.4 l10n: no new strings expected (the settings name is Nextcloud's own); `npm run test:l10n`
      green. (No new strings in this change; `npm run test:l10n` is red on development for strings in
      js/admin.js that are not this change's; green again since thematiq#2203.)

## Notes from the build

- The rail's trigger: an app's sheet sets `--thematiq-rail: 1` on its content element in its fold
  state (Mail: a conversation is open; Deck: a card is open). An element cannot query a custom
  property it sets itself, and the shared rules size `.app-navigation` from it; the navigation sets
  it back to 0 for what it holds when it unfolds, and `--thematiq-rail-armed` carries "armed" to the
  list for the overlay. One registered property (`--thematiq-rail`), as the spec asks.
- REQ-TQSS-007: the shared section is written with CSS nesting (same selectors and specificity), and
  the login page and guest page rules moved from `css/workplace-layout.css` to
  `css/workplace-login.css`, loaded right after it only on renders that are not a user's page
  (`LayoutStylesheets::withContext()`). Without that move every page passed Mail's old weight.
- Kept in Mail, not shared: the 32 px initials avatar (a rule on every `NcListItem` would recolour
  avatars that carry the person's colour) and the envelope row's 22 px start (room for the unread dot).
