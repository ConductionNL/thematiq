# apps-menu-settings

## ADDED Requirements

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
