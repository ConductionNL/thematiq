---
status: done
---

# Token Set Dropdown Specification

## Purpose
Replace the radio button list for token set selection with a searchable dropdown (`<select>`) that scales to 400+ entries, improving usability as the number of available token sets grows.

## Requirements

### Requirement: Dropdown Token Set Selector
The admin settings page MUST use a `<select>` dropdown instead of radio buttons for token set selection.

#### Scenario: Dropdown renders with all token sets
- GIVEN the admin opens nldesign settings
- WHEN the page loads
- THEN a `<select>` dropdown SHALL be rendered containing all available token sets
- AND each option SHALL display the token set name
- AND the currently active token set SHALL be pre-selected

#### Scenario: Dropdown is searchable via browser native behavior
- GIVEN the dropdown is focused
- WHEN the admin types characters
- THEN the browser's native type-to-filter behavior SHALL narrow the options
- AND this SHALL work without custom JavaScript search logic

#### Scenario: Token set selection triggers save
- GIVEN the admin selects a different token set from the dropdown
- WHEN the selection changes, and the admin confirms the apply dialog when one is shown
- THEN the token set SHALL be saved via `POST /settings/tokenset`
- AND a success or error notification SHALL be displayed

#### Scenario: Dropdown handles 400+ entries
- GIVEN 400+ token sets are available
- WHEN the admin opens the dropdown
- THEN all entries SHALL be listed
- AND the dropdown SHALL remain responsive (no UI freeze)

### Requirement: Token Sets Sorted Alphabetically
The dropdown options MUST be sorted alphabetically by display name for easy scanning.

#### Scenario: Token sets appear in alphabetical order
- GIVEN multiple token sets with names like "Gemeente Amsterdam", "Gemeente Zwolle", "Rijkshuisstijl", "VNG Vereniging Nederlandse Gemeenten"
- WHEN the dropdown renders
- THEN options SHALL appear sorted alphabetically by name: "Gemeente Amsterdam", "Gemeente Zwolle", "Rijkshuisstijl", "VNG Vereniging Nederlandse Gemeenten"

### Requirement: Preview Updates on Selection
The existing theme preview section MUST update when a new token set is selected from the dropdown.

#### Scenario: Preview reflects new selection
- GIVEN the admin selects a different token set from the dropdown
- WHEN the selection changes
- THEN the preview section SHALL update its colors to reflect the new token set
- AND the update SHALL happen before the API save call completes (optimistic UI)
