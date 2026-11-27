# apps-menu-settings

## ADDED Requirements

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
text) between "Show items as tiles" and "Show payoff". While it is off, Show payoff MUST be disabled
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
theme version with `showMenuLabels` MUST set `menu.topBarTitles`. It is set on the admin page, Layout
and options, Menu card, as "Show titles in the top bar".

Boards: TqLayout.

#### Scenario: Titles in the top bar are off unless the administrator switches them on
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testTopBarTitlesShipOff)
- GIVEN neither `menu.topBarTitles` nor `show_menu_labels` is stored
- THEN the effective `topBarTitles` MUST be false

#### Scenario: An instance that had text labels keeps them
@e2e exclude a repair step on upgrade; proven by tests/Unit/Repair/MigrateMenuLabelsToTopBarTitlesTest.php (testTheFormerValueMovesOnceAndIsRemoved)
- GIVEN the app value `show_menu_labels` is `'1'`
- WHEN thematiq is upgraded
- THEN `menu.topBarTitles` MUST be true and `show_menu_labels` MUST be gone

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

Boards: AppMenuVastzetten, TqLayout.

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
