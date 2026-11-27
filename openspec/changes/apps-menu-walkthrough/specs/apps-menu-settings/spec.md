# apps-menu-settings

## ADDED Requirements

### Requirement: Menu settings offers to replay the tour (REQ-TQWT-008)

The footer of the Menu settings dialog MUST have a "Replay walkthrough" button between Reset to default
and Cancel, for every person and for an administrator in either scope. It MUST close the dialog without
saving, asking first when there are unsaved changes, then open the apps menu and start the apps menu
tour at its first step, running every step. With the classic menu style the button MUST NOT be drawn,
because there is no workplace menu to tour.

Boards: TqMenuRondleidingOpnieuw, AppMenuInstellingen.

#### Scenario: Replay from Menu settings
- GIVEN a person who finished the tour opens Menu settings
- WHEN they press "Replay walkthrough"
- THEN the dialog MUST close, the apps menu MUST open and the tour MUST start at the open step

#### Scenario: Unsaved changes are not lost without asking
@e2e exclude a dialog detail; proven by tests/vitest/menuSettingsDialog.spec.js (replay asks before dropping unsaved changes)
- GIVEN Menu settings with an app pinned and not yet saved
- WHEN "Replay walkthrough" is pressed
- THEN the dialog MUST ask whether to drop the changes before it closes
