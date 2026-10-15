## ADDED Requirements

### Requirement: A resting floating label hides the placeholder

A theme that sets placeholder opacity SHALL keep the placeholder of a field with a floating label (`.input-field` without `--label-outside`) hidden while the field has no focus.

#### Scenario: A labelled select at rest shows only its label
- **GIVEN** the nldesign or high-contrast theme is active
- **WHEN** an NcSelect with `inputLabel` "Soort" and placeholder "Alle soorten" is at rest
- **THEN** the placeholder SHALL have opacity 0 and only "Soort" SHALL show
- **AND** on focus the placeholder SHALL show in the theme's placeholder colour

@e2e exclude A cascade-order defect; the vitest guard reads the shipped stylesheets, and the live check was made on :8080 with computed styles.
