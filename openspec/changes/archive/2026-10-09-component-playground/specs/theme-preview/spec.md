# Spec delta: Theme Preview (component-playground)

Preview state, its endpoints, its switcher and its isolation are all unchanged. What is added
is that a second reader honours it.

## ADDED Requirements

### Requirement: The Instrument Describes The Previewed Set
When a session preview is active, the component playground MUST describe the previewed set:
the token values it exports and the values its specimens are drawn with MUST be the
previewed set's, not the instance-wide one.

#### Scenario: The instrument follows the preview
- GIVEN an active session preview of a set other than the instance-wide one
- WHEN the admin opens the theming panel
- THEN the specimens MUST be drawn with the previewed set's values
- AND an export MUST serialise the previewed set

#### Scenario: A preview does not leak to other sessions
- GIVEN an admin previewing a set
- WHEN another user loads any page
- THEN that user MUST see the instance-wide set, exactly as before this change
