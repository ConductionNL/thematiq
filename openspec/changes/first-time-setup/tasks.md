# Tasks: first-time-setup

Nothing here is built until this change and its boards (ConductionNL/design-system#1213) are merged.
Copy and layout follow the boards; each task names its board. Task 5.3 needs apps-menu-walkthrough built.

## 1. Status and contract

- [ ] 1.1 `lib/Service/SetupStatusService.php`: configured, completed, skipped, each step's done and the
      stored choices (`current`), from the stored values; cached per request. REQ-TQFS-002.
- [ ] 1.2 `lib/Service/SetupActions.php`: `apply` checks the whole request, then writes the licence
      confirmation, the set through `ActiveTokenSetService::switchTo()`, Nextcloud's theme through the
      `--sync-core` path and `core_theming_follows_set`, `themed_contexts` and `hide_slogan`, the menu
      defaults through `AppMenuSettings::saveDefaults()` and `header_style` workplace when the menu
      needs the bar, then `setup.completed`; reports each part; `skip`; audit entries with the actor.
      REQ-TQFS-003, REQ-TQFS-004, REQ-TQFS-007 to REQ-TQFS-010.
- [ ] 1.3 `lib/Controller/SetupController.php` and the routes `GET /api/setup/status`,
      `POST /api/setup/action/{actionId}`; admin only (no `#[NoAdminRequired]`), CSRF on the POST; 400
      naming the key, 404 for an unknown action, 423 under a configuration source through the existing
      middleware. REQ-TQFS-005.
- [ ] 1.4 `lib/Service/ActiveTokenSetService.php`: while `core_theming_follows_set` is `1`, every switch
      (settings page, occ, planned switch) also applies the set to Nextcloud's theme with the snapshot as
      a version. REQ-TQFS-008.
- [ ] 1.5 PHPUnit `tests/Unit/Service/SetupStatusServiceTest.php` (configured by an earlier version, an
      upgrade with nothing stored is due, a configuration source counts as configured, each step's done),
      `tests/Unit/Service/SetupActionsTest.php` (the licence gate, a failed part is reported and
      completion waits, skip changes nothing, the Nextcloud login removes the login context, the menu
      brings `header_style`), `tests/Unit/Controller/SetupControllerTest.php` (a wrong value refuses the
      request, an unknown action is not found, the menu switch stores the style),
      `ActiveTokenSetServiceTest` (a following theme is synced on every switch).
- [ ] 1.6 Hydra gates on the new routes: route-auth, semantic-auth, no-admin-idor, route-reachability.

## 2. Opening the wizard on any page

- [ ] 2.1 `lib/Listener/SetupWizardListener.php` on `BeforeTemplateRenderedEvent`: user pages only,
      `admin` group only, setup due only; provide `setup_status` and add `js/setup-wizard-loader.js`.
      Registered in `Application::register()`. REQ-TQFS-001.
- [ ] 2.2 `js/setup-wizard-loader.js`: add `js/vendor/setupWizard.js` once the page is idle; wait while
      Nextcloud's first-run wizard is open. REQ-TQFS-001.
- [ ] 2.3 PHPUnit `tests/Unit/Listener/SetupWizardListenerTest.php` (only an admin's user page gets the
      wizard; nothing once set up; nothing for a non-admin, a delegated theming administrator, a guest
      or a public page).

## 3. The wizard

- [ ] 3.1 `src/manifest.json`: the ADR-042 `setup` block with the steps welcome, house-style,
      nextcloud-theme, login-page, apps-menu, done.
- [ ] 3.2 `src/setupWizard.js` and `webpack.setup-wizard.config.js` building `js/vendor/setupWizard.js`
      (gitignored, run by `npm run build`): mount `CnSetupWizard` in its own container; the counter and
      rail; Skip and Later on steps 1 to 5, the close button "Later, continue on the next page"; nothing
      written before Apply. TqSetupWelkom. REQ-TQFS-003, REQ-TQFS-004, REQ-TQFS-006.
- [ ] 3.3 The house style step: the radio cards, the summary line, the gallery link, Upload your own set
      through the existing upload route, the licence block with the statement and Next held until it is
      ticked. TqSetupHuisstijl, TqSetupHuisstijlLicentie. REQ-TQFS-007.
- [ ] 3.4 The Nextcloud theme step: the switch, the four cells with now and new, the version note;
      skipped for Nextcloud default. TqSetupTheming. REQ-TQFS-008.
- [ ] 3.5 The login page step: the two styles, the slogan checkbox, the login card preview.
      TqSetupInlogpagina. REQ-TQFS-009.
- [ ] 3.6 The apps menu step: the switch, style, columns, rows above More apps, the three switches,
      Apply with the parts reported on a failure. TqSetupAppmenu. REQ-TQFS-003, REQ-TQFS-010.
- [ ] 3.7 The done step: the summary rows, Close, Start the apps menu walkthrough (stores the immediate
      start, reloads). TqSetupKlaar. REQ-TQFS-011.
- [ ] 3.8 vitest `tests/vitest/setupWizard.spec.js`: Later and the steps store nothing, Skip runs `skip`
      and an uploaded set stays, the restricted set flow and a refusal on Apply, the parts on a failure,
      the walkthrough handoff.
- [ ] 3.9 l10n: the boards' copy in English and Dutch; `npm run test:l10n` green.

## 4. The version card

- [ ] 4.1 `templates/settings/admin.php` and new `js/admin-version-card.js`: the version card with the
      installed version, the setup state and detail, Set up again, Apps menu walkthrough and Help us,
      Set up again disabled under a configuration source. TqSetupOpnieuw. REQ-TQFS-012.
- [ ] 4.2 vitest `tests/vitest/adminVersionCard.spec.js`; PHPUnit for the card's state in
      `lib/Settings/Admin.php`.

## 5. End to end

- [ ] 5.1 `tests/e2e/spec-coverage/first-time-setup.spec.ts` (`@e2e` tags per scenario): in setup, read
      and remove the setup-relevant app values and `setup.*` through `occPrefix()`, and put every one
      back in a `finally`, with `_theme-state` restoring the active set and core theming; withdraw the
      confirmation of `amsterdam` when it is not applied, and restore it after.
- [ ] 5.2 Walk: the welcome on the dashboard and in Files; a non-admin sees nothing and the page has no
      `setup_status`; walking the steps and Later writes nothing and the wizard comes back; Skip and
      nothing on the next page; a configured instance shows nothing; status is 403 for a non-admin.
- [ ] 5.3 Full run: choose `amsterdam`, Next disabled until the statement; keep the theme switch on;
      Follow the house style with the slogan hidden; menu on with 3 columns on a set without the
      workplace top bar; Apply; then the set is active and audited, the core primary colour, logo and
      favicon are the set's (`_core-theming.ts`), an anonymous login page has no slogan, a non-admin
      sees the workplace top bar and 3 columns; Start the apps menu walkthrough opens the menu with the
      tour.
- [ ] 5.4 The version card reads "Completed by admin on"; Set up again opens the wizard with the stored
      set; Later leaves setup done.

## 6. Live check

- [ ] 6.1 On a fresh reference instance: enable thematiq, open the dashboard as admin and run the
      wizard through; open as a non-admin; screenshots against the TqSetup boards.
- [ ] 6.2 On an instance configured by an earlier version, upgrade and confirm nothing opens and the
      version card reads the earlier-version state.
- [ ] 6.3 Speed: an admin page with setup done and a non-admin page carry no wizard script; record
      the bundle size in the PR.
