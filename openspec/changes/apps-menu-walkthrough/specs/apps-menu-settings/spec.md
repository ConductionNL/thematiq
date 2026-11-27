# apps-menu-settings

## ADDED Requirements

### Requirement: Menu settings offers to replay the tour (REQ-TQWT-008)

The footer of the Menu settings dialog MUST have a "Replay the tour" button, left of Reset to default,
for every person and for an administrator in either scope. It MUST close the dialog without saving,
asking first when there are unsaved changes, then open the apps menu and start the apps menu tour at
its first step. With the classic menu style the button MUST NOT be drawn, because there is no workplace
menu to tour.

#### Scenario: Replay from Menu settings
- GIVEN a person who finished the tour opens Menu settings
- WHEN they press "Replay the tour"
- THEN the dialog MUST close, the apps menu MUST open and the tour MUST start at the open step

#### Scenario: Unsaved changes are not lost without asking
@e2e exclude a dialog detail; proven by tests/vitest/menuSettingsDialog.spec.js (replay asks before dropping unsaved changes)
- GIVEN Menu settings with an app pinned and not yet saved
- WHEN "Replay the tour" is pressed
- THEN the dialog MUST ask whether to drop the changes before it closes
