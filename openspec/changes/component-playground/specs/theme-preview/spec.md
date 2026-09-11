# Spec delta: Theme Preview (component-playground)

Preview state, its endpoints and its isolation are unchanged. What is added is a second page
that honours them: the playground carries the same switcher and must behave as the settings
page does when a preview is active.

## ADDED Requirements

### Requirement: The Playground Honours An Active Preview
When a session preview is active, the playground MUST render the previewed set rather than
the instance-wide one, and its switcher MUST show the previewed set as current. Starting or
discarding a preview from the playground MUST apply the change to the page by swapping the
stylesheet run, without navigating.

#### Scenario: The playground opens on the previewed set
- GIVEN an active session preview of a set other than the instance-wide one
- WHEN the admin opens the playground
- THEN every component MUST be rendered with the previewed set's values
- AND the switcher MUST show the previewed set as current

#### Scenario: Discarding a preview from the playground
- GIVEN a preview started from the playground
- WHEN the admin discards it
- THEN the page MUST return to the instance-wide set by swapping the stylesheet run
- AND the browser MUST NOT navigate

#### Scenario: A preview does not leak to other sessions
- GIVEN an admin previewing a set in the playground
- WHEN another user loads any page
- THEN that user MUST see the instance-wide set, exactly as before this change
