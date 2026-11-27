# first-time-setup

## ADDED Requirements

### Requirement: The wizard opens for an administrator on the first Nextcloud page while setup is due (REQ-TQFS-001)

While setup is due, thematiq's global script MUST open the welcome screen and setup wizard
(nextcloud-vue's `CnSetupWizard`, ADR-042) over any Nextcloud user page an administrator opens: the
dashboard, Files, any app, thematiq's own settings page. It MUST be shown only to members of the `admin`
group; delegated theming administrators and subadmins MUST NOT get it. A page for anyone else, and a
guest, public, error or login page, MUST carry neither the `setup_status` initial state nor the
wizard's script. The wizard MUST NOT open while Nextcloud's own first-run wizard is open; it opens once
that one is closed.

Board: TqSetupWelkom.

#### Scenario: The administrator meets the wizard on the dashboard
- GIVEN thematiq was just enabled on an instance with no thematiq configuration
- WHEN an administrator opens the dashboard
- THEN the welcome screen MUST open over the dashboard with the title "Welcome to Thematiq" and "Set up Thematiq, step 1 of 6"

#### Scenario: The wizard opens in any app
- GIVEN setup is due
- WHEN an administrator opens Files
- THEN the welcome screen MUST open over Files

#### Scenario: Other users never see it
- GIVEN setup is due
- WHEN a user who is not an administrator opens the dashboard
- THEN no wizard MUST open
- AND the page MUST NOT carry the `setup_status` initial state or load the wizard's script

#### Scenario: A delegated theming administrator does not get it
@e2e exclude needs a delegation set up on the shared instance; proven by tests/Unit/Listener/SetupWizardListenerTest.php (testADelegatedAdministratorGetsNoWizard)
- GIVEN setup is due and a user who is not in the `admin` group has delegated access to the Theming settings
- WHEN that user opens the dashboard
- THEN no wizard MUST open and the page MUST NOT carry the wizard's state or script

#### Scenario: A public share page carries nothing
@e2e exclude a public page has no signed-in user, so the listener returns before any work; proven by tests/Unit/Listener/SetupWizardListenerTest.php (testOnlyAnAdminsUserPageGetsTheWizard)
- GIVEN setup is due
- WHEN anyone opens a public share link
- THEN the page MUST NOT carry the wizard's state or script

### Requirement: Setup status is derived from the saved configuration (REQ-TQFS-002)

Setup MUST count as done when the app value `setup.completed` or `setup.skipped` exists, or when the
instance is configured. The instance MUST count as configured when any of the app values `token_set`,
`header_style`, `themed_contexts`, `hide_slogan`, `login_watermark`, `brand_stripe_placement`,
`core_theming_follows_set` or a `menu.<key>` default is stored, or when `thematiq.config_source` is set
in `config.php`. No repair step or migration is needed: an instance configured by an earlier version
MUST count as set up on its first page after the upgrade, and an instance upgraded with none of these
stored MUST get the wizard.

Board: TqSetupWelkom.

#### Scenario: An instance configured by an earlier version shows nothing
- GIVEN an instance whose `token_set` is `zuiddrecht`, stored before this version, and no `setup.*` value
- WHEN an administrator opens the dashboard
- THEN no wizard MUST open

#### Scenario: An upgraded instance that was never configured gets the wizard
@e2e exclude an upgrade cannot be staged on the test instance; the same state is the fresh-instance scenario of REQ-TQFS-001, and the derivation is proven by tests/Unit/Service/SetupStatusServiceTest.php (testAnUpgradeWithNothingStoredIsDue)
- GIVEN an instance upgraded from an earlier thematiq with none of the configuration values stored and no `setup.*` value
- WHEN an administrator opens the dashboard
- THEN the welcome screen MUST open

#### Scenario: Theme as code counts as configured
@e2e exclude needs config.php; proven by tests/Unit/Service/SetupStatusServiceTest.php (testAConfigSourceCountsAsConfigured)
- GIVEN `thematiq.config_source` is set and no thematiq app value is stored
- WHEN an administrator opens the dashboard
- THEN no wizard MUST open

### Requirement: Nothing changes until Apply (REQ-TQFS-003)

The steps before Apply MUST NOT write any configuration. Apply, on the apps menu step, MUST send every
choice in one `apply` action, which MUST check the whole request before it writes anything and then
write, in order, the licence confirmation, the token set, Nextcloud's theme, the login page, the apps
menu and `setup.completed`. A token set uploaded with "Upload your own set" MUST be stored among the own
sets, and MUST NOT be applied before Apply. When a write fails during Apply, the step MUST show which
parts were applied and which were not, stay open, and store `setup.completed` only once every part is
applied.

Boards: TqSetupAppmenu, TqSetupKlaar, TqSetupOpnieuw.

#### Scenario: Walking the steps writes nothing
- GIVEN the wizard on the welcome screen
- WHEN the administrator chooses a house style, the theme switch and the login style, and presses Later on the apps menu step
- THEN the active token set, Nextcloud's theme and the login page MUST be unchanged
- AND on the next page the welcome screen MUST open again

#### Scenario: A failed write keeps the step open
@e2e exclude a write fault cannot be caused on the shared instance; proven by tests/Unit/Service/SetupActionsTest.php (testAFailedPartIsReportedAndCompletionWaits) and tests/vitest/setupWizard.spec.js (the step shows the parts and stays open)
- GIVEN Apply was pressed and storing the menu defaults fails
- THEN the step MUST say the menu was not applied and the other parts were
- AND `setup.completed` MUST NOT be stored

### Requirement: Later closes the wizard for this page, Skip ends it (REQ-TQFS-004)

Steps 1 to 5 MUST offer "Skip, keep Nextcloud as it is" and Later. Later and the close button ("Later,
continue on the next page") MUST close the wizard and store nothing, so it opens again on the next page
load while setup is due. Skip MUST run the `skip` action: store `setup.skipped` with the administrator
and the time and change nothing else. What was stored before Skip MUST stay: with nothing applied before
Apply, that is at most a set uploaded in the house style step, which stays among the own sets.

Boards: TqSetupWelkom, TqSetupHuisstijl.

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

#### Scenario: Skip on a later step keeps an uploaded set
@e2e exclude uploading a set in the wizard is covered by tests/vitest/setupWizard.spec.js (an uploaded set stays after Skip); Skip itself is covered by the scenario above
- GIVEN the administrator uploaded their own set on the house style step and is on the login page step
- WHEN they press "Skip, keep Nextcloud as it is"
- THEN the uploaded set MUST be among the own sets and MUST NOT be the active token set

### Requirement: The setup contract is for administrators only (REQ-TQFS-005)

thematiq MUST serve `GET /apps/thematiq/api/setup/status` and
`POST /apps/thematiq/api/setup/action/{actionId}` to administrators only, refused by Nextcloud's
middleware with 403 before the controller runs for anyone else, with CSRF on the POST. Status MUST
answer `{version, completed, skipped, configured, steps, current}`, each step `{done, detail}`, and
`current` the stored choices. The actions MUST be `apply` and `skip`; any other id MUST answer 404.
`apply` MUST take only `tokenSet`, `confirmRestricted`, `nextcloudThemeFollows`, `loginStyle`,
`hideSlogan` and `menu`, check each value with the rules of the settings page, the licence gate and
`POST /app-menu/defaults`, and refuse the whole request with 400 naming the key when one is unknown or
wrong, storing nothing. Every write MUST be in the theming audit trail with its actor. Under a
configuration source every write MUST be refused with 423, as the settings page is.

Board: TqSetupKlaar.

#### Scenario: A user who is not an administrator is refused
- GIVEN a user who is not an administrator
- WHEN they request `GET /apps/thematiq/api/setup/status`
- THEN the answer MUST be 403

#### Scenario: A wrong value stores nothing
@e2e exclude pure controller logic: PHPUnit tests/Unit/Controller/SetupControllerTest.php (testAWrongValueRefusesTheRequest)
- GIVEN an administrator
- WHEN they apply `{tokenSet: "zuiddrecht", menu: {columns: 9}}`
- THEN the answer MUST be 400 naming `menu.columns`
- AND the active token set MUST be unchanged

#### Scenario: An unknown action is not found
@e2e exclude pure controller logic: PHPUnit tests/Unit/Controller/SetupControllerTest.php (testAnUnknownActionIsNotFound)
- GIVEN an administrator
- WHEN they post to `/apps/thematiq/api/setup/action/seed`
- THEN the answer MUST be 404 and nothing MUST change

### Requirement: The welcome step says what thematiq does (REQ-TQFS-006)

The first step MUST say what thematiq does, list its three parts (Choose a house style, Nextcloud
follows, An apps menu made to measure), say that only administrators see the screen and that Later
brings it back, and offer "Skip, keep Nextcloud as it is", Later and Start, with the copy of its board in
English and Dutch.

Board: TqSetupWelkom.

#### Scenario: The welcome offers three ways on
- GIVEN the welcome screen is open
- THEN it MUST offer "Skip, keep Nextcloud as it is", Later and Start

### Requirement: The house style step chooses a token set and its licence confirmation (REQ-TQFS-007)

The house style step MUST offer the token sets as a radio group of cards with colour swatches and a
licence line, the stored set first, Nextcloud default ("No house style, only the apps menu") and "Upload
your own set", with a summary line of the chosen set and a link "All <n> sets in the gallery" that lists
every set in the step. Choosing a restricted set without a confirmation MUST show "This house style
belongs to <holder>", what the licence allows, the source package and version, the licence text and the
statement "We are this organisation, or we have its permission to use this house style." with a checkbox,
and Next MUST stay disabled until the box is ticked. Apply MUST then store the confirmation and apply the
set through the same service, licence gate, audit entry and saved version as the settings page. A
refusal (403 `restricted_set_not_confirmed`) MUST show on the apps menu step, with Back leading to the
house style step.

Boards: TqSetupHuisstijl, TqSetupHuisstijlLicentie.

#### Scenario: An open set is applied
- GIVEN the house style step is open
- WHEN the administrator chooses an open-licensed set and walks on to Apply
- THEN that set MUST be the active token set
- AND the audit log MUST hold a token set change by that administrator

#### Scenario: A restricted set needs the statement
- GIVEN the house style step is open and `amsterdam` is restricted and not confirmed
- WHEN the administrator chooses `amsterdam`
- THEN "This house style belongs to Gemeente Amsterdam", the source package and the licence text MUST show
- AND Next MUST be disabled
- WHEN they tick the statement and walk on to Apply
- THEN `amsterdam` MUST be confirmed by that administrator and be the active token set

#### Scenario: A confirmation refused at Apply
@e2e exclude a race between two administrators; proven by tests/vitest/setupWizard.spec.js (a 403 restricted_set_not_confirmed shows on the apps menu step) and tests/Unit/Service/SetupActionsTest.php (testTheHouseStyleGoesThroughTheLicenceGate)
- GIVEN the administrator ticked the statement for `amsterdam`
- AND the server refuses the set at Apply
- THEN the apps menu step MUST show the refusal and nothing MUST be applied

### Requirement: The Nextcloud theme step keeps Nextcloud's theme in line with the set (REQ-TQFS-008)

The Nextcloud theme step MUST have the switch "Keep the Nextcloud theme in line with the set", on by
default, and show what changes for the primary colour, the background colour, the logo with its dark
version, and the favicon, each with what Nextcloud has now, and that the current theme is kept as a
version in the log. On, Apply MUST apply the set to Nextcloud's theme through the same service as
`occ thematiq:theme:set --sync-core`, keep the old theme as a version, and store
`core_theming_follows_set` `1`. While it is `1`, every later change of the instance's set (the settings
page, `occ thematiq:theme:set`, a planned switch) MUST also apply the new set to Nextcloud's theme. Off,
Apply MUST leave Nextcloud's theme as it is and store `0`. With Nextcloud default chosen as house style
the step MUST be skipped.

Board: TqSetupTheming.

#### Scenario: The set's logo, colours and favicon reach Nextcloud
- GIVEN the administrator chose a set whose theming metadata has a primary colour, a logo and a favicon
- WHEN the switch stays on and they press Apply
- THEN Nextcloud's primary colour, logo and favicon MUST be the set's

#### Scenario: Off leaves Nextcloud's theme alone
- GIVEN the Nextcloud theme step is open
- WHEN the administrator turns the switch off and presses Apply
- THEN Nextcloud's theming values MUST be unchanged

#### Scenario: A later change of set follows
@e2e exclude a second set change on the shared instance repeats the first scenario through the settings page; proven by tests/Unit/Service/ActiveTokenSetServiceTest.php (testAFollowingThemeIsSyncedOnEverySwitch)
- GIVEN `core_theming_follows_set` is `1`
- WHEN an operator runs `occ thematiq:theme:set rijkshuisstijl`
- THEN Nextcloud's primary colour MUST be the Rijkshuisstijl's

### Requirement: The login page step sets the login page style (REQ-TQFS-009)

The login page step MUST offer "Login page style" with Follow the house style (the login page is themed,
`login` in `themed_contexts`) or Nextcloud default (Nextcloud's own background and layout, only the logo
changes), the checkbox "Hide the Nextcloud slogan" (`hide_slogan`), and a preview of the login card.

Board: TqSetupInlogpagina.

#### Scenario: The slogan is hidden on the login page
- GIVEN the login page step is open
- WHEN the administrator keeps Follow the house style, ticks "Hide the Nextcloud slogan" and Apply is pressed
- THEN an anonymous visitor of the login page MUST NOT see the slogan

#### Scenario: Nextcloud default login page
@e2e exclude a style rule of the themed contexts; proven by tests/Unit/Service/SetupActionsTest.php (testTheNextcloudLoginRemovesTheLoginContext)
- GIVEN the login page step is open
- WHEN the administrator chooses Nextcloud default and Apply is pressed
- THEN `themed_contexts` MUST NOT contain `login`

### Requirement: The apps menu step is one switch (REQ-TQFS-010)

The apps menu step MUST have one switch, "Use the thematiq apps menu", on by default, and MUST NOT offer
a choice between a Workplace and a Classic style. On MUST mean thematiq's workplace apps menu: Apply MUST
store the administrator's `menu.style` default workplace. Off MUST mean Nextcloud's own menu: Apply MUST
store the administrator's `menu.style` default classic and no other `menu.<key>` default. While the
switch is on the step MUST show the workplace menu's defaults, columns 3 to 5, rows above More apps 1 to
3, and the switches Show payoff, Show Create and Show external apps, ending in Apply, and Apply MUST store
them as the administrator's `menu.<key>` defaults, checked as `POST /app-menu/defaults` checks them. The
workplace apps menu shows only in the workplace top bar: when the switch is on and the chosen house
style does not draw the workplace top bar, Apply MUST also store `header_style` workplace. With the
switch off, `header_style` MUST be left as it is.

Board: TqSetupAppmenu, without its Style row (Ruben, 27 November 2026).

#### Scenario: The step offers no style choice
@e2e exclude the absence of a control on a step; proven by tests/vitest/setupWizard.spec.js (the apps menu step has one switch and no Style choice)
- GIVEN the apps menu step is open
- THEN it MUST show the switch "Use the thematiq apps menu"
- AND it MUST NOT offer Workplace or Classic

#### Scenario: The menu defaults reach everyone
- GIVEN the apps menu step is open
- WHEN the administrator keeps the switch on, sets 3 columns and presses Apply
- THEN the administrator's `menu.style` default MUST be workplace
- AND a user who is not an administrator MUST see the workplace apps menu with 3 columns

#### Scenario: Turning the menu on brings the workplace top bar
- GIVEN the administrator chose a house style that does not draw the workplace top bar
- WHEN they keep "Use the thematiq apps menu" on and press Apply
- THEN `header_style` MUST be workplace
- AND a user who is not an administrator MUST see the workplace top bar with the workplace apps menu

#### Scenario: The menu off keeps Nextcloud's own menu
@e2e exclude switching everyone to the classic style is proven against the shared instance by apps-menu-settings; the stored default by tests/Unit/Controller/SetupControllerTest.php (testTheMenuSwitchStoresTheStyle)
- GIVEN the apps menu step is open and the active `header_style` is `nextcloud`
- WHEN the administrator turns "Use the thematiq apps menu" off and presses Apply
- THEN the administrator's `menu.style` default MUST be classic
- AND `header_style` MUST still be `nextcloud`
- AND no column, row or show default MUST be stored

### Requirement: The done step sums up and offers the apps menu walkthrough (REQ-TQFS-011)

After Apply, the done step MUST read "Done, <set> is set" and "Applied.", list each applied part with a
check (house style, Nextcloud theme, login page, apps menu), point to the version card for setting up
again, and offer Close and, when the apps menu is on, "Start the apps menu walkthrough". Close MUST close
the wizard. "Start the apps menu walkthrough" MUST store the walkthrough's progress with an immediate
start for that administrator and reload the page, after which the apps menu MUST open and the tour MUST
start at its first step.

Board: TqSetupKlaar.

#### Scenario: Start the walkthrough
- GIVEN the done step with the apps menu on
- WHEN the administrator presses "Start the apps menu walkthrough"
- THEN the page MUST reload without the wizard
- AND the apps menu MUST open and the walkthrough MUST start at "Open the apps menu"

#### Scenario: Close ends setup
- GIVEN the done step is open
- WHEN the administrator presses Close
- THEN on the next page no wizard MUST open

### Requirement: The version card shows the setup and runs it again (REQ-TQFS-012)

thematiq's Theming settings page MUST show administrators the version card of its board: "About this
installation of Thematiq.", the installed version, the row "First-time setup" with its state and detail
("Completed by <user> on <date>, <time>" with the applied set and version, or the state of an instance
that skipped, was set up by an earlier version, or is not set up yet, in the wording of the board), and
the buttons "Set up again", "Apps menu walkthrough" and "Help us: suggest an idea". Set up again MUST open
the wizard at the welcome step, filled with the stored choices, and change nothing until Apply. Closing a
rerun with Later MUST leave setup done. Applying a rerun MUST store `setup.completed` again with the new
administrator and time. "Apps menu walkthrough" MUST open the apps menu and start the walkthrough at its
first step. "Help us: suggest an idea" MUST open nextcloud-vue's suggest-a-feature modal. Under a
configuration source Set up again MUST be disabled with the note who manages the house style.

Board: TqSetupOpnieuw.

#### Scenario: Set up again after finishing
- GIVEN setup was completed with `zuiddrecht`
- WHEN the administrator presses "Set up again" on the version card
- THEN the wizard MUST open at the welcome step, and the house style step MUST have `zuiddrecht` chosen
- WHEN they press Later
- THEN on the next page no wizard MUST open

#### Scenario: The card names who completed setup
- GIVEN administrator `admin` completed setup
- WHEN the Theming settings page opens
- THEN the version card MUST read "Completed by admin on" followed by the date and time

#### Scenario: Theme as code disables the button
@e2e exclude needs config.php; proven by tests/vitest/adminVersionCard.spec.js (the button is disabled with the source note under a configuration source)
- GIVEN `thematiq.config_source` is set
- WHEN an administrator opens the Theming settings page
- THEN "Set up again" MUST be disabled with the note who manages the house style

### Requirement: The settings page switches Nextcloud's theme in line with the set (REQ-TQFS-013)

thematiq's Theming settings page MUST show administrators, in the Active token set card, the switch "Keep
the Nextcloud theme in line with the set" with the hint "Also on the next change of set. Off: thematiq
leaves the Nextcloud theme alone.", showing the stored `core_theming_follows_set` (off when absent). It
MUST save at once through `POST /apps/thematiq/settings/core-theming-follows` with `{follows}`, served to
administrators only, with an entry in the theming audit trail naming the actor. Turning it on MUST store
`1` and apply the active set to Nextcloud's theme through the same service as
`occ thematiq:theme:set --sync-core`, keeping the old theme as a version; with Nextcloud default as the
active set it MUST only store `1`. Turning it off MUST store `0` and leave Nextcloud's theme as it is.
While it is on, choosing another set on the settings page MUST apply that set to Nextcloud's theme
without the theming sync dialog and say so in the card's status line; while it is off the dialog MUST
ask as before. The wizard's Nextcloud theme step (REQ-TQFS-008) and this switch MUST read and write the
same value. Under a configuration source the switch MUST be disabled with the note who manages the
house style, and the route MUST answer 423.

Board: TqTheming (Active token set card), not drawn yet (Ruben, 27 November 2026).

#### Scenario: Turning the switch on brings the set to Nextcloud
- GIVEN the active set has a primary colour, a logo and a favicon in its theming metadata
- AND `core_theming_follows_set` is `0`
- WHEN an administrator turns on "Keep the Nextcloud theme in line with the set" on the Theming settings page
- THEN `core_theming_follows_set` MUST be `1`
- AND Nextcloud's primary colour, logo and favicon MUST be the set's
- AND the audit log MUST hold the change by that administrator

#### Scenario: A change of set follows without the dialog
- GIVEN the switch is on
- WHEN the administrator chooses another set with theming metadata on the settings page
- THEN no theming sync dialog MUST open
- AND Nextcloud's primary colour MUST be the new set's

#### Scenario: Off leaves Nextcloud's theme alone
- GIVEN the switch is on
- WHEN the administrator turns it off and then chooses another set with theming metadata
- THEN `core_theming_follows_set` MUST be `0`
- AND the theming sync dialog MUST ask before Nextcloud's theme changes

#### Scenario: The wizard's choice shows on the settings page
- GIVEN the administrator kept "Keep the Nextcloud theme in line with the set" on in the wizard and pressed Apply
- WHEN they open the Theming settings page
- THEN the switch MUST be on

#### Scenario: A user who is not an administrator is refused
@e2e exclude pure controller authorisation; proven by tests/Unit/Controller/SettingsControllerTest.php (testCoreThemingFollowsIsAdminOnly) and the route-auth gate
- GIVEN a user who is not an administrator
- WHEN they post to `/apps/thematiq/settings/core-theming-follows`
- THEN the answer MUST be 403 and `core_theming_follows_set` MUST be unchanged

#### Scenario: Theme as code disables the switch
@e2e exclude needs config.php; proven by tests/Unit/Controller/SettingsControllerTest.php (testCoreThemingFollowsIsLockedUnderASource) and the admin page vitest
- GIVEN `thematiq.config_source` is set
- WHEN an administrator opens the Theming settings page
- THEN the switch MUST be disabled with the note who manages the house style
- AND a post to the route MUST answer 423
