# Tasks: first-time-setup

Nothing here is built until this change and its boards (ConductionNL/design-system#1213) are merged.
Copy and layout follow the boards; each task names its board. Task 5.3 needs apps-menu-walkthrough built.

## 1. Status and contract

- [x] 1.1 `lib/Service/SetupStatusService.php`: configured, completed, skipped, each step's done and the
      stored choices (`current`), from the stored values; cached per request. REQ-TQFS-002.
- [x] 1.2 `lib/Service/SetupActions.php`: `apply` checks the whole request, then writes the licence
      confirmation, the set through `ActiveTokenSetService::switchTo()`, Nextcloud's theme through the
      `--sync-core` path and `core_theming_follows_set`, `themed_contexts` and `hide_slogan`, the menu
      defaults through `AppMenuSettings::saveDefaults()` and `header_style` workplace when the menu
      needs the bar, then `setup.completed`; reports each part; `skip`; audit entries with the actor.
      REQ-TQFS-003, REQ-TQFS-004, REQ-TQFS-007 to REQ-TQFS-010.
- [x] 1.3 `lib/Controller/SetupController.php` and the routes `GET /api/setup/status`,
      `POST /api/setup/action/{actionId}`; admin only (no `#[NoAdminRequired]`), CSRF on the POST; 400
      naming the key, 404 for an unknown action, 423 under a configuration source through the existing
      middleware. REQ-TQFS-005.
- [x] 1.4 `lib/Service/ActiveTokenSetService.php`: while `core_theming_follows_set` is `1`, every switch
      (settings page, occ, planned switch) also applies the set to Nextcloud's theme with the snapshot as
      a version. REQ-TQFS-008.
- [x] 1.5 PHPUnit `tests/Unit/Service/SetupStatusServiceTest.php` (configured by an earlier version, an
      upgrade with nothing stored is due, a configuration source counts as configured, each step's done),
      `tests/Unit/Service/SetupActionsTest.php` (the licence gate, a failed part is reported and
      completion waits, skip changes nothing, the Nextcloud login removes the login context, the menu
      brings `header_style`), `tests/Unit/Controller/SetupControllerTest.php` (a wrong value refuses the
      request, an unknown action is not found, the menu switch stores the style),
      `ActiveTokenSetServiceTest` (a following theme is synced on every switch).
- [x] 1.6 Hydra gates on the new routes: route-auth, semantic-auth, no-admin-idor, route-reachability.

## 2. Opening the wizard on any page

- [x] 2.1 `lib/Listener/SetupWizardListener.php` on `BeforeTemplateRenderedEvent`: user pages only,
      `admin` group only, setup due only; provide `setup_status` and add `js/setup-wizard-loader.js`.
      Registered in `Application::register()`. REQ-TQFS-001.
- [x] 2.2 `js/setup-wizard-loader.js`: add `js/vendor/setupWizard.js` once the page is idle; wait while
      Nextcloud's first-run wizard is open. REQ-TQFS-001.
- [x] 2.3 PHPUnit `tests/Unit/Listener/SetupWizardListenerTest.php` (only an admin's user page gets the
      wizard; nothing once set up; nothing for a non-admin, a delegated theming administrator, a guest
      or a public page).

## 3. The wizard

- [x] 3.1 `src/manifest.json`: the ADR-042 `setup` block with the steps welcome, house-style,
      nextcloud-theme, login-page, apps-menu, done.
- [x] 3.2 `src/setupWizard.js` and `webpack.setup-wizard.config.js` building `js/vendor/setupWizard.js`
      (gitignored, run by `npm run build`): mount `CnSetupWizard` in its own container; the counter and
      rail; Skip and Later on steps 1 to 5, the close button "Later, continue on the next page"; nothing
      written before Apply. TqSetupWelkom. REQ-TQFS-003, REQ-TQFS-004, REQ-TQFS-006.
- [x] 3.3 The house style step: the radio cards, the summary line, the gallery link, Upload your own set
      through the existing upload route, the licence block with the statement and Next held until it is
      ticked. TqSetupHuisstijl, TqSetupHuisstijlLicentie. REQ-TQFS-007.
- [x] 3.4 The Nextcloud theme step: the switch, the four cells with now and new, the version note;
      skipped for Nextcloud default. TqSetupTheming. REQ-TQFS-008.
- [x] 3.5 The login page step: the two styles, the slogan checkbox, the login card preview.
      TqSetupInlogpagina. REQ-TQFS-009.
- [x] 3.6 The apps menu step: the one switch (on is workplace, off is classic, no Style choice), and
      while it is on the columns, rows above More apps and the three switches; Apply with the parts
      reported on a failure. TqSetupAppmenu, without its Style row. REQ-TQFS-003, REQ-TQFS-010.
- [x] 3.7 The done step: the summary rows, Close, Start the apps menu walkthrough (stores the immediate
      start, reloads). TqSetupKlaar. REQ-TQFS-011.
- [x] 3.8 vitest `tests/vitest/setupWizard.spec.js`: Later and the steps store nothing, Skip runs `skip`
      and an uploaded set stays, the restricted set flow and a refusal on Apply, the parts on a failure,
      the walkthrough handoff.
- [x] 3.9 l10n: the boards' copy in English and Dutch; `npm run test:l10n` green. (#2166: the wizard over
      CnWizardDialog with nextcloud-vue 2.236.0's `#actions-start` for Skip; live on a sandbox NC34 +
      MariaDB: Welcome to Done with Gemeente Amsterdam, licence statement gating Next, four parts
      applied, setup.completed stored; a half-failed Apply names the parts; vitest 12. The
      extraction check reads js/ only, so the src/ strings were added to every locale by hand.)

## 4. The version card

- [x] 4.1 `templates/settings/admin.php` and new `js/admin-version-card.js`: the version card with the
      installed version, the setup state and detail, Set up again, Apps menu walkthrough and Help us,
      Set up again disabled under a configuration source. TqSetupOpnieuw. REQ-TQFS-012.
- [x] 4.2 vitest `tests/vitest/adminVersionCard.spec.js`; PHPUnit for the card's state in
      `lib/Settings/Admin.php`. (#VC: the card reads its state from `GET /api/setup/status` and the
      source from `GET /settings/config-source`, so Admin.php carries none and needs no PHPUnit; vitest
      adminVersionCard 5. Live on a sandbox NC34: Completed by admin, Set up again opens at Welcome with
      the stored set chosen, Later closes, nothing after a reload. "Help us" links to the forge's
      feature-request form, as nextcloud-vue's shell does since its in-product modal was removed.)

## 4b. Keep the Nextcloud theme in line, on the settings page

- [x] 4b.1 `SettingsController::setCoreThemingFollows()` and the route
      `POST /settings/core-theming-follows`: admin only, audit entry, 423 under a configuration source;
      on applies the active set through the `--sync-core` path with the old theme as a version.
      REQ-TQFS-013.
- [x] 4b.2 `templates/settings/admin.php` and `js/admin.js`: the switch in the Active token set card
      with its hint and status line, filled from `core_theming_follows_set`; no theming sync dialog on a
      change of set while it is on; disabled under a configuration source. REQ-TQFS-013.
- [x] 4b.3 PHPUnit `tests/Unit/Controller/SettingsControllerTest.php` (on applies and stores `1`, off
      stores `0` and leaves core theming, 423 under a source, audit entry); vitest for the switch and
      the skipped dialog.
- [x] 4b.4 e2e in `tests/e2e/spec-coverage/first-time-setup.spec.ts`: turn the switch on, the core
      primary colour is the set's; choose another set, no dialog and the core colour follows; turn it
      off, choose a set, the dialog asks; restore core theming and the set in a `finally`.

## 5. End to end

- [x] 5.1 `tests/e2e/spec-coverage/first-time-setup.spec.ts` (`@e2e` tags per scenario): in setup, read
      and remove the setup-relevant app values and `setup.*` through `occPrefix()`, and put every one
      back in a `finally`, with `_theme-state` restoring the active set and core theming; withdraw the
      confirmation of `amsterdam` when it is not applied, and restore it after.
- [x] 5.2 Walk: the welcome on the dashboard and in Files; a non-admin sees nothing and the page has no
      `setup_status`; walking the steps and Later writes nothing and the wizard comes back; Skip and
      nothing on the next page; a configured instance shows nothing; status is 403 for a non-admin.
- [x] 5.3 Full run: choose `amsterdam`, Next disabled until the statement; keep the theme switch on;
      Follow the house style with the slogan hidden; menu on with 3 columns on a set without the
      workplace top bar; Apply; then the set is active and audited, the core primary colour, logo and
      favicon are the set's (`_core-theming.ts`), an anonymous login page has no slogan, a non-admin
      sees the workplace top bar and 3 columns; the step offers no Style choice; Start the apps menu
      walkthrough opens the menu with the tour.
      (28 Nov, b-launchpad helping, sandbox NC 34.0.6 + MariaDB + php-imagick, OpenRegister main:
      first-time-setup.spec.ts 6/6. Added: amsterdam's logo and favicon slots are what its theming
      metadata brings; a zuiddrecht run (logo + favicon in its metadata) puts its primary colour, logo
      and favicon into Nextcloud's theme, and "Start the apps menu walkthrough" reloads without the
      wizard, opens the menu and starts at "Open the apps menu". Fixed in the spec: beforeEach left the
      menu defaults of an earlier Apply in place, so a second wizard run found setup done. Sandbox
      note: without php-imagick Nextcloud refuses an SVG favicon and Apply reports "Not applied:
      Nextcloud theme". STILL OPEN, decision queued: amsterdam (no light layout) with the menu on
      stores `header_style` workplace, but `css/header-workplace.css` is emitted only under the light
      layout (workplace-layout spec), so no workplace top bar and no thematiq menu appear for a
      non-admin; the e2e for that scenario waits in its own PR.)
      (28 Nov: decision (A) built in #2225, Apply with the menu on also stores the light layout; the
      scenario's e2e in #2221, first-time-setup.spec.ts 7/7 live on NC 34.0.6 + PG16, done-thematiq.)
- [x] 5.4 The version card reads "Completed by admin on"; Set up again opens the wizard with the stored
      set; Later leaves setup done. (#E2E: `first-time setup: the wizard` in first-time-setup.spec.ts, live
      4/4 on a sandbox NC34 + MariaDB: 5.1 fixture through occ with every value put back and amsterdam's
      confirmation withdrawn and restored; 5.2 welcome on Dashboard and Files, Later writes nothing and
      it returns, non-admin sees nothing / no setup_status / 403, Skip; 5.4 the card and Set up again.
      5.3 is covered except the logo and favicon checks, the set without a workplace top bar, and the
      tour opening after "Start the apps menu walkthrough" (red on this sandbox: the menu's state had no
      walkthrough after the reload; the stored hand-off is pinned by vitest). 5.3 stays open for those.)

## 6. Live check

- [x] 6.1 On a fresh reference instance: enable thematiq, open the dashboard as admin and run the
      wizard through; open as a non-admin; screenshots against the TqSetup boards; the switch on the
      Theming settings page. (Walked live on a fresh NC 34 sandbox, #2180: Welcome to Done with
      Gemeente Amsterdam, Next held until the statement, Apply sets the set, the Nextcloud primary
      follows (#004699) and `setup.completed`; non-admin sees nothing. Fixed there: the house style step
      offered no cards and "All 0 sets" while the cold catalogue loaded, and a pick left another set's
      radio checked. Still open: the comparison with the TqSetup boards.)
      (28 Nov, DONE lane, board pass: the TqSetup boards are on design-system main but not yet on
      identity.conduction.nl, so they were rendered from design-system preview/screens/boards and set
      beside the live wizard at 1920x1080 on NC 34.0.6 + PostgreSQL. Every step body now follows its
      board: Welkom (feature cards, info box) and Appmenu (switch cards, selects, Display/Top bar, the
      separate Create row/button switches and the Contacts switch) #2227, Huisstijl (featured sets,
      upload card, summary panel) #2229, Theming (colour/logo/favicon cells, no double-escaped
      apostrophe) #2231, Inlogpagina (descriptions, preview) #2232, Klaar (applied banner, parts with
      details, walkthrough card) in this PR. Still different, in nextcloud-vue CnWizardDialog and not
      in this app: the boards' left-aligned "Thematiq instellen, stap n van 6" head with the step title,
      Skip as a link at the start of the footer, the chevrons on Next/Apply, and the walkthrough as the
      primary footer button on Klaar. 6.1 stays open for that frame.)
      (28 Nov, b-thematiq cloud, the frame: nextcloud-vue 2.244.1's board look already draws it, so
      no nextcloud-vue change was needed. thematiq uses it (look board, the eyebrow "Set up Thematiq,
      step n of 6" above the step's own title, chevrons), Skip is a bare underlined link at the start,
      and Done has Close beside the walkthrough as the primary button with its chevron after the
      label, with no Back. The bundle also keeps nextcloud-vue's unscoped board dialog, header and
      stepper rules (style-inject-loader `alsoKeep`), and the switches keep their track under the
      board look's checkbox rule. Live on a fresh NC 34.0.6 sandbox (sqlite, php -S, Imagick with
      SVG) at 1920x1080 beside TqSetupWelkom..TqSetupKlaar: first-time-setup.spec.ts 7/7.)
- [x] 6.2 On an instance configured by an earlier version, upgrade and confirm nothing opens and the
      version card reads the earlier-version state. (live lane, NC 34 sandbox: thematiq 1.2.10 from main
      with Gemeente Amsterdam set through its own settings API, upgraded in place to development by
      `occ upgrade`: status configured, not completed; the dashboard opens nothing and requests no
      wizard script; the version card reads "Set up by an earlier version", "Token set amsterdam".)
- [x] 6.3 Speed: an admin page with setup done and a non-admin page carry no wizard script; record
      the bundle size in the PR. (live lane, fresh NC 34 sandbox: after setup, Files and Theming load 0
      wizard requests; a non-admin's dashboard 0; `js/vendor/setupWizard.js` 913,649 B, 218,026 B gzip.)
