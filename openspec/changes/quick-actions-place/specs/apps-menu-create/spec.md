# apps-menu-create

## ADDED Requirements

### Requirement: The Create row is in the apps menu in either place (REQ-TQQP-004)
The Create row of the apps menu MUST be drawn with both values of `quickActionsPlace`, under the
existing rules of the Create row: above the footer with "Drag an app to reorder" and the Menu settings
gear, and not with `showCreateActions` off or without any creator or action. With `appsMenu` the row is
the only place of the quick actions and MUST look as the AppMenuSnelacties board draws it. The shared
Create actions (`window.thematiqCreateActions`) MUST carry a `place` field with the effective
`quickActionsPlace`, so the top bar script can follow it.

Boards: AppMenuSnelacties, AppMenu.

#### Scenario: The Create row in the apps menu place
@e2e exclude covered by the e2e scenario "The quick actions are in the apps menu" of workplace-quick-add; the row's drawing is proven by tests/vitest/appMenuCreate.spec.js (draws the Create row with either place)
- GIVEN the administrator set the quick actions to the apps menu
- WHEN a person opens the apps menu on Files
- THEN the Create heading and its chips MUST show above the footer

#### Scenario: The shared actions name their place
@e2e exclude a script contract between two scripts; proven by tests/vitest/appMenuCreate.spec.js (shares the place with the Create actions)
- GIVEN `quickActionsPlace` is `appsMenu`
- WHEN the apps menu script shares its Create actions
- THEN `window.thematiqCreateActions.place` MUST be `appsMenu`
