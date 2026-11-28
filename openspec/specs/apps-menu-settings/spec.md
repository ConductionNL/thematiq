# apps-menu-settings Specification

## Purpose
Menu settings for the workplace apps menu: defaults an administrator sets for everyone, the keys a person sets for themselves, the external apps, and the dialog that edits them, each value checked on the server.

## Requirements

### Requirement: Menu settings are admin defaults with per-user overrides
The apps menu MUST be drawn from these settings, with these shipped values: `style` workplace,
`columns` 4, `rows` 6, `rowsAboveFold` 2, `showCreateButton` true, `showCreateRow` true, `showExternalApps` true, `shape`
round, `tiles` false, `payoff` true, `iconSize` large, `iconColour` brand, and the lists `order`,
`pinned`, `hidden` and `externalApps`, empty. An administrator sets any key for everyone, kept in app
values `thematiq` / `menu.<key>`. A person sets only the personal keys `order`, `pinned`, `hidden`,
`externalApps`, `showCreateButton` and `showCreateRow`, kept in user values with the same names. A person's key MUST win over the
administrator's, and the administrator's over the shipped value, except `externalApps`: the menu
MUST list the administrator's external apps, then the person's own. The order a person saved before
these settings (user value `app_menu_order`) MUST still be read while `menu.order` is absent. The
effective settings MUST be in the `thematiq/app-menu` initial state as `settings`, `order` and `pins`.

#### Scenario: A person's pins win, the theming is the administrator's
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testThePersonsOwnKeysWinAndTheirAppsFollowTheAdministrators)
- GIVEN an administrator set 3 columns, square items and Files pinned for everyone
- AND Anna pinned Deck for herself
- THEN Anna's menu MUST have 3 columns, square items and Deck pinned
- AND Bob's menu MUST have Files pinned

#### Scenario: A person turns the Create row back on
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testAPersonsCreateSwitchesWinOverTheAdministrators)
- GIVEN an administrator switched the Create row off for everyone
- AND Anna switched it on for herself
- THEN Anna's apps menu MUST show the Create row
- AND Bob's apps menu MUST NOT show it

### Requirement: Menu settings are checked before they are stored
`POST /apps/thematiq/app-menu/settings` with `{settings}` MUST store only the signed-in person's personal
keys and take no user id; a theming or configuration key MUST be refused with 400 naming it.
`POST /apps/thematiq/app-menu/defaults` MUST be for administrators only. Before anything is stored, every
value MUST be checked: `style` workplace or classic, `shape` round or square, `iconSize` small or large,
`iconColour` brand or neutral, `columns` 2 to 5, `rows` and `rowsAboveFold` 1 to 12, the switches true
or false, `order`, `pinned` and `hidden` lists of keys of entries the person can see, at most 5 pinned.
An unknown key or a value outside its rule MUST refuse the whole save with 400 naming the setting, and
nothing MUST be stored. A key sent as null MUST follow the default again.

#### Scenario: A person may not change the theming
@e2e exclude Pure controller logic: PHPUnit tests/Unit/Controller/AppMenuSettingsControllerTest.php (testAPersonSavesOnlyTheirOwnKeys)
- GIVEN a person who is not an administrator
- WHEN they send `{columns: 3}`
- THEN the answer MUST be 400 naming `columns` and nothing MUST change

### Requirement: Reset to default
`DELETE /apps/thematiq/app-menu/settings` MUST remove the signed-in person's personal keys, including
the order kept before these settings, so the administrator's values apply again.
`DELETE /apps/thematiq/app-menu/defaults` MUST be for administrators only and MUST remove every
`menu.<key>` app value, so the shipped values apply again. The dialog's Reset to default MUST ask for a
confirmation first and then call the endpoint of the chosen scope.

#### Scenario: A person resets their menu items
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testResetClearsTheScope)
- GIVEN Anna pinned nothing for herself while the administrator pinned Deck
- WHEN she resets
- THEN Deck MUST be pinned in her menu again

### Requirement: The external apps of the admin page move into the menu settings
The external apps an administrator gave an address on the admin page (app values `external_apps`,
`openproject_url`, `openproject_modules`) MUST move once into the administrator's
`menu.externalApps`, after any app already there, each with its title, payoff, address and icon and
with the same menu key (`external-openproject-work_packages`, `external-wiki`), so a person's order,
pins and hidden apps still name it. OpenProject's work packages, Gantt charts, boards and meetings and
XWiki MUST take the icons the AppMenu board draws for them. The old values MUST be removed and a marker
(`menu.externalAppsMigrated`) set, so the move runs once and a later reset of the defaults does not
bring them back. The move MUST run on the first page that draws the menu and when Menu settings opens, so it
needs no upgrade. The admin page MUST no longer edit external apps: its block points to
Menu settings, and its endpoints `/settings/external-apps` and `/settings/openproject` are gone.

#### Scenario: The demo's OpenProject and XWiki move
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuLegacyExternalAppsTest.php (testTheAdminPageAppsMoveOnceAndKeepTheirKeys)
- GIVEN OpenProject at `http://localhost:8077` and XWiki at `http://localhost:8078` on the admin page
- WHEN the menu is drawn
- THEN Menu settings MUST list Work packages, Gantt charts, Boards, Meetings and XWiki with edit and delete
- AND the admin page values MUST be gone

### Requirement: External apps are checked on the server
An external app MUST be `{id, title, payoff, url, icon, visible, order}`: an id of lower case letters,
digits, `_` and `-`, a title of 1 to 60 characters without markup, a payoff of at most 80, an `https`
address with a host and without user info (plain `http` only on this machine's loopback address,
localhost, 127.x or ::1, where a development or demo service runs without TLS), and an icon that is
`{library: <name>}` or `{upload: <id>}` of an uploaded icon. The dialog offers the 16 library icons;
the icons the AppMenu boards draw for the moved apps, and the admin page's own line icons, are
accepted too. One
app that is not allowed MUST refuse the save. The menu MUST draw a visible external app as a link with
`target="_blank"`, `rel="noopener noreferrer"` and the hidden text "(opens in a new tab)". An uploaded
icon MUST be a PNG or an SVG without script, event handlers or foreign objects, at most 100 kB, sent to
`POST /apps/thematiq/app-menu/icons` and served at `GET /apps/thematiq/app-menu/icons/{id}` to signed-in
people with `Content-Security-Policy: default-src 'none'` and `X-Content-Type-Options: nosniff`.

#### Scenario: An http address is refused
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testAnExternalAppIsChecked)
- GIVEN an external app with the address `http://intranet.example.org`
- WHEN it is saved
- THEN the answer MUST be 400 naming `externalApps`

#### Scenario: An SVG with script is refused
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuIconsTest.php (testWhatMayNotRunOrIsTooLargeIsRefused)
- GIVEN an SVG icon with a script element
- WHEN it is uploaded
- THEN it MUST NOT be stored

### Requirement: The menu is drawn from the settings
The open menu MUST show `columns` entries per row and `columns` times `rowsAboveFold` entries before the
fold toggle, which reads "More" and, unfolded, "Fewer"; past `rows` rows the unfolded menu MUST
scroll. `shape` square MUST give the icons a rounded square, `tiles` MUST give every entry a tile,
`iconSize` small MUST draw 32 px icons and large 40 px. With `iconColour` brand the organisation's work
apps, the apps of the menu's first section, MUST take the brand's light wash and every other app,
external apps included, the grey chip; `neutral` MUST draw every app grey. An entry MUST be 68 px high
(12 px by 6 px padding round a 44 px content box), as the AppMenuAlles board measures. With `payoff` off no entry shows its second line; hidden entries MUST NOT show; with
`showExternalApps` off the external apps MUST NOT show; with `showCreateRow` off the Create row MUST
NOT show; with `showCreateRow` and `showCreateButton` both off the templates API MUST NOT be asked. A value the menu cannot use MUST fall back to its
shipped value or the nearest allowed number. The footer MUST show a gear button "Menu settings" with
`aria-haspopup="dialog"` in place of the Manage apps link. The Zuiddrecht set MUST give the Nextcloud
apps their payoff too.

#### Scenario: Four columns of two rows
@e2e exclude the demo stack is being filmed and cannot be deployed to; proven by tests/vitest/appMenuSettings.spec.js (shows four columns of two rows by default)
- GIVEN the shipped settings
- WHEN the apps menu opens
- THEN 8 entries MUST show before More

### Requirement: The classic style leaves Nextcloud's apps menu alone
With the effective `style` classic, the workplace header MUST NOT load css/app-menu.css or arrange the
menu, so a person gets Nextcloud's apps menu as it ships. A person who is not an administrator MUST get
no menu state and no script. An administrator MUST get one row with the gear at the end of Nextcloud's
menu, so the style can be set back; only the dialog's script and stylesheet load for that.

#### Scenario: A person on the classic menu
@e2e exclude no demo deploy during filming; proven by tests/Unit/Service/AppMenuServiceTest.php (testTheClassicStyleLeavesTheMenuAlone)
- GIVEN the administrator chose the classic menu for everyone
- WHEN a person who is not an administrator opens a page
- THEN the apps menu script and stylesheet MUST NOT load

### Requirement: The Menu settings dialog
The gear MUST open a modal dialog titled "Menu settings", in its own file, with a close button, a
`tablist` of Menu items, Configuration and, for an administrator choosing For everyone, Theming; the
tabs MUST use roving tabindex with the arrow keys, Home and End, and every tab MUST control its
`tabpanel`. An administrator MUST get a `radiogroup` For everyone / Only for me with its help text; a
person gets Only for me alone. Menu items MUST list the apps in the scope's order, each with a drag
handle, Move up and Move down buttons named after the app, Alt with Arrow up or down, a pin toggle
(`aria-pressed`, disabled at 5 pins for an unpinned app) and a show switch (`role="switch"`), and the
external apps with up, down, show, edit and delete, and Add external app. The Add or Edit external app
dialog MUST have labelled fields for Title, Payoff and URL with help text, mark a wrong field with
`aria-invalid` and say why, offer the icon library as a keyboard `radiogroup` and an upload, show a
preview, and say that an external app always opens in a new tab. Configuration MUST have the menu style,
labelled steppers for columns (2 to 5), rows and rows above More with help text, a preview of the
grid, the section switches "Create button in the top bar" (`showCreateButton`), "Create row in the apps
menu" (`showCreateRow`) and the external apps switch, and for an administrator the Manage apps link with
"Admins only"; for a person in Only for me its controls MUST be disabled with the note that the
administrator sets them, except the two create switches, which stay editable and are saved as the
person's own keys.
Theming MUST have the shape, the tiles and payoff switches, the icon size as a `radiogroup` beside its
name, the icon colour as two inline radios with a swatch, and a preview of four items beside them with
a sentence built from the settings ("Round, large icons in the brand colour, with payoff and without
tiles."). The scope help MUST sit beside the For everyone / Only for me switch. The footer MUST have Reset to default, Cancel and Save. Save MUST
send the person's changed personal keys, or for For everyone every setting; Reset MUST ask first, then
clear the person's keys or restore the shipped defaults. After a save or reset the page MUST reload;
on a failure the dialog MUST say so in an alert. Closing MUST give focus back to the gear.

#### Scenario: A person pins an app in the dialog
@e2e exclude the demo stack is being filmed; proven by tests/vitest/menuSettingsDialog.spec.js (orders, pins and hides apps, and a person saves only what they changed)
- GIVEN a person opens Menu settings
- WHEN they pin Calendar, hide Notes and press Save
- THEN `POST /apps/thematiq/app-menu/settings` MUST carry `pinned`, `hidden` and `order` only

#### Scenario: An external app needs an https address
@e2e exclude the demo stack is being filmed; proven by tests/vitest/menuSettingsDialog.spec.js (adds an external app after checking its title and https address)
- GIVEN the Add external app dialog with the address `http://intranet.example.org`
- WHEN Add is pressed
- THEN the URL field MUST be marked invalid and the app MUST NOT be added

### Requirement: Menu settings offers to replay the tour (REQ-TQWT-008)

The footer of the Menu settings dialog MUST have a "Replay walkthrough" button between Reset to default
and Cancel, for every person and for an administrator in either scope. It MUST close the dialog without
saving, asking first when there are unsaved changes, then open the apps menu and start the apps menu
tour at its first step, running every step. With the classic menu style the button MUST NOT be drawn,
because there is no workplace menu to tour.

Boards: TqMenuRondleidingOpnieuw, AppMenuInstellingen.

#### Scenario: Replay from Menu settings
- GIVEN a person who finished the tour opens Menu settings
- WHEN they press "Replay walkthrough"
- THEN the dialog MUST close, the apps menu MUST open and the tour MUST start at the open step

#### Scenario: Unsaved changes are not lost without asking
@e2e exclude a dialog detail; proven by tests/vitest/menuSettingsDialog.spec.js (replay asks before dropping unsaved changes)
- GIVEN Menu settings with an app pinned and not yet saved
- WHEN "Replay walkthrough" is pressed
- THEN the dialog MUST ask whether to drop the changes before it closes

### Requirement: The Create button and the Create row switch on and off independently (REQ-TQQT-001)
The menu settings MUST have two switches for the quick actions, both shipped on: `showCreateButton`,
the Quick add button with the plus before the bell in the top bar, and `showCreateRow`, the Create row
at the bottom of the apps menu, above the footer. Neither MUST depend on the other, so a person can
have both, either or neither. An administrator sets the default for everyone; a person MAY set either
switch for themselves, and their value MUST win. `POST /apps/thematiq/app-menu/settings` MUST accept
both as personal keys, true or false, and null MUST follow the administrator's value again. The Create
button, when on, MUST be on every page with the workplace top bar, the same on every app.

Boards: AppMenuSnelacties, AppMenuConfiguratie, MlInbox, ClWeek, TbBoard.

#### Scenario: Both on
- GIVEN `showCreateButton` and `showCreateRow` are on for Anna
- WHEN Anna opens Files and then the apps menu
- THEN "Quick add" MUST stand before the bell
- AND the apps menu MUST show the Create row above its footer

#### Scenario: Only the Create button
- GIVEN `showCreateButton` is on and `showCreateRow` is off for Anna
- WHEN Anna opens Files and then the apps menu
- THEN "Quick add" MUST stand before the bell and list the Create actions
- AND the apps menu MUST NOT show the Create row

#### Scenario: Only the Create row
- GIVEN `showCreateButton` is off and `showCreateRow` is on for Anna
- WHEN Anna opens Files and then the apps menu
- THEN no Quick add button MUST be drawn
- AND the apps menu MUST show the Create row above its footer

#### Scenario: Neither
- GIVEN `showCreateButton` and `showCreateRow` are off for Anna
- WHEN Anna opens Files and then the apps menu
- THEN no Quick add button MUST be drawn and the apps menu MUST NOT show the Create row
- AND the templates API MUST NOT be asked

#### Scenario: A person's switch wins over the administrator's default
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testAPersonsCreateSwitchesWinOverTheAdministrators)
- GIVEN the administrator switched the Create button off for everyone
- AND Anna switched it on for herself in Menu settings
- THEN Anna MUST get the Quick add button on every page and Bob MUST NOT

### Requirement: The former create switch moves into the two new switches (REQ-TQQT-002)
`showCreateActions` MUST no longer be a menu setting. An administrator's stored app value
`menu.showCreateActions` MUST move once into `menu.showCreateButton` and `menu.showCreateRow`, each only
where that key is not stored yet, and MUST then be removed. The move MUST run on the first page that
draws the menu and when Menu settings opens, so it needs no upgrade step, as the external apps move
does. No user value of `showCreateActions` exists, because it was never a personal key. A save that
still sends `showCreateActions` to `POST /apps/thematiq/app-menu/defaults`, as an older setup wizard
does, MUST be read as the same value for both new keys and MUST NOT store the old key. The first-time
setup MUST send the two new keys.

#### Scenario: An administrator who switched create actions off keeps both off
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTheFormerCreateSwitchMovesIntoBothNewSwitches)
- GIVEN the app value `menu.showCreateActions` is false
- WHEN the menu is drawn
- THEN `menu.showCreateButton` and `menu.showCreateRow` MUST be false
- AND `menu.showCreateActions` MUST be gone

#### Scenario: The old key on a save sets both
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTheFormerCreateSwitchOnASaveSetsBoth)
- GIVEN an administrator
- WHEN `{showCreateActions: false}` is sent to `POST /apps/thematiq/app-menu/defaults`
- THEN `menu.showCreateButton` and `menu.showCreateRow` MUST be false and `menu.showCreateActions` MUST NOT be stored

### Requirement: The administrator sets the defaults on thematiq's admin page (REQ-TQQT-003)
The Menu card of the Layout and options panel on thematiq's admin page MUST show the two switches,
"Create button in the top bar" and "Create row in the apps menu", each with its help text and
`role="switch"`, checked from the administrator's values. Toggling one MUST save at once through
`POST /apps/thematiq/app-menu/defaults` with only that key, say "Saved" in the page's polite live
region, and on a failure say why in an alert and set the switch back. A note under the switches MUST
say that people can change both for themselves in Menu settings. With the classic menu style both
switches MUST be disabled with the note "Only with the workplace menu." The changes MUST be listed among
the changes in this session, as the panel's other options are.

Boards: TqLayout.

#### Scenario: The administrator switches the Create row off for everyone
- GIVEN an administrator on Layout and options with both create switches on
- WHEN they switch "Create row in the apps menu" off
- THEN the app value `menu.showCreateRow` MUST be false
- AND a person without their own value MUST get no Create row in the apps menu

#### Scenario: The classic menu disables the switches
@e2e exclude a disabled state of two switches; proven by tests/vitest/adminLayoutCreateSwitches.spec.js (disables the create switches with the classic menu)
- GIVEN the administrator chose the classic menu for everyone
- WHEN they open Layout and options
- THEN both create switches MUST be disabled with "Only with the workplace menu."

### Requirement: The shape applies to every icon of the apps menu and the top bar (REQ-TQMS-001)
With the workplace menu style, the effective `shape` MUST apply to every icon background of the apps
menu and every icon button of the top bar, on every page. With `shape` square each of them MUST take a
rounded square with the radius of the menu's app icons, `--border-radius-element`: in the apps menu
the app entries, the Create row's icons and its More, and the external apps; in the top bar the
Create button, the bell, the contacts menu, the person's menu, every other icon-only button or
header menu trigger at the end of the bar, the apps menu button and the pins; and the marks of the
Create button's menu and the chip of a pin being dragged. A top bar button MUST draw its hover,
focus-visible, active and open background in that shape, and the bell and the contacts menu MUST
draw that background in the hover colour, as the Create button does, where Nextcloud draws none. With
`shape` round each MUST keep the radius
it has without this requirement. The search field and every avatar MUST keep their own shape. The
script of the apps menu MUST write the checked shape on the page root as `data-thematiq-shape`, so the
rules of the bar can read it; with the classic menu style it MUST NOT write it.

#### Scenario: Square buttons in the bar
- GIVEN the administrator set the shape to square for everyone, on the Zuiddrecht set
- WHEN a person rests the pointer on the bell
- THEN its hover background MUST have the radius of `--border-radius-element`
- AND the contacts menu's and the Create button's hover backgrounds MUST have the same radius

#### Scenario: The contacts menu shows its hover
- GIVEN the workplace menu style, in either shape
- WHEN a person rests the pointer on the contacts menu
- THEN it MUST show the hover background of the bar's other icon buttons

#### Scenario: Square icons everywhere in the menu
- GIVEN the administrator set the shape to square for everyone, on the Zuiddrecht set
- WHEN a person opens the apps menu
- THEN every app icon, every icon of the Create row, More included, and every external app's icon MUST
  have the radius of `--border-radius-element`

#### Scenario: Round again
- GIVEN the administrator set the shape back to round
- WHEN a person rests the pointer on the bell and on the Create button
- THEN their hover backgrounds MUST be round, as before this requirement

#### Scenario: The page root carries the shape
@e2e exclude Unit: vitest tests/vitest/appMenuSettings.spec.js writes the checked shape on the root, round for a value that is not allowed
- GIVEN effective settings with `shape` set to a value that is not allowed
- WHEN the apps menu script starts
- THEN the page root MUST carry `data-thematiq-shape="round"`

#### Scenario: The classic style leaves the bar alone
@e2e exclude Unit: vitest tests/vitest/appMenuSettings.spec.js starts the script with the classic style
- GIVEN the classic menu style
- WHEN the apps menu script starts
- THEN the page root MUST NOT carry `data-thematiq-shape`

### Requirement: The contacts menu in the top bar switches off for a person (REQ-TQCM-001)
The menu settings MUST have the switch `showContactsMenu`, shipped true, for Nextcloud's contacts menu
in the top bar: the workplace top bar and Nextcloud's own header. It is a personal key, like `showCreateButton`: an administrator sets the
default for everyone in app value `thematiq` / `menu.showContactsMenu`, a person MAY set it for
themselves in user value `menu.showContactsMenu`, and the person's value MUST win.
`POST /apps/thematiq/app-menu/settings` MUST accept it, true or false, and null MUST follow the
administrator's value again. With the effective value off, the contacts menu button MUST NOT be in the
top bar on any page: it MUST NOT be displayed, MUST NOT be focusable and MUST NOT be in the
accessibility tree, and it MUST NOT show first and disappear after. The Contacts app MUST stay
reachable. It MUST work with the workplace and the classic menu style alike. In Menu settings, tab
Configuration, the switch "Contacts button in the top bar" (`role="switch"`, with its help text) MUST
follow the create switches and stay editable in Only for me, saved as the person's own key.

Boards: AppMenuConfiguratie, AppMenu.

#### Scenario: A person switches the contacts menu off
- GIVEN `showContactsMenu` is off for Anna
- WHEN Anna opens Files
- THEN the top bar MUST have no contacts menu button
- AND pressing Tab from the bell MUST move focus to the next control after it, not to a contacts menu
- AND the Contacts app MUST still open from the apps menu

#### Scenario: On by default
- GIVEN no `showContactsMenu` is stored for anyone
- WHEN Anna opens Files
- THEN the contacts menu button MUST stand after the bell in the top bar

#### Scenario: A person's switch wins over the administrator's default
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testAPersonsContactsMenuSwitchWinsOverTheAdministrators)
- GIVEN the administrator switched the contacts menu off for everyone
- AND Anna switched it on for herself in Menu settings
- THEN Anna's top bar MUST have the contacts menu and Bob's MUST NOT

#### Scenario: The stylesheet comes only with the switch off
@e2e exclude asset loading of one page request; proven by tests/Unit/Service/AppMenuServiceTest.php (testTheContactsMenuStylesheetLoadsOnlyWhenSwitchedOff)
- GIVEN the effective `showContactsMenu` is off
- WHEN a page with the workplace top bar is drawn
- THEN `css/top-bar-no-contacts.css` MUST be added, and with it on MUST NOT

#### Scenario: The classic header loses the button too
@e2e exclude switching the shared instance to the classic menu for everyone disturbs parallel runs; proven by tests/Unit/Service/AppMenuServiceTest.php (testTheContactsMenuStylesheetLoadsWithTheClassicStyle)
- GIVEN the classic menu style and `showContactsMenu` off for everyone
- WHEN a person who is not an administrator opens Files
- THEN Nextcloud's header MUST have no contacts menu button
- AND the apps menu script MUST still not load

#### Scenario: A person switches it in Menu settings
@e2e exclude a dialog save; proven by tests/vitest/menuSettingsDialog.spec.js (the contacts menu switch is editable in Only for me and saved as a personal key)
- GIVEN a person in Menu settings, Only for me, tab Configuration
- WHEN they switch "Contacts button in the top bar" off and press Save
- THEN `POST /apps/thematiq/app-menu/settings` MUST carry `showContactsMenu: false`

### Requirement: The administrator sets the contacts menu default on thematiq's admin page (REQ-TQCM-002)
The Menu card of the Layout and options panel on thematiq's admin page MUST show "Contacts button in
the top bar" after the create switches, with its help text and `role="switch"`, checked from the
administrator's value. Toggling it MUST save at once through `POST /apps/thematiq/app-menu/defaults`
with only that key, say "Saved" in the page's polite live region, and on a failure say why in an alert
and set the switch back. The note under the switches MUST say that people can change these for
themselves in Menu settings. With the classic menu style the switch MUST stay enabled, because it
works there too. The change MUST be listed among the changes in this session.

Boards: TqLayout.

#### Scenario: The administrator switches the contacts menu off for everyone
- GIVEN an administrator on Layout and options with the contacts menu switch on
- WHEN they switch "Contacts button in the top bar" off
- THEN the app value `menu.showContactsMenu` MUST be false
- AND a person without their own value MUST get no contacts menu button in the top bar

#### Scenario: The switch stays enabled with the classic menu
@e2e exclude an enabled state of one switch; proven by tests/vitest/adminLayoutCreateSwitches.spec.js (keeps the contacts button switch enabled with the classic menu)
- GIVEN the administrator chose the classic menu for everyone
- WHEN they open Layout and options
- THEN the create switches MUST be disabled and the contacts button switch MUST NOT

### Requirement: Show titles is a Theming choice of the administrator (REQ-TQMT-001)
The menu settings MUST have the switch `titles`, shipped true, checked as true or false like the other
switches, with null following the default again. It is a Theming key, like `shape`, `tiles`, `payoff`,
`iconSize` and `iconColour`: an administrator sets it for everyone in app value `thematiq` /
`menu.titles` through `POST /apps/thematiq/app-menu/defaults`, and it MUST NOT be a personal key, so
`POST /apps/thematiq/app-menu/settings` MUST refuse it with 400 naming `titles`. The effective value
MUST be in the `thematiq/app-menu` initial state under `settings`. `DELETE /apps/thematiq/app-menu/defaults`
MUST remove it with the other `menu.<key>` values. With the classic menu style it MUST have no effect.

Boards: AppMenuVormgeving.

#### Scenario: Titles are on unless the administrator switches them off
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTitlesShipOnAndFollowTheAdministrator)
- GIVEN no `menu.titles` is stored
- THEN the effective `titles` MUST be true
- AND after an administrator stores `{titles: false}` for everyone it MUST be false for every person

#### Scenario: A person may not switch titles off for themselves
@e2e exclude Pure controller logic: PHPUnit tests/Unit/Controller/AppMenuSettingsControllerTest.php (testAPersonMayNotSetTitles)
- GIVEN a person who is not an administrator
- WHEN they send `{titles: false}` to `POST /apps/thematiq/app-menu/settings`
- THEN the answer MUST be 400 naming `titles` and nothing MUST change

#### Scenario: A value that is not a switch is refused
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTitlesIsASwitch)
- GIVEN an administrator
- WHEN they send `{titles: "no"}` to `POST /apps/thematiq/app-menu/defaults`
- THEN the answer MUST be 400 naming `titles` and nothing MUST be stored

### Requirement: With titles off the apps menu shows icons only (REQ-TQMT-002)
With the effective `titles` off, every app entry of the workplace apps menu MUST show its icon without
its visible name, in every section: the Nextcloud apps, the Create row's create chips and the external
apps. The pinned apps in the top bar follow `topBarTitles` instead (REQ-TQMT-006). Headings ("Apps", "Create", the external apps heading)
and the fold toggles (More, Fewer, the Create row's More) MUST keep their text. With titles off no
payoff MUST be drawn, whatever `payoff` holds. Each icon-only entry MUST keep the app's name as its
accessible name (`aria-label`); an external app's MUST end with "(opens in a new tab)". The entry MUST
keep its drag, Alt with an arrow key and Alt+P behaviour, and the live region MUST still name the app.
With titles on the menu MUST be drawn as before this change.

Boards: AppMenuZonderTitels, AppMenuAlles.

#### Scenario: The administrator switches titles off
- GIVEN the administrator stored `{titles: false}` for everyone
- WHEN a person opens the apps menu
- THEN no Nextcloud app, create chip or external app MUST show its name or payoff as text
- AND the Files entry MUST have the accessible name "Files"
- AND the headings "Apps" and "Create" and the More toggle MUST still show their text

#### Scenario: An icon-only external app says it opens in a new tab
@e2e exclude depends on an external app set for everyone on the shared instance; proven by tests/vitest/appMenuTitles.spec.js (an icon-only external app keeps its title and the new tab note as its name)
- GIVEN titles off and the external app XWiki
- WHEN the apps menu opens
- THEN the XWiki link MUST have the accessible name "XWiki (opens in a new tab)"

#### Scenario: Pinning still names the app
@e2e exclude the same key handler as with titles on; proven by tests/vitest/appMenuTitles.spec.js (Alt+P on an icon-only entry announces the app's name)
- GIVEN titles off and focus on Mail in the open apps menu
- WHEN Alt+P is pressed
- THEN the live region MUST say "Mail pinned to the top bar."

### Requirement: An icon-only entry shows its name as a tooltip on hover and focus (REQ-TQMT-003)
With titles off, an app entry of the apps menu MUST show a tooltip (`role="tooltip"`) with its name
when the pointer rests on it and at once when it receives keyboard focus. With `topBarTitles` off, a
pinned app in the top bar MUST show the same tooltip, under the pin. The tooltip MUST NOT be referenced by
`aria-describedby`, because it repeats the accessible name. It MUST stay while the pointer is on the
entry or on the tooltip and while the entry has focus, and MUST hide when both leave, when a drag
starts and when the menu closes. Escape MUST hide a showing tooltip without moving focus and without
closing the menu; Escape with no tooltip showing MUST act as before. The tooltip MUST NOT cover the
focused entry or its focus ring, and its text MUST have a contrast of at least 4.5:1 in light and dark
mode. An icon-only entry or pin MUST keep its visible focus ring (WCAG 2.2 AA, 1.4.13, 2.4.7, 2.4.11).

Boards: AppMenuZonderTitels, AppMenuVastzetten.

#### Scenario: Keyboard focus shows the name
- GIVEN titles off and the open apps menu
- WHEN the person moves focus to Calendar with the keyboard
- THEN a tooltip reading "Calendar" MUST be visible
- AND Calendar MUST show a visible focus ring

#### Scenario: Escape dismisses the tooltip and keeps the menu open
- GIVEN titles off and a tooltip showing on the focused Calendar entry
- WHEN Escape is pressed
- THEN the tooltip MUST be hidden
- AND the apps menu MUST still be open with focus on Calendar

#### Scenario: A pin shows its name on focus
- GIVEN `topBarTitles` off and Deck pinned
- WHEN the person moves focus to the Deck pin with the keyboard
- THEN a tooltip reading "Deck" MUST be visible under the pin
- AND the pin MUST show a visible focus ring

#### Scenario: The tooltip can be hovered
@e2e exclude a pointer path from the entry onto its tooltip is timing-bound in a browser run; proven by tests/vitest/appMenuTitles.spec.js (the tooltip stays while the pointer moves onto it)
- GIVEN titles off and a tooltip showing on hover over Deck
- WHEN the pointer moves from Deck onto the tooltip
- THEN the tooltip MUST stay visible

### Requirement: Menu settings and the setup wizard offer Show titles (REQ-TQMT-004)
The Theming tab of Menu settings MUST have the switch "Show titles" (`role="switch"`, with its help
text) after "Show items as tiles", then "Show titles in the top bar" (REQ-TQMT-005), then "Show
payoff". While Show titles is off, Show payoff MUST be disabled
with the note "Only with Show titles." and keep its value, and Save MUST store that value unchanged.
The preview MUST draw its items without names and without payoff while titles is off, and its sentence
MUST say "without titles" in place of the payoff part ("Round, large icons in the brand colour, without
titles and without tiles."). Reset to default for everyone MUST bring titles back on. The apps menu step
of the setup wizard offers Show payoff, so it MUST also offer "Show titles" before it, with Show payoff
disabled while titles is off, and Apply MUST store `menu.titles` as the administrator's default.

Boards: AppMenuVormgeving, TqSetupAppmenu.

#### Scenario: The administrator switches titles off in Menu settings
- GIVEN an administrator in Menu settings, For everyone, tab Theming
- WHEN they switch "Show titles" off
- THEN "Show payoff" MUST be disabled with "Only with Show titles."
- AND the preview MUST show its four items without names
- AND after Save the app value `menu.titles` MUST be false and `menu.payoff` MUST keep its value

#### Scenario: Switching titles back on brings the payoff back
@e2e exclude a dialog state; proven by tests/vitest/menuSettingsDialog.spec.js (payoff keeps its value while titles is off)
- GIVEN titles off and payoff on for everyone
- WHEN the administrator switches "Show titles" on and saves
- THEN the apps menu MUST show names and payoffs again

#### Scenario: The setup wizard stores titles
@e2e exclude the wizard's apps menu step is not built yet and is built with this switch; proven by tests/vitest/setupWizard.spec.js (the apps menu step offers Show titles before Show payoff) and tests/Unit/Service/SetupRequestTest.php (testTheMenuKeysIncludeTitles)
- GIVEN the apps menu step of the setup wizard with the thematiq apps menu on
- WHEN the administrator switches "Show titles" off and presses Apply
- THEN `menu.titles` MUST be false for everyone

### Requirement: Show titles in the top bar is a Theming choice of the administrator (REQ-TQMT-005)
The menu settings MUST have the switch `topBarTitles`, shipped false, checked as true or false like
the other switches. It is a Theming key, like `titles`: an administrator sets it for everyone in app
value `thematiq` / `menu.topBarTitles` through `POST /apps/thematiq/app-menu/defaults`, and
`POST /apps/thematiq/app-menu/settings` MUST refuse it with 400 naming `topBarTitles`. It is separate
from `titles`: `titles` is about the entries of the open apps menu, `topBarTitles` about the top bar.
It replaces the admin page's former option "Show text labels in app menu (hide icons)" (menu-labels):
the former app value `show_menu_labels` MUST move into it once, and until then MUST be read while
`menu.topBarTitles` is absent. The OCS capability field `showMenuLabels` and the configuration bundle
key `showMenuLabels` MUST carry its value, so they keep their names; importing a bundle or restoring a
theme version with `showMenuLabels` MUST set `menu.topBarTitles`. It is set in two places, both for
administrators only: Menu settings, For everyone, tab Theming, as the switch "Show titles in the top
bar" (`role="switch"`, with its help text) directly under "Show titles"; and the admin page, Layout and
options, Menu card, in place of the former option. It does not depend on `titles` and does not
disable Show payoff. Save with For everyone MUST send it with the other settings.

Boards: AppMenuVormgeving, TqLayout.

#### Scenario: Titles in the top bar are off unless the administrator switches them on
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTopBarTitlesShipOff)
- GIVEN neither `menu.topBarTitles` nor `show_menu_labels` is stored
- THEN the effective `topBarTitles` MUST be false

#### Scenario: An instance that had text labels keeps them
@e2e exclude a repair step on upgrade; proven by tests/Unit/Repair/MigrateMenuLabelsToTopBarTitlesTest.php (testTheFormerValueMovesOnceAndIsRemoved)
- GIVEN the app value `show_menu_labels` is `'1'`
- WHEN thematiq is upgraded
- THEN `menu.topBarTitles` MUST be true and `show_menu_labels` MUST be gone

#### Scenario: The administrator switches titles in the top bar on in Menu settings
@e2e exclude a dialog save; proven by tests/vitest/menuSettingsDialog.spec.js (Show titles in the top bar sits under Show titles and is saved for everyone)
- GIVEN an administrator in Menu settings, For everyone, tab Theming
- WHEN they switch "Show titles in the top bar" on and press Save
- THEN `POST /apps/thematiq/app-menu/defaults` MUST carry `topBarTitles: true`
- AND "Show payoff" MUST stay as it was

#### Scenario: A person may not set titles in the top bar
@e2e exclude Pure controller logic: PHPUnit tests/Unit/Controller/AppMenuSettingsControllerTest.php (testAPersonMayNotSetTopBarTitles)
- GIVEN a person who is not an administrator
- WHEN they send `{topBarTitles: true}` to `POST /apps/thematiq/app-menu/settings`
- THEN the answer MUST be 400 naming `topBarTitles` and nothing MUST change

### Requirement: With titles in the top bar on, pinned apps show their names (REQ-TQMT-006)
With the effective `topBarTitles` on, each pinned app in the workplace top bar MUST show the app's name
next to its icon, in the bar's text style. The visible name is then the pin's accessible name, and
the pin MUST NOT show the name tooltip of REQ-TQMT-003. A name that does not fit the pin's maximum
width MUST be cut off with an ellipsis, and only then MUST the tooltip show the full name. Pinning,
unpinning, moving a pin and the "Drop to pin" slot MUST work as with icons only. With `topBarTitles`
off the pins stay icon-only with the tooltip. On a page that shows Nextcloud's own apps menu, with the
classic style or a house style without the workplace top bar, `topBarTitles` on MUST give Nextcloud's
menu its names, as the former option did (menu-labels).

Boards: AppMenuBalkTitels, AppMenuVastzetten, TqLayout.

#### Scenario: Pins show their names
- GIVEN the administrator switched "Show titles in the top bar" on and Anna pinned Files and Deck
- WHEN Anna opens Files
- THEN the Files and Deck pins MUST show "Files" and "Deck" next to their icons
- AND focusing the Deck pin MUST NOT show a tooltip

#### Scenario: The classic menu keeps its text labels
@e2e exclude switching the shared instance to the classic menu for everyone disturbs parallel runs; proven by tests/Unit/Service/CssInjectionServiceTest.php (testTopBarTitlesLoadTheLabelsStylesheetOnNextcloudsOwnMenu)
- GIVEN `topBarTitles` on and the classic menu style
- WHEN a person opens Files
- THEN Nextcloud's apps menu MUST show the app names
