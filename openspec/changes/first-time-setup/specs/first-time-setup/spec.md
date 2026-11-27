# first-time-setup

## ADDED Requirements

### Requirement: The wizard opens for an administrator on the first Nextcloud page while setup is due (REQ-TQFS-001)

While setup is due, thematiq's global script MUST open the welcome screen and setup wizard
(nextcloud-vue's `CnSetupWizard`, ADR-042) over any Nextcloud user page an administrator opens: the
dashboard, Files, any app, thematiq's own settings page. It MUST be shown only to members of the `admin`
group. A page for anyone else, and a guest, public, error or login page, MUST carry neither the
`setup_status` initial state nor the wizard's script. The wizard MUST NOT open while Nextcloud's own
first-run wizard is open; it opens once that one is closed.

#### Scenario: The administrator meets the wizard on the dashboard
- GIVEN thematiq was just enabled on an instance with no thematiq configuration
- WHEN an administrator opens the dashboard
- THEN the welcome screen MUST open over the dashboard with the title "Welcome to thematiq"

#### Scenario: The wizard opens in any app
- GIVEN setup is due
- WHEN an administrator opens Files
- THEN the welcome screen MUST open over Files

#### Scenario: Other users never see it
- GIVEN setup is due
- WHEN a user who is not an administrator opens the dashboard
- THEN no wizard MUST open
- AND the page MUST NOT carry the `setup_status` initial state or load the wizard's script

#### Scenario: A public share page carries nothing
@e2e exclude a public page has no signed-in user, so the listener returns before any work; proven by tests/Unit/Listener/SetupWizardListenerTest.php (testOnlyAnAdminsUserPageGetsTheWizard)
- GIVEN setup is due
- WHEN anyone opens a public share link
- THEN the page MUST NOT carry the wizard's state or script

### Requirement: Setup status is derived from the saved configuration (REQ-TQFS-002)

Setup MUST count as done when the app value `setup.completed` or `setup.skipped` exists, or when the
instance is configured and no wizard run is in progress. The instance MUST count as configured when any
of the app values `token_set`, `header_style`, `hide_slogan`, `login_watermark`,
`brand_stripe_placement` or a `menu.<key>` default is stored, or when `thematiq.config_source` is set in
`config.php`. The first write through the setup contract MUST store `setup.inProgress`, and while it is
there without `setup.completed` or `setup.skipped`, setup MUST stay due. No repair step or migration is
needed: an instance configured by an earlier version MUST count as set up on its first page after the
upgrade.

#### Scenario: An instance configured by an earlier version shows nothing
- GIVEN an instance whose `token_set` is `zuiddrecht`, stored before this version, and no `setup.*` value
- WHEN an administrator opens the dashboard
- THEN no wizard MUST open

#### Scenario: A saved step does not end the run
@e2e exclude covered end to end by "Later brings it back on the next page" after a saved step; the derivation itself by tests/Unit/Service/SetupStatusServiceTest.php (testARunInProgressStaysDue)
- GIVEN an administrator applied a house style in the wizard and closed it with Later
- WHEN they open another page
- THEN the wizard MUST open again at the step after the house style

#### Scenario: Theme as code counts as configured
@e2e exclude needs config.php; proven by tests/Unit/Service/SetupStatusServiceTest.php (testAConfigSourceCountsAsConfigured)
- GIVEN `thematiq.config_source` is set and no thematiq app value is stored
- WHEN an administrator opens the dashboard
- THEN no wizard MUST open

### Requirement: Later closes the wizard for this page, Skip ends it (REQ-TQFS-003)

Every step MUST offer Later, and the dialog's close button and Escape MUST act as Later: the wizard
closes and nothing is stored, so it opens again on the next page load while setup is due. The welcome
step and every step after it MUST offer "Skip, keep Nextcloud as it is", which MUST run the `skip`
action: store `setup.skipped` with the administrator and the time and change nothing else.

#### Scenario: Later brings it back on the next page
- GIVEN the welcome screen is open
- WHEN the administrator presses Later
- THEN the wizard MUST close and the page MUST be usable
- WHEN they open another page
- THEN the welcome screen MUST open again

#### Scenario: Skip keeps Nextcloud as it is
- GIVEN the welcome screen is open on an instance with no thematiq configuration
- WHEN the administrator presses "Skip, keep Nextcloud as it is"
- THEN the wizard MUST close and no token set, login or menu value MUST be stored
- AND on the next page no wizard MUST open

### Requirement: The setup contract is for administrators only (REQ-TQFS-004)

thematiq MUST serve `GET /apps/thematiq/api/setup/status`, `POST /apps/thematiq/api/setup/config` and
`POST /apps/thematiq/api/setup/action/{actionId}` to administrators only, refused by Nextcloud's
middleware with 403 before the controller runs for anyone else, with CSRF on both POSTs. Status MUST
answer `{version, completed, skipped, configured, inProgress, steps}`, each step `{done, detail}`.
Config MUST take only `hide_slogan`, `login_watermark`, `brand_stripe_placement` and `menu`, check
each value with the rules of the settings page and of `POST /app-menu/defaults`, and refuse the whole
patch with 400 naming the key when one is unknown or wrong, storing nothing. Actions MUST be
`apply-house-style`, `apply-nextcloud-theming`, `skip` and `complete`; any other id MUST answer 404.
Every write MUST be in the theming audit trail with its actor. Under a configuration source every
write MUST be refused with 423, as the settings page is.

#### Scenario: A user who is not an administrator is refused
- GIVEN a user who is not an administrator
- WHEN they request `GET /apps/thematiq/api/setup/status`
- THEN the answer MUST be 403

#### Scenario: A wrong value stores nothing
@e2e exclude pure controller logic: PHPUnit tests/Unit/Controller/SetupControllerTest.php (testAWrongValueRefusesThePatch)
- GIVEN an administrator
- WHEN they post `{hide_slogan: true, menu: {columns: 9}}` to the setup config
- THEN the answer MUST be 400 naming `menu.columns`
- AND `hide_slogan` MUST NOT be stored

#### Scenario: An unknown action is not found
@e2e exclude pure controller logic: PHPUnit tests/Unit/Controller/SetupControllerTest.php (testAnUnknownActionIsNotFound)
- GIVEN an administrator
- WHEN they post to `/apps/thematiq/api/setup/action/seed`
- THEN the answer MUST be 404 and nothing MUST change

### Requirement: The welcome step says what thematiq does (REQ-TQFS-005)

The first step MUST be a welcome that says in at most three short sentences that thematiq gives all of
Nextcloud the organisation's house style (colours, logo, fonts, the login page and the apps menu), and
offer Start, Later and "Skip, keep Nextcloud as it is". The copy MUST be in English and Dutch, without
em-dashes and in sentence case.

#### Scenario: The welcome offers three ways on
- GIVEN the welcome screen is open
- THEN it MUST offer Start, Later and "Skip, keep Nextcloud as it is"

### Requirement: The house style step applies a token set through the licence gate (REQ-TQFS-006)

The house style step MUST list the token sets as the settings page does, searchable, with Nextcloud
(keep Nextcloud's look) first and the stored set selected. Choosing a restricted set without a
confirmation MUST show its warning, the holder, the source package and version, the full licence text
and the statement "We are this organisation, or we have its permission to use this house style." with a
checkbox, and Next MUST stay disabled until the box is ticked. Next MUST then store the confirmation
through `POST /settings/tokensets/{id}/licence/confirmation` and run `apply-house-style`, which MUST
apply the set through the same service, licence gate, audit entry and saved version as the settings
page. A refusal (403 `restricted_set_not_confirmed`) MUST show in the step, which stays open.

#### Scenario: An open set applies at once
- GIVEN the house style step is open
- WHEN the administrator chooses an open-licensed set and presses Next
- THEN that set MUST be the active token set
- AND the audit log MUST hold a token set change by that administrator

#### Scenario: A restricted set needs the statement
- GIVEN the house style step is open and `amsterdam` is restricted and not confirmed
- WHEN the administrator chooses `amsterdam`
- THEN the warning, the holder, the source package and the licence text MUST show
- AND Next MUST be disabled
- WHEN they tick the statement and press Next
- THEN `amsterdam` MUST be confirmed by that administrator and be the active token set

#### Scenario: A confirmation withdrawn in between is refused
@e2e exclude a race between two administrators; proven by tests/vitest/setupWizard.spec.js (a 403 restricted_set_not_confirmed keeps the step open and shows the refusal) and tests/Unit/Service/SetupActionsTest.php (testTheHouseStyleGoesThroughTheLicenceGate)
- GIVEN the administrator ticked the statement for `amsterdam`
- AND another administrator withdrew its confirmation before Next
- WHEN the house style is applied and refused
- THEN the step MUST show the refusal and stay open

### Requirement: The Nextcloud theming step applies logo, colours and favicon (REQ-TQFS-007)

After the house style, the wizard MUST show what applying the set to Nextcloud's own theming would
change, for the logo, the primary and background colour and the favicon, each with its current and new
value and a checkbox ticked when the set brings it. Next MUST run `apply-nextcloud-theming` with the
ticked parts, through the same service as `occ thematiq:theme:set --sync-core` and the theming sync
dialog, and report each part as applied or skipped. "Keep Nextcloud's own" MUST untick every part, and
Next then changes nothing in Nextcloud's theming. With Nextcloud chosen as house style the step MUST be
skipped.

#### Scenario: The set's logo and colour reach Nextcloud
- GIVEN the administrator applied a set whose theming metadata has a primary colour, a logo and a favicon
- WHEN the Nextcloud theming step is left with every part ticked
- THEN Nextcloud's primary colour, logo and favicon MUST be the set's

#### Scenario: Keep Nextcloud's own
- GIVEN the Nextcloud theming step is open
- WHEN the administrator chooses "Keep Nextcloud's own" and presses Next
- THEN Nextcloud's theming values MUST be unchanged

### Requirement: The login page step sets the login page style (REQ-TQFS-008)

The login page step MUST set whether the Nextcloud slogan is hidden, the login watermark and where the
brand stripe shows, with the choices and help text of the settings page and a preview of the login
card, and store them through `POST /api/setup/config`.

#### Scenario: The slogan is hidden on the login page
- GIVEN the login page step is open
- WHEN the administrator turns on hiding the Nextcloud slogan and presses Next
- THEN an anonymous visitor of the login page MUST NOT see the slogan

### Requirement: The apps menu step sets the menu and its defaults (REQ-TQFS-009)

The apps menu step MUST have a switch "Use the workplace apps menu", stored as the administrator's
`menu.style` default (workplace on, classic off), and, while it is on, the defaults for columns, rows
above More, the Create section and the external apps with the Menu settings grid preview. They MUST be
stored as the administrator's `menu.<key>` defaults through `POST /api/setup/config`, checked as
`POST /app-menu/defaults` checks them.

#### Scenario: The menu defaults reach everyone
- GIVEN the apps menu step is open
- WHEN the administrator keeps the workplace menu on, sets 3 columns and presses Next
- THEN a user who is not an administrator MUST see the apps menu with 3 columns

#### Scenario: The classic menu
@e2e exclude switching everyone to the classic style is proven against the shared instance by apps-menu-settings; the stored default by tests/Unit/Controller/SetupControllerTest.php (testTheMenuSwitchStoresTheStyle)
- GIVEN the apps menu step is open
- WHEN the administrator turns the workplace apps menu off and presses Next
- THEN the administrator's `menu.style` default MUST be classic

### Requirement: Finish records completion and offers the apps menu tour (REQ-TQFS-010)

The finish step MUST sum up what was set and offer "Finish" and, when the workplace apps menu is on,
"Finish and show me the apps menu". Both MUST run the `complete` action, which stores `setup.completed`
with the administrator and the time and removes `setup.inProgress`, and reload the page so the house
style draws. "Finish and show me the apps menu" MUST also store the apps menu tour's progress at its
first step for that administrator, so the next open of the apps menu starts the tour.

#### Scenario: Finish and take the tour
- GIVEN the finish step is open with the workplace apps menu on
- WHEN the administrator presses "Finish and show me the apps menu"
- THEN the page MUST reload without the wizard
- WHEN they open the apps menu
- THEN the apps menu tour MUST start at its first step

#### Scenario: Finish ends setup
- GIVEN the finish step is open
- WHEN the administrator presses Finish
- THEN on the next page no wizard MUST open

### Requirement: Setup can be run again from the version card (REQ-TQFS-011)

thematiq's Theming settings page MUST show a version card with the installed version, the setup state
("Set up on <date> by <user>", "Set up" for an instance configured before this version, "Skipped" or
"Not set up") and a "Set up again" button, for administrators. Set up again MUST open the wizard at the
welcome step, filled with the stored values, without resetting anything. Closing a rerun with Later
MUST leave setup done. Finishing a rerun MUST store `setup.completed` again with the new administrator
and time. Under a configuration source the button MUST be disabled with the note who manages the house
style.

#### Scenario: Set up again after finishing
- GIVEN setup was finished
- WHEN the administrator presses "Set up again" on the version card
- THEN the wizard MUST open at the welcome step with the stored house style selected on the house style step
- WHEN they press Later
- THEN on the next page no wizard MUST open

#### Scenario: Theme as code disables the button
@e2e exclude needs config.php; proven by tests/vitest/adminVersionCard.spec.js (the button is disabled with the source note under a configuration source)
- GIVEN `thematiq.config_source` is set
- WHEN an administrator opens the Theming settings page
- THEN "Set up again" MUST be disabled with the note who manages the house style
