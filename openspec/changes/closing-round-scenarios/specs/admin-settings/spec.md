# admin-settings Specification (delta)

## MODIFIED Requirements

### Requirement: Live Preview Box
The settings panel MUST include a preview box that shows the visual effect of the selected token set.

#### Scenario: Preview box renders with token set colors
- GIVEN the settings panel is loaded
- WHEN the preview section renders
- THEN it MUST show a preview header bar (colored based on the token set)
- AND it MUST show a "Primary Button" styled with the token set's primary colors
- AND it MUST show a "Secondary Button" styled with the token set's secondary colors
- AND the preview MUST be contained in a `.nldesign-preview-box` element

#### Scenario: Preview updates on token set change
@e2e exclude Requires triggering dropdown change and asserting CSS color on preview element — overlaps with token-set-apply-dialog trigger tests which use Cancel to avoid saving.
- GIVEN the admin selects a different token set from the dropdown
- WHEN the JavaScript handles the change event
- THEN the preview box MUST update its colors to reflect the newly selected token set
- AND the update MUST happen without a full page reload

#### Scenario: Preview reflects Rijkshuisstijl defaults for unknown sets
@e2e exclude Requires a token set not in the hardcoded color map — no such token set guaranteed to exist in the test environment.
- GIVEN the admin selects a token set that is not in the hardcoded `tokenSetColors` map
- WHEN the preview renders
- THEN it MUST fall back to Rijkshuisstijl default colors (#154273 primary, #ffffff text)

#### Scenario: The preview shows the app shell navigation
- GIVEN the settings panel is loaded with the App view of the preview
- WHEN the preview section renders
- THEN it MUST show a header bar with the logo, the app entries and the account glyphs
- AND it MUST show a navigation list of labelled entries under captions
- AND the selected navigation entry MUST be filled with the set's primary colour and labelled in its paired text colour
- AND a change of token set MUST repaint the header bar and the navigation together
