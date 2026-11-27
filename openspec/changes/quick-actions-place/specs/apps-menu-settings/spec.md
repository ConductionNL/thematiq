# apps-menu-settings

## ADDED Requirements

### Requirement: The place of the quick actions is an administrator's menu setting (REQ-TQQP-001)
The menu settings MUST have the key `quickActionsPlace` with the choices `topBar` and `appsMenu` and the
shipped value `topBar`. Only an administrator MUST set it, for everyone, in app value `thematiq` /
`menu.quickActionsPlace`; it MUST NOT be a personal key. `POST /apps/thematiq/app-menu/defaults` MUST
accept it, check it against its choices and refuse any other value with 400 naming
`quickActionsPlace`, storing nothing. `POST /apps/thematiq/app-menu/settings` MUST refuse it with 400
naming it, as it refuses every key that is not personal. Sent as null it MUST follow the shipped value
again, and `DELETE /apps/thematiq/app-menu/defaults` MUST remove it. The effective value MUST be in the
`settings` of the `thematiq/app-menu` initial state for every person.

#### Scenario: The shipped place is the top bar
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTheQuickActionsPlaceShipsAsTheTopBar)
- GIVEN no administrator set the place of the quick actions
- WHEN the menu settings are read for Anna
- THEN `quickActionsPlace` MUST be `topBar`

#### Scenario: A person may not move the quick actions
@e2e exclude Pure controller logic: PHPUnit tests/Unit/Controller/AppMenuSettingsControllerTest.php (testAPersonMayNotSetTheQuickActionsPlace)
- GIVEN a person who is not an administrator
- WHEN they send `{quickActionsPlace: "appsMenu"}` to `POST /apps/thematiq/app-menu/settings`
- THEN the answer MUST be 400 naming `quickActionsPlace` and nothing MUST change

#### Scenario: An unknown place is refused
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testAnUnknownQuickActionsPlaceIsRefused)
- GIVEN an administrator
- WHEN they send `{quickActionsPlace: "sidebar"}` to `POST /apps/thematiq/app-menu/defaults`
- THEN the answer MUST be 400 naming `quickActionsPlace` and nothing MUST be stored

### Requirement: The administrator chooses the place on thematiq's admin page (REQ-TQQP-002)
The Menu card of the Layout and options panel on thematiq's admin page MUST show a `radiogroup`
labelled "Quick actions" with two radios, "In the top bar" and "In the apps menu", each with its help
text, checked from the administrator's effective `quickActionsPlace`. Choosing a radio MUST save at once
through `POST /apps/thematiq/app-menu/defaults` with only `quickActionsPlace`, say "Saved" in the page's
polite live region, and on a failure say why in an alert and check the previous radio again. With
`showCreateActions` off, or with the classic menu style, the radios MUST be disabled with a note saying
why. The choice MUST be listed among the changes in this session, as the panel's other options are.

Boards: the settings board on `design/thematiq-quick-actions-place`, TqLayout.

#### Scenario: The administrator moves the quick actions to the apps menu
- GIVEN an administrator on Layout and options with the quick actions in the top bar
- WHEN they choose "In the apps menu"
- THEN the app value `menu.quickActionsPlace` MUST be `appsMenu`
- AND a Mail page MUST show no Quick add button before the bell

#### Scenario: The choice waits for the create actions
@e2e exclude a disabled state of two radios; proven by tests/vitest/adminLayoutQuickActions.spec.js (disables the place without create actions or with the classic menu)
- GIVEN the administrator switched the create actions off for everyone
- WHEN they open Layout and options
- THEN both radios MUST be disabled with "Switch on the create actions first."

### Requirement: Menu settings shows the place under the Create switch (REQ-TQQP-003)
The Configuration tab of the Menu settings dialog MUST show the same "Quick actions" `radiogroup` in its
Sections block, under "Show create actions". For an administrator choosing For everyone it MUST be
editable and its value MUST go with the For everyone save. In Only for me, and for a person who is not
an administrator, it MUST be disabled under the note that the administrator sets it, showing the
effective value. With "Show create actions" off in the form it MUST be disabled too.

Boards: AppMenuInstellingen, the settings board on `design/thematiq-quick-actions-place`.

#### Scenario: A person sees the place but cannot change it
@e2e exclude a dialog detail; proven by tests/vitest/menuSettingsDialog.spec.js (shows the quick actions place disabled in Only for me)
- GIVEN a person who is not an administrator opens Menu settings
- WHEN they open Configuration
- THEN the "Quick actions" radios MUST be disabled and show the administrator's choice

#### Scenario: An administrator saves the place for everyone
@e2e exclude a dialog detail; proven by tests/vitest/menuSettingsDialog.spec.js (sends the quick actions place with the For everyone save)
- GIVEN an administrator in Menu settings with For everyone chosen
- WHEN they choose "In the apps menu" and press Save
- THEN `POST /apps/thematiq/app-menu/defaults` MUST carry `quickActionsPlace: "appsMenu"`
