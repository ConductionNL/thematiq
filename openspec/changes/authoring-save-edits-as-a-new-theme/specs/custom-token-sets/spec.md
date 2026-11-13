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
- **GIVEN** no token set is active and the admin changed two table tokens
- **WHEN** the admin saves, enters "Zuiddrecht tabellen" and chooses "Bewaren als nieuw thema"
- **THEN** a custom token set "Zuiddrecht tabellen" holds both tokens
- **AND** it appears in the active token set list without a reload

#### Scenario: Values that fit no token set stay as overrides
- **GIVEN** an edit that a token set cannot hold
- **WHEN** the admin saves the edits as a new theme
- **THEN** that value stays saved as an override and the admin sees it listed

#### Scenario: Changing the Nextcloud theme saves overrides
- **WHEN** the admin chooses "Het Nextcloud-thema aanpassen"
- **THEN** the edits are saved as overrides and no new token set is created

#### Scenario: The question only comes on Nextcloud's own theme
- **GIVEN** a token set is active
- **WHEN** the admin saves edits
- **THEN** the system saves them without asking

#### Scenario: A name already in use is refused
- **GIVEN** a custom token set named "Zuiddrecht tabellen" exists
- **WHEN** the admin saves edits as a new theme with that name
- **THEN** the system asks for another name and saves nothing
