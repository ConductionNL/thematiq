# nextcloud-variable-mapping Specification (delta)

## ADDED Requirements

### Requirement: The selection wash follows the brand primary

The overrides SHALL map `--color-primary-element` to the set's primary and SHALL leave `--color-background-selection` and `--color-text-selection` to Nextcloud, which derives the selection wash from the primary element colour. Selected and highlighted text SHALL therefore show a translucent tint of the brand primary, and its text SHALL stay readable on it.

#### Scenario: A user selects text
- GIVEN a token set with a blue primary is active
- WHEN a user selects a paragraph
- THEN the selection background is a translucent tint of that blue
- AND the selected text meets the contrast the set's audit checks for the selection pair
