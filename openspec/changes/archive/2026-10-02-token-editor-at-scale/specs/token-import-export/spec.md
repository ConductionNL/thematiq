## ADDED Requirements

### Requirement: Overrides round-trip every registry token
Exporting the overrides and importing the file again MUST restore every override, for every registry token, including dark values.

@e2e exclude File download and upload with assertions on the stored overrides; the visible effect of an imported internal token is covered by `component-tokens` ("A variable the component declares itself is reached").

#### Scenario: An internal token survives the round trip
- GIVEN the admin has set `--nldesign-nc-dp-hover-color` to `#e8eef5`
- WHEN the admin downloads the overrides and uploads the same file
- THEN the override MUST be restored with the same value

#### Scenario: A dark value survives the round trip
- GIVEN the admin has set `--color-mark` with a dark value `#5c4a00`
- WHEN the overrides are exported and imported
- THEN the dark value MUST be restored for the dark scopes only

#### Scenario: A file from before this change still imports
- GIVEN an overrides file exported before this change, holding only brand tokens
- WHEN the admin imports it
- THEN every token in it MUST be imported as before
