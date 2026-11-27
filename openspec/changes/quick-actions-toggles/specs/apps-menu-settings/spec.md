# apps-menu-settings

## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: The Create button and the Create row switch on and off independently (REQ-TQQT-001)
The menu settings MUST have two switches for the quick actions, both shipped on: `showCreateButton`,
the Quick add button with the plus before the bell in the top bar, and `showCreateRow`, the Create row
at the bottom of the apps menu, above the footer. Neither MUST depend on the other, so a person can
have both, either or neither. An administrator sets the default for everyone; a person MAY set either
switch for themselves, and their value MUST win. `POST /apps/thematiq/app-menu/settings` MUST accept
both as personal keys, true or false, and null MUST follow the administrator's value again. Where the
Quick add button is drawn (Mail, Calendar and Deck) MUST NOT change.

Boards: AppMenuSnelacties, AppMenuConfiguratie, MlInbox, ClWeek, TbBoard.

#### Scenario: Both on
- GIVEN `showCreateButton` and `showCreateRow` are on for Anna
- WHEN Anna opens Mail and then the apps menu
- THEN "Quick add" MUST stand before the bell
- AND the apps menu MUST show the Create row above its footer

#### Scenario: Only the Create button
- GIVEN `showCreateButton` is on and `showCreateRow` is off for Anna
- WHEN Anna opens Mail and then the apps menu
- THEN "Quick add" MUST stand before the bell and list the Create actions
- AND the apps menu MUST NOT show the Create row

#### Scenario: Only the Create row
- GIVEN `showCreateButton` is off and `showCreateRow` is on for Anna
- WHEN Anna opens Mail and then the apps menu
- THEN no Quick add button MUST be drawn
- AND the apps menu MUST show the Create row above its footer

#### Scenario: Neither
- GIVEN `showCreateButton` and `showCreateRow` are off for Anna
- WHEN Anna opens Mail and then the apps menu
- THEN no Quick add button MUST be drawn and the apps menu MUST NOT show the Create row
- AND the templates API MUST NOT be asked

#### Scenario: A person's switch wins over the administrator's default
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testAPersonsCreateSwitchesWinOverTheAdministrators)
- GIVEN the administrator switched the Create button off for everyone
- AND Anna switched it on for herself in Menu settings
- THEN Anna MUST get the Quick add button on Mail and Bob MUST NOT

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
