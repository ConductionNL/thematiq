# custom-token-sets

## ADDED Requirements

### Requirement: An admin SHALL be able to save edits as a new theme

When an admin saves token edits while no token set is active (the admin works on Nextcloud's own
theme), the system SHALL ask whether to save the edits as a new theme or to change the Nextcloud
theme. Saving as a new theme SHALL store the edited tokens as a custom token set under the name
the admin gives. The new set SHALL appear in the active token set list without a reload. Edited
values that do not fit in a token set SHALL stay saved as overrides, and the system SHALL show the
admin which values those are. Choosing to change the Nextcloud theme SHALL save the edits as
overrides, as Save does today. An admin SHALL be able to switch the question off.

Rows: `aut-save-as-new-theme` (thematiq matrix).

#### Scenario: Edits become a new named theme
@e2e exclude Covered by Vitest in tests/vitest/admin-token-editor.spec.js ("creates the theme as it is ... and selects it"); the upload and the dropdown are mocked at the fetch boundary.
- **GIVEN** no token set is active and the admin changed two table tokens
- **WHEN** the admin saves, enters "Zuiddrecht tabellen" and chooses "Bewaren als nieuw thema"
- **THEN** a custom token set "Zuiddrecht tabellen" holds both tokens
- **AND** it appears in the active token set list without a reload

#### Scenario: Values that fit no token set stay as overrides
@e2e exclude Covered by Vitest in tests/vitest/admin-token-editor.spec.js: the leftover is saved as an override of the new set and listed, or listed as not saved when that fails.
- **GIVEN** an edit that a token set cannot hold
- **WHEN** the admin saves the edits as a new theme
- **THEN** that value stays saved as an override and the admin sees it listed

#### Scenario: Changing the Nextcloud theme saves overrides
@e2e exclude Covered by Vitest in tests/vitest/admin-token-editor.spec.js ("writes over the Nextcloud theme when asked to").
- **WHEN** the admin chooses "Het Nextcloud-thema aanpassen"
- **THEN** the edits are saved as overrides and no new token set is created

#### Scenario: The question only comes on Nextcloud's own theme
@e2e exclude Covered by Vitest in tests/vitest/admin-token-editor.spec.js ("saving on a token set" and "does not ask when told not to").
- **GIVEN** a token set is active
- **WHEN** the admin saves edits
- **THEN** the system saves them without asking

#### Scenario: A name already in use is refused
@e2e exclude Covered by Vitest in tests/vitest/admin-token-editor.spec.js ("keeps the dialog open with the server refusal") and by the upload's 409 in PHPUnit.
- **GIVEN** a custom token set named "Zuiddrecht tabellen" exists
- **WHEN** the admin saves edits as a new theme with that name
- **THEN** the system asks for another name and saves nothing
