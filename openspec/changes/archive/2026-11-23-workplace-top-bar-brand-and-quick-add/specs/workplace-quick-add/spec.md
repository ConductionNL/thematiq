# workplace-quick-add

## ADDED Requirements

### Requirement: Mail, Calendar and Deck show Quick add in the bar (REQ-TQQA-001)
While the header style resolves to `workplace`, a page of Mail, Calendar or Deck MUST show a 44 px
round button with a plus before the bell, as MlInbox, ClWeek and TbBoard draw it, named "Quick add",
with `aria-haspopup="menu"`, `aria-expanded` and `aria-controls` naming its menu. No other app MUST get
it. The button MUST be drawn only once the apps menu has shared its Create actions
(`window.thematiqCreateActions`); with the Create actions switched off in Menu settings, or without that
source, no button MUST be drawn. The button MUST stay in the bar when Nextcloud renders the bar's end
again. Every colour MUST read a token.

#### Scenario: Calendar's bar
@e2e exclude Unit: vitest tests/vitest/headerCreate.spec.js places the button before #notifications
- GIVEN the workplace header and a Calendar page
- WHEN the bar renders
- THEN "Quick add" MUST stand before the bell

#### Scenario: Files has none
@e2e exclude Unit: vitest tests/vitest/headerCreate.spec.js
- GIVEN the workplace header and a Files page
- WHEN the bar renders
- THEN no Quick add button MUST be drawn

#### Scenario: Create actions switched off
@e2e exclude Unit: vitest tests/vitest/headerCreate.spec.js and tests/vitest/appMenuCreate.spec.js
- GIVEN `showCreateActions` is off
- WHEN a Mail page renders
- THEN no Quick add button MUST be drawn

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
