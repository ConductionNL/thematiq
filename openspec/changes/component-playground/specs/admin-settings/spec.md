# Spec delta: Admin Settings (component-playground)

The settings section keeps every control it has. What is added is a way of reading its token
editor: the same rows, narrowed to one component at a time and shown next to a drawing of it.

## ADDED Requirements

### Requirement: The Panel Publishes What The Instrument Reads
The settings section MUST publish the component inventory, the conversion reason vocabulary,
the active set's resolved `--nldesign-*` values and the variable-to-token map over the
initial-state channel, for the set the page is WEARING — a session preview wins over the
instance-wide set, exactly as the render does.

#### Scenario: The instrument describes the set on the page
- GIVEN an active session preview of a set other than the instance-wide one
- WHEN the admin opens the settings section
- THEN the published token values MUST be the previewed set's

#### Scenario: A missing key is not a crash
- GIVEN the component inventory cannot be read
- WHEN the settings section renders
- THEN it MUST render its token editor unchanged
- AND the instrument MUST simply not build

### Requirement: The Token Editor Remains The Full View
The tabbed token editor MUST remain, and MUST keep writing through the overrides endpoint.
The instrument MUST NOT replace it: "full view" MUST be the first chip of every tab and MUST
restore the complete list, so an admin who knows the variable's name is never forced through
a component to set it.

#### Scenario: The token editor still saves
- GIVEN an edit made in the settings section's token editor
- WHEN the admin saves
- THEN it MUST be written through the overrides endpoint exactly as before this change

#### Scenario: The full list is one click away
- GIVEN a component selected in the instrument
- WHEN the admin returns to the full view
- THEN every token of the open tab MUST be listed again, unchanged
