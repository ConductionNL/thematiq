# Tasks: first-time-setup

Nothing here is built until this change and its boards (design-system branch
`design/thematiq-apps-menu-walkthrough`) are merged. Copy and layout follow the boards. Task 5.3 needs
apps-menu-walkthrough built.

## 1. Status and contract

- [ ] 1.1 `lib/Service/SetupStatusService.php`: configured, in progress, completed, skipped and each
      step's done, from the stored values; cached per request. REQ-TQFS-002.
- [ ] 1.2 `lib/Service/SetupActions.php`: `apply-house-style` through `ActiveTokenSetService::switchTo()`,
      `apply-nextcloud-theming` through the `--sync-core` path with the ticked parts, `skip`, `complete`;
      the first write stores `setup.inProgress`; audit entries with the actor. REQ-TQFS-004,
      REQ-TQFS-006, REQ-TQFS-007, REQ-TQFS-010.
- [ ] 1.3 `lib/Controller/SetupController.php` and the routes `GET /api/setup/status`,
      `POST /api/setup/config`, `POST /api/setup/action/{actionId}`; admin only (no
      `#[NoAdminRequired]`), CSRF on the POSTs; config through `LayoutOptionValues` and
      `AppMenuSettings::saveDefaults()`, whole patch refused with 400 naming the key; 404 for an unknown
      action; 423 under a configuration source through the existing middleware. REQ-TQFS-004,
      REQ-TQFS-008, REQ-TQFS-009.
- [ ] 1.4 PHPUnit `tests/Unit/Service/SetupStatusServiceTest.php` (configured by an earlier version, a
      run in progress stays due, a configuration source counts as configured, each step's done),
      `tests/Unit/Service/SetupActionsTest.php` (the licence gate, the sync parts, skip changes nothing),
      `tests/Unit/Controller/SetupControllerTest.php` (a wrong value refuses the patch, an unknown action
      is not found, the menu switch stores the style).
- [ ] 1.5 Hydra gates on the new routes: route-auth, semantic-auth, no-admin-idor, route-reachability.

## 2. Opening the wizard on any page

- [ ] 2.1 `lib/Listener/SetupWizardListener.php` on `BeforeTemplateRenderedEvent`: user pages only,
      `admin` group only, setup due only; provide `setup_status` and add `js/setup-wizard-loader.js`.
      Registered in `Application::register()`. REQ-TQFS-001.
- [ ] 2.2 `js/setup-wizard-loader.js`: add `js/vendor/setupWizard.js` once the page is idle; wait while
      Nextcloud's first-run wizard is open. REQ-TQFS-001.
- [ ] 2.3 PHPUnit `tests/Unit/Listener/SetupWizardListenerTest.php` (only an admin's user page gets the
      wizard; nothing once set up; nothing for a non-admin, guest or public page).

## 3. The wizard

- [ ] 3.1 `src/manifest.json`: the ADR-042 `setup` block with the steps welcome, house-style,
      nextcloud-theming, login-page, apps-menu, finish.
- [ ] 3.2 `src/setupWizard.js` and `webpack.setup-wizard.config.js` building `js/vendor/setupWizard.js`
      (gitignored, run by `npm run build`): mount `CnSetupWizard` in its own container, Later on every
      step and the close button, Skip from the welcome step on. REQ-TQFS-003, REQ-TQFS-005.
- [ ] 3.3 The house style step: the set list, search, Nextcloud first, the licence block with the
      statement, confirm then apply, the refusal in the step. REQ-TQFS-006.
- [ ] 3.4 The Nextcloud theming step: the plan with current and new values, the part checkboxes, Keep
      Nextcloud's own, skipped for Nextcloud. REQ-TQFS-007.
- [ ] 3.5 The login page and apps menu steps as config fields with their previews. REQ-TQFS-008,
      REQ-TQFS-009.
- [ ] 3.6 The finish step: the summary, Finish, Finish and show me the apps menu (stores the tour
      progress), reload. REQ-TQFS-010.
- [ ] 3.7 vitest `tests/vitest/setupWizard.spec.js`: Later stores nothing, Skip runs `skip`, the
      restricted set flow and its refusal, the theming parts posted, the tour handoff.
- [ ] 3.8 l10n: the wizard's copy in English and Dutch; `npm run test:l10n` green.

## 4. The version card

- [ ] 4.1 `templates/settings/admin.php` and new `js/admin-version-card.js`: the version card with the
      installed version, the setup state and Set up again, disabled under a configuration source.
      REQ-TQFS-011.
- [ ] 4.2 vitest `tests/vitest/adminVersionCard.spec.js`; PHPUnit for the card's state in
      `lib/Settings/Admin.php`.

## 5. End to end

- [ ] 5.1 `tests/e2e/spec-coverage/first-time-setup.spec.ts` (`@e2e` tags per scenario): in setup, read
      and remove the setup-relevant app values and `setup.*` through `occPrefix()`, and put every one
      back in a `finally`, with `_theme-state` restoring the active set and core theming; withdraw the
      confirmation of `amsterdam` when it is not applied, and restore it after.
- [ ] 5.2 Walk: the welcome on the dashboard and in Files; a non-admin sees nothing and the page has no
      `setup_status`; Later and back on the next page; Skip and nothing on the next page; a configured
      instance shows nothing; status is 403 for a non-admin.
- [ ] 5.3 Full run: choose `amsterdam`, Next disabled until the statement, applied and audited; the
      Nextcloud theming step sets the core primary colour, logo and favicon (`_core-theming.ts`); the
      login step hides the slogan on an anonymous login page; the menu step sets 3 columns seen by a
      non-admin; Finish and show me the apps menu, then the tour starts on the next menu open.
- [ ] 5.4 Set up again from the version card opens the wizard with the stored set; Later leaves setup
      done.

## 6. Live check

- [ ] 6.1 On a fresh reference instance: enable thematiq, open the dashboard as admin and run the
      wizard through; open as a non-admin; screenshots against the boards.
- [ ] 6.2 On an instance configured by an earlier version, upgrade and confirm nothing opens and the
      version card reads "Set up".
- [ ] 6.3 Speed: an admin page with setup done and a non-admin page carry no wizard script; record
      the bundle size in the PR.
