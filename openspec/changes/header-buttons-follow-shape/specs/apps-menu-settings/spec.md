# apps-menu-settings

## ADDED Requirements

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
