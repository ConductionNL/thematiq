# workplace-quick-add Specification

## Purpose
The Quick add button the MlInbox, ClWeek and TbBoard boards draw in the top bar of Mail, Calendar and Deck, listing the apps menu's Create actions.

## Requirements

### Requirement: Quick add lists the apps menu's Create actions (REQ-TQQA-002)
Quick add MUST list the same Create actions as the apps menu, in the same order, fetched on the first
open only. A choice MUST run that action. With nothing to create the menu MUST say "Nothing to create
here." The arrow keys MUST move through the entries, Escape MUST close the menu and give focus back to
the button, Tab and a click outside MUST close it.

#### Scenario: The same list as the apps menu
@e2e exclude Unit: vitest tests/vitest/headerCreate.spec.js
- GIVEN the Create row offers New document and New spreadsheet
- WHEN Quick add opens
- THEN it MUST list New document and New spreadsheet in that order

#### Scenario: Escape closes
@e2e exclude Unit: vitest tests/vitest/headerCreate.spec.js
- GIVEN Quick add is open with focus on an entry
- WHEN Escape is pressed
- THEN the menu MUST close and focus MUST be on the button

### Requirement: Every page shows the Create button in the bar (REQ-TQQA-003)
While the header style resolves to `workplace` and the effective menu setting `showCreateButton` is on,
every Nextcloud page with the workplace top bar MUST show the same 44 px round button with a plus before
the bell, as the apps menu button is on every page: Files, Settings, Mail, Calendar, Deck and every
other app alike. No app MUST get a button, an entry or a position of its own. It MUST look as MlInbox,
ClWeek and TbBoard draw it, be named "Quick add", and carry `aria-haspopup="menu"`, `aria-expanded` and
`aria-controls` naming its menu. The button MUST be drawn only once the apps menu has shared its Create
actions (`window.thematiqCreateActions`); with `showCreateButton` off, or without that source, no
button MUST be drawn on any page. Drawing the button MUST NOT ask the templates API: the Create actions
MUST be fetched on the first open of Quick add or the apps menu, once per page, so a page where neither
opens pays nothing. Whether the apps menu draws its Create row (`showCreateRow`) MUST NOT change whether
the button is drawn. The button MUST stay in the bar when Nextcloud renders the bar's end again. Every
colour MUST read a token.

Boards: AppMenuSnelacties, MlInbox, ClWeek, TbBoard.

#### Scenario: Files has the button
- GIVEN the workplace header, `showCreateButton` on and a Files page
- WHEN the bar renders
- THEN "Quick add" MUST stand before the bell

#### Scenario: Settings has the button
- GIVEN the workplace header, `showCreateButton` on and the personal settings page
- WHEN the bar renders
- THEN "Quick add" MUST stand before the bell

#### Scenario: Calendar gets the same button as Files
@e2e exclude Unit: vitest tests/vitest/headerCreate.spec.js (draws the same button and the same entries on every app)
- GIVEN the workplace header and `showCreateButton` on
- WHEN a Calendar page and a Files page render and Quick add is opened on each
- THEN both MUST list the same Create actions in the same order

#### Scenario: Drawing the button asks nothing
@e2e exclude a network detail; proven by tests/vitest/headerCreate.spec.js (draws the button without asking the templates API, and asks it once on the first open)
- GIVEN `showCreateButton` on and a Files page
- WHEN the bar renders and nobody opens Quick add or the apps menu
- THEN the templates API MUST NOT be asked
- WHEN Quick add is opened twice
- THEN the templates API MUST be asked once

#### Scenario: The Create button switched off
@e2e exclude covered by the e2e scenario "Only the Create row" of apps-menu-settings; also vitest tests/vitest/headerCreate.spec.js
- GIVEN `showCreateButton` is off
- WHEN a Files, Mail or Settings page renders
- THEN no Quick add button MUST be drawn
