# apps-menu-settings

## ADDED Requirements

### Requirement: Menu settings are admin defaults with per-user overrides
The apps menu MUST be drawn from these settings, with these shipped values: `style` workplace,
`columns` 4, `rows` 6, `rowsAboveFold` 2, `showCreateActions` true, `showExternalApps` true, `shape`
round, `tiles` false, `payoff` true, `iconSize` large, `iconColour` brand, and the lists `order`,
`pinned`, `hidden` and `externalApps`, empty. An administrator sets any key for everyone, kept in app
values `thematiq` / `menu.<key>`. A person sets only the personal keys `order`, `pinned`, `hidden` and
`externalApps`, kept in user values with the same names. A person's key MUST win over the
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
fold toggle, which reads "More apps" and, unfolded, "Fewer apps"; past `rows` rows the unfolded menu MUST
scroll. `shape` square MUST give the icons a rounded square, `tiles` MUST give every entry a tile,
`iconSize` small MUST draw 32 px icons and large 40 px. With `iconColour` brand the organisation's work
apps, the apps of the menu's first section, MUST take the brand's light wash and every other app,
external apps included, the grey chip; `neutral` MUST draw every app grey. An entry MUST be 68 px high
(12 px by 6 px padding round a 44 px content box), as the AppMenuAlles board measures. With `payoff` off no entry shows its second line; hidden entries MUST NOT show; with
`showExternalApps` off the external apps MUST NOT show; with `showCreateActions` off the Create row MUST
NOT show and the templates API MUST NOT be asked. A value the menu cannot use MUST fall back to its
shipped value or the nearest allowed number. The footer MUST show a gear button "Menu settings" with
`aria-haspopup="dialog"` in place of the Manage apps link. The Zuiddrecht set MUST give the Nextcloud
apps their payoff too.

#### Scenario: Four columns of two rows
@e2e exclude the demo stack is being filmed and cannot be deployed to; proven by tests/vitest/appMenuSettings.spec.js (shows four columns of two rows by default)
- GIVEN the shipped settings
- WHEN the apps menu opens
- THEN 8 entries MUST show before More apps

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
labelled steppers for columns (2 to 5), rows and rows above More apps with help text, a preview of the
grid, the two section switches, and for an administrator the Manage apps link with "Admins only"; for a
person in Only for me its controls MUST be disabled with the note that the administrator sets them.
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
