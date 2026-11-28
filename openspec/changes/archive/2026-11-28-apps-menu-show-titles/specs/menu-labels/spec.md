# menu-labels

## MODIFIED Requirements

### Requirement: Configuration Storage
The show menu labels feature MUST be driven by the menu setting `topBarTitles` (Show titles in the top
bar, apps-menu-settings REQ-TQMT-005), the administrator's app value `thematiq` / `menu.topBarTitles`,
shipped false. The former app value `show_menu_labels` MUST move once into `menu.topBarTitles` (`'1'`
becomes true, `'0'` false) by a repair step on upgrade, and MUST then be removed; until it has moved,
`topBarTitles` MUST read `show_menu_labels` while `menu.topBarTitles` is absent. An instance that had
the labels on MUST keep them on.

#### Scenario: Setting stored as enabled
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTopBarTitlesIsStoredForEveryone)
- GIVEN an administrator
- WHEN `POST /apps/thematiq/app-menu/defaults` is called with `{topBarTitles: true}`
- THEN the app value `menu.topBarTitles` MUST be true

#### Scenario: Setting stored as disabled
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTopBarTitlesIsStoredForEveryone)
- GIVEN an administrator
- WHEN `POST /apps/thematiq/app-menu/defaults` is called with `{topBarTitles: false}`
- THEN the app value `menu.topBarTitles` MUST be false

#### Scenario: Default value when not configured
@e2e exclude a browser cannot remove a stored app value; proven by tests/Unit/Service/AppMenuSettingsTest.php (testTopBarTitlesShipOff)
- GIVEN neither `menu.topBarTitles` nor `show_menu_labels` is stored
- WHEN the setting is read
- THEN `topBarTitles` MUST be false
- AND menu icons MUST be displayed normally (Nextcloud default behaviour)

#### Scenario: Setting persists across restarts
@e2e exclude a server restart cannot be driven from a browser; the value is an app value, proven by tests/Unit/Service/AppMenuSettingsTest.php (testTopBarTitlesIsStoredForEveryone)
- GIVEN the administrator switched titles in the top bar on
- WHEN the Nextcloud server restarts
- THEN `menu.topBarTitles` MUST still be true

#### Scenario: The former value moves once
@e2e exclude a repair step on upgrade; proven by tests/Unit/Repair/MigrateMenuLabelsToTopBarTitlesTest.php (testTheFormerValueMovesOnceAndIsRemoved)
- GIVEN the app value `show_menu_labels` is `'1'` and no `menu.topBarTitles` is stored
- WHEN the repair step runs
- THEN `menu.topBarTitles` MUST be true
- AND `show_menu_labels` MUST be gone
- AND running the step again MUST change nothing

### Requirement: Conditional CSS Loading
The show-menu-labels CSS file MUST be loaded only when the administrator's effective `topBarTitles` is
on and the page shows Nextcloud's own apps menu, that is, where thematiq does not draw the workplace
apps menu. On a page with the workplace apps menu it MUST NOT be loaded, because its `.app-item__label`
rules would put names back into an icon-only workplace menu; the workplace top bar draws its pins'
names itself (apps-menu-settings REQ-TQMT-006).

#### Scenario: Feature enabled loads CSS
@e2e exclude asset loading of one page request; proven by tests/Unit/Service/CssInjectionServiceTest.php (testTopBarTitlesLoadTheLabelsStylesheetOnNextcloudsOwnMenu)
- GIVEN `topBarTitles` is on and the menu style is classic
- WHEN the theme CSS is injected
- THEN `show-menu-labels` CSS MUST be loaded

#### Scenario: Feature disabled skips CSS
@e2e exclude asset loading of one page request; proven by tests/Unit/Service/CssInjectionServiceTest.php (testTogglesAbsentFromConfigDefaultToOff)
- GIVEN `topBarTitles` is off
- WHEN the theme CSS is injected
- THEN `show-menu-labels` CSS MUST NOT be loaded

#### Scenario: CSS loading order relative to other conditionals
@e2e exclude stylesheet order of one page request; proven by tests/Unit/Service/CssInjectionServiceTest.php (testConditionalStylesheetOrder)
- GIVEN hide_slogan and `topBarTitles` are on and the menu style is classic
- WHEN the CSS files are loaded
- THEN hide-slogan MUST be loaded before show-menu-labels
- AND both MUST load after the custom-overrides layer

#### Scenario: The workplace menu does not load it
@e2e exclude asset loading of one page request; proven by tests/Unit/Service/CssInjectionServiceTest.php (testTheWorkplaceMenuDoesNotLoadTheLabelsStylesheet)
- GIVEN `topBarTitles` is on and the page draws the workplace apps menu
- WHEN the theme CSS is injected
- THEN `show-menu-labels` CSS MUST NOT be loaded

### Requirement: Admin Settings Panel Integration
The admin page MUST control the feature with the switch "Show titles in the top bar" in the Menu card
of Layout and options (admin-settings, Show Menu Labels Checkbox), saved through
`POST /apps/thematiq/app-menu/defaults` with `{topBarTitles}`.

#### Scenario: Checkbox reflects current state on load
@e2e exclude the checked state of one switch; proven by tests/vitest/adminLayoutTopBarTitles.spec.js (the switch is checked from the administrator's topBarTitles)
- GIVEN `menu.topBarTitles` is true
- WHEN the settings panel loads
- THEN "Show titles in the top bar" MUST be checked

#### Scenario: Checkbox change triggers save
- GIVEN an administrator on Layout and options with "Show titles in the top bar" off
- WHEN they switch it on
- THEN the app value `menu.topBarTitles` MUST be true
- AND a person's pinned apps in the workplace top bar MUST show their names next to their icons

#### Scenario: Checkbox label is localized and accessible
@e2e exclude the label of one switch; proven by tests/vitest/adminLayoutTopBarTitles.spec.js (the switch is labelled and has its help text)
- GIVEN the settings panel renders
- THEN the switch MUST read "Show titles in the top bar" via `$l->t()` with `role="switch"` and its help text

## REMOVED Requirements

### Requirement: API Endpoint
**Reason**: the switch is a menu setting now and is saved through `POST /apps/thematiq/app-menu/defaults`, which checks every menu value and is for administrators only.
**Migration**: send `{topBarTitles: true|false}` to `POST /apps/thematiq/app-menu/defaults`. The route `/settings/menulabels` and `SettingsController::setMenuLabelsSetting()` are removed; the stored value moves by the repair step of Configuration Storage.
