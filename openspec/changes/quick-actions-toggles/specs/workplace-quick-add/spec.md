# workplace-quick-add

## MODIFIED Requirements

### Requirement: Mail, Calendar and Deck show Quick add in the bar (REQ-TQQA-001)
While the header style resolves to `workplace` and the effective menu setting `showCreateButton` is on,
a page of Mail, Calendar or Deck MUST show a 44 px round button with a plus before the bell, as MlInbox,
ClWeek and TbBoard draw it, named "Quick add", with `aria-haspopup="menu"`, `aria-expanded` and
`aria-controls` naming its menu. No other app MUST get it. The button MUST be drawn only once the apps
menu has shared its Create actions (`window.thematiqCreateActions`); with `showCreateButton` off, or
without that source, no button MUST be drawn. Whether the apps menu draws its Create row
(`showCreateRow`) MUST NOT change whether the button is drawn. The button MUST stay in the bar when
Nextcloud renders the bar's end again. Every colour MUST read a token.

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
@e2e exclude covered by the e2e scenario "Only the Create row" of apps-menu-settings; also vitest tests/vitest/headerCreate.spec.js
- GIVEN `showCreateButton` is off
- WHEN a Mail page renders
- THEN no Quick add button MUST be drawn
