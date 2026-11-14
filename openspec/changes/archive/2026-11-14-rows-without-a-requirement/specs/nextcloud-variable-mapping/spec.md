# Spec delta: Nextcloud variable mapping (rows-without-a-requirement)

The capability row `sur-selection-colour` names a requirement this spec did not state.

## ADDED Requirements

### Requirement: Selected text takes the brand colour in a readable, translucent form

The nldesign overrides MUST map `--color-primary-element` to the set's primary and MUST NOT set
`--color-background-selection`, so Nextcloud derives the selection and highlight colour from the
brand as a translucent tint that keeps the selected text readable.

#### Scenario: Selecting text under a dark brand
@e2e exclude Stylesheet contract; covered by PHPUnit (CapabilityRowsTest::testTheSelectionColourIsLeftToNextcloud)
- GIVEN the `rijkshuisstijl` set is active
- WHEN a user selects a paragraph
- THEN the selection MUST be a translucent tint of the set's primary
- AND the selected text MUST stay readable
