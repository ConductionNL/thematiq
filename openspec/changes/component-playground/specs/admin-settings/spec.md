# Spec delta: Admin Settings (component-playground)

The settings section keeps every control it has. What is added is the way out of it: the
compact token editor stays for admins who know which variable they want, and the playground
becomes the visual surface for admins who need to see the result.

## ADDED Requirements

### Requirement: The Settings Section Links To The Playground
The admin settings section MUST offer a control that opens the component playground, and the
token-set apply dialog MUST offer the same. The control MUST be present whether or not the
active set has a conversion report, because the playground's value does not depend on one.

#### Scenario: Opening the playground from the settings section
- GIVEN an administrator on the Thematiq settings section
- WHEN they activate the playground control
- THEN the playground page MUST open for the currently active token set

#### Scenario: Opening the playground after applying a set
- GIVEN the token-set apply dialog is open
- WHEN the admin chooses to open the playground from it
- THEN the playground MUST open for the set the dialog was applying

### Requirement: The Token Editor Remains The Compact View
The settings section's tabbed token editor MUST remain, and MUST keep writing through the
overrides endpoint. The playground MUST NOT replace it: the two are alternative views of one
file, and an admin who knows the variable's name MUST NOT be forced through a visual page to
set it.

#### Scenario: The token editor still saves
- GIVEN an edit made in the settings section's token editor
- WHEN the admin saves
- THEN it MUST be written through the overrides endpoint exactly as before this change
