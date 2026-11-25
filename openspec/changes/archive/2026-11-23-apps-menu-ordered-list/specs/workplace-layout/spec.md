# workplace-layout

## MODIFIED Requirements

### Requirement: A token set may group the apps menu
A token set's manifest entry MAY carry an `app_menu` block with `groups`, `others_title`, `hint`
and `manage_label`. Each group MUST have a `title` and at least one app; an app is an id of lower
case letters, digits and underscores, with an optional `description`; a group MAY carry a `link`
`{label, url}`, kept only with an http(s) address or a path on this server. Texts MUST follow the
login copy's rules (one string or a map per language). At most four groups of 24 apps MUST be
kept. An administrator MAY store the same shape in the app config key `app_menu_config`; its keys
MUST win over the set's, key by key. The groups MUST give the apps menu its default order (the
groups' apps first, in group order) and each app its second line (the `description`).

#### Scenario: Zuiddrecht ships the board's section
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuServiceTest.php reads the shipped manifest
- GIVEN the token set `zuiddrecht` and a Dutch request
- WHEN the menu is resolved
- THEN the first group MUST be "Werk" with eight apps, dossiq described as "Zaken en Woo"
- AND its link MUST be "Mijn werkdag in launchpad" to `/apps/launchpad/`

#### Scenario: What may not be shown is dropped
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuServiceTest.php
- GIVEN an override with an app id `Bad-Id`, a group without a title, an empty group and a `javascript:` link
- WHEN the menu is resolved
- THEN none of those MUST be returned

### Requirement: The workplace header draws the apps menu of the board
While the header style resolves to `workplace`, the open apps menu MUST be 640px wide (never wider
than the window), MUST NOT scroll inside its grid, MUST NOT underline or break an app's name
mid-word, and MUST draw each icon in a chip. The page MUST carry the initial state
`thematiq/app-menu` and MUST load `js/app-menu.js`; without the workplace header neither MUST be
emitted. The script MUST write text only and MUST NOT move the tiles in the DOM.

The menu MUST be one list in three columns, in the order of the apps-menu-order capability. Each
app MUST show its name and, when its group gives one, its description as a second line (hidden
from assistive technology). No group heading MUST be drawn. The first nine entries MUST show. With
more than nine, a button "All apps" MUST follow them; it MUST unfold the rest under a line, carry
`aria-expanded`, and then read "Hide apps". Without more than nine, no button MUST be drawn. The
rest MUST list the Nextcloud apps first, then the external apps under their own heading. A footer
MUST follow with the drag hint, the hint and, when Nextcloud shows a "more apps" tile,
`manage_label` linking to that tile's address, which then hides.

#### Scenario: Zuiddrecht's apps menu
- GIVEN the token set `zuiddrecht`, a signed-in administrator with more than nine apps and no stored order
- WHEN the grid button opens the apps menu
- THEN the eight apps of "Werk" MUST come first with their descriptions, then one other app
- AND every app after the ninth MUST be hidden
- AND a button "All apps" with `aria-expanded="false"` MUST follow the nine
- AND the footer MUST end with "Apps beheren"

#### Scenario: All apps unfolds the rest
- GIVEN the open apps menu of the previous scenario
- WHEN "All apps" is activated
- THEN every app MUST show, the rest under a line
- AND the button MUST read "Hide apps" with `aria-expanded="true"`
- WHEN it is activated again
- THEN only the first nine MUST show

#### Scenario: Nine apps or fewer
@e2e exclude a test instance with nine apps or fewer is not kept; proven by tests/vitest/appMenu.spec.js (draws no toggle with nine entries)
- GIVEN a user who sees nine apps
- WHEN the apps menu opens
- THEN every app MUST show and no "All apps" button MUST be drawn

#### Scenario: The arrow keys follow the screen
- GIVEN the open apps menu, focus on its first app
- WHEN Arrow right, then End is pressed
- THEN focus MUST move to the second app on screen, then to the last visible entry
- AND an app past the nine MUST NOT receive focus while the rest is folded

#### Scenario: The layer asks with the page's set
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php and AppMenuServiceTest.php
- GIVEN a page without the workplace header style
- WHEN it renders
- THEN no apps menu state and no script MUST be emitted
