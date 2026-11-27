---
kind: code
depends_on:
  - apps-menu-walkthrough
chain:
  - apps-menu-walkthrough   # the tour the finish step offers
  - first-time-setup        # this change
---

# First-time setup: welcome screen and setup wizard

## Why

Ruben's review of thematiq on a reference instance (27 November 2026, item 2): the other Conduction apps
greet an administrator with a welcome screen and a setup wizard (ADR-042, nextcloud-vue's
`CnSetupWizard` with the `/api/setup/status|action|config` contract). thematiq has none. An
administrator who installs it gets a Nextcloud that looks the same as before and a settings page with
dozens of panels, and has to find out alone that a house style needs choosing, that Nextcloud's own logo
and colours are a separate step, and that the login page and the apps menu have options of their own.

thematiq changes the look of the whole of Nextcloud, not only a page of its own. So the wizard does not
wait on thematiq's settings page (changed by Ruben, 27 November 2026, about 13:50): it opens for an
administrator on the first Nextcloud page they open after thematiq is installed or enabled, wherever that
is.

## What changes

- **A welcome screen and wizard over the first page.** thematiq's global script opens it for an
  administrator on any Nextcloud page (dashboard, files, any app) while thematiq is not set up. Other
  users never see it and never load its script.
- **Six steps**: welcome (what thematiq does); the house style (a token set, with the licence
  confirmation for a restricted set); apply it to Nextcloud's own theming (logo, colours, favicon, the
  `--sync-core` behaviour); the login page style; the apps menu (on or off, and its defaults); finish,
  which offers the apps menu tour (change apps-menu-walkthrough).
- **Later and Skip.** Later closes the wizard for this page; it opens again on the next page until setup
  is finished or skipped. "Skip, keep Nextcloud as it is" ends it without changing anything.
- **Set up again** from a version card on thematiq's Theming settings page, at any time.
- **Completion is read from the saved configuration.** An instance that an earlier version already
  configured counts as set up and shows nothing, without a migration.
- **The standard setup contract.** `GET /api/setup/status`, `POST /api/setup/config` and
  `POST /api/setup/action/{actionId}`, administrators only, every write through the same validation,
  licence gate and audit as the settings page.

## Not in this change

- New theming options. Every step sets something the settings page already sets.
- A wizard for subadmins or delegated theming administrators. Only Nextcloud administrators see it,
  because it changes Nextcloud's own theming.
- Seeding. thematiq has no data to seed; its run-actions apply configuration.

## Boards

The wizard and version card boards are drawn by the design agent in ConductionNL/design-system, branch
`design/thematiq-apps-menu-walkthrough`. Boards are canon; see `screens.md`.

## Impact

- new `lib/Service/SetupStatusService.php`, `lib/Service/SetupActions.php`,
  `lib/Controller/SetupController.php`, routes `GET /api/setup/status`, `POST /api/setup/config`,
  `POST /api/setup/action/{actionId}`
- new `lib/Listener/SetupWizardListener.php` on `BeforeTemplateRenderedEvent`: the `setup_status`
  initial state and the loader script, for an administrator on a user page while setup is due
- new `js/setup-wizard-loader.js`, `src/setupWizard.js`, `webpack.setup-wizard.config.js`, bundled to
  `js/vendor/setupWizard.js`
- `src/manifest.json`: the ADR-042 `setup` block
- `templates/settings/admin.php`, new `js/admin-version-card.js`: the version card with Set up again
- l10n: the wizard's copy in English and Dutch
- new `tests/e2e/spec-coverage/first-time-setup.spec.ts`
