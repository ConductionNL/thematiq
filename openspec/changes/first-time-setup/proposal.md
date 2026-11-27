---
kind: code
depends_on:
  - apps-menu-walkthrough
chain:
  - apps-menu-walkthrough   # the tour the done step offers
  - first-time-setup        # this change
---

# First-time setup: welcome screen and setup wizard

## Why

Ruben's review of thematiq on a reference instance (27 November 2026, item 2): the other Conduction apps
greet an administrator with a welcome screen and a setup wizard (ADR-042, nextcloud-vue's
`CnSetupWizard` with the standard setup contract). thematiq has none. An administrator who installs it
gets a Nextcloud that looks the same as before and a settings page with dozens of panels, and has to
find out alone that a house style needs choosing, that Nextcloud's own logo and colours are a separate
step, and that the login page and the apps menu have options of their own.

thematiq changes the look of the whole of Nextcloud, not only a page of its own. So the wizard does not
wait on thematiq's settings page (changed by Ruben, 27 November 2026, about 13:50): it opens for an
administrator on the first Nextcloud page they open after thematiq is installed or enabled, wherever that
is.

## What changes

- **A welcome screen and wizard over the first page.** thematiq's global script opens it for a member
  of the `admin` group on any Nextcloud page (dashboard, files, any app) while thematiq is not set up.
  Other users, delegated theming administrators included, never see it and never load its script.
- **Six steps**: welcome; the house style (a token set, with the licence confirmation for a restricted
  set, or your own uploaded set); the Nextcloud theme (keep Nextcloud's logo, colours and favicon in line
  with the set, now and on later changes of set); the login page (follow the house style or Nextcloud
  default, hide the slogan); the apps menu (on or off, and its defaults, with the workplace top bar it
  needs); done, which offers the apps menu walkthrough (change apps-menu-walkthrough).
- **Nothing changes until Apply.** The steps collect the choices; Apply on the apps menu step writes
  them all in one checked request.
- **Later and Skip.** Later closes the wizard for this page; it opens again on the next page until setup
  is applied or skipped. "Skip, keep Nextcloud as it is" ends it without changing anything.
- **A version card** on thematiq's Theming settings page shows who completed setup and when, and offers
  Set up again, the apps menu walkthrough and Help us.
- **Completion is read from the saved configuration.** An instance that an earlier version already
  configured counts as set up and shows nothing, without a migration. An upgraded instance with nothing
  configured gets the wizard.
- **The ADR-042 setup routes**, `GET /api/setup/status` and `POST /api/setup/action/{actionId}`,
  administrators only, every write through the same validation, licence gate and audit as the settings
  page.

## Not in this change

- New theming options on the settings page. Every step sets something the settings page already sets,
  except the new "keep the Nextcloud theme in line" value, which the wizard and the version card show.
  A settings-page control for it is not drawn yet.
- A wizard for subadmins or delegated theming administrators.
- Seeding. thematiq has no data to seed; Apply writes configuration.

## Boards

ConductionNL/design-system#1213: TqSetupWelkom, TqSetupHuisstijl, TqSetupHuisstijlLicentie,
TqSetupTheming, TqSetupInlogpagina, TqSetupAppmenu, TqSetupKlaar and TqSetupOpnieuw. Boards are canon;
`design.md` carries their copy. See `screens.md`.

## Impact

- new `lib/Service/SetupStatusService.php`, `lib/Service/SetupActions.php`,
  `lib/Controller/SetupController.php`, routes `GET /api/setup/status`,
  `POST /api/setup/action/{actionId}`
- new `lib/Listener/SetupWizardListener.php` on `BeforeTemplateRenderedEvent`: the `setup_status`
  initial state and the loader script, for an administrator on a user page while setup is due
- `lib/Service/ActiveTokenSetService.php`: apply the set to Nextcloud's theme on every switch while
  `core_theming_follows_set` is `1`
- new `js/setup-wizard-loader.js`, `src/setupWizard.js`, `webpack.setup-wizard.config.js`, bundled to
  `js/vendor/setupWizard.js`
- `src/manifest.json`: the ADR-042 `setup` block
- `templates/settings/admin.php`, new `js/admin-version-card.js`: the version card
- l10n: the wizard's copy in English and Dutch
- new `tests/e2e/spec-coverage/first-time-setup.spec.ts`
