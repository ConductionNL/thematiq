# component-playground Specification (delta)

## ADDED Requirements

### Requirement: The playground previews the login page before it is published

The playground SHALL show Nextcloud's login card, drawn with Nextcloud's own login-page stylesheet scoped to the playground, using the token values the editor holds at that moment. A change in the editor SHALL show in the preview before the administrator saves it, and the live login page SHALL NOT change until the set is saved.

#### Scenario: An administrator changes the primary colour
- GIVEN the administrator opens the playground's login page tab
- WHEN they change the primary colour in the token editor without saving
- THEN the preview's login button takes the new colour
- AND the instance's real login page still shows the saved colour
