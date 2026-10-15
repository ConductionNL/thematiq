## ADDED Requirements

### Requirement: A resting floating label hides the placeholder

A theme that sets placeholder opacity SHALL keep the placeholder of a field with a floating label (`.input-field` without `--label-outside`, and `.textarea__input` without `--label-outside`) hidden while the field has no focus. It SHALL do so by not reaching those placeholders, so Nextcloud's own `opacity: 0` applies, and SHALL NOT need a rule that hides a placeholder with `!important`.

#### Scenario: A labelled select at rest shows only its label
- **GIVEN** the nldesign or high-contrast theme is active
- **WHEN** an NcSelect with `inputLabel` "Soort" and placeholder "Alle soorten" is at rest
- **THEN** the placeholder SHALL have opacity 0 and only "Soort" SHALL show
- **AND** on focus the placeholder SHALL show in the theme's placeholder colour

@e2e exclude A cascade-order defect; the vitest guard reads the shipped stylesheets, and the live check was made on :8080 with computed styles.

#### Scenario: A text area with its label inside shows only its label at rest
- **GIVEN** the nldesign or high-contrast theme is active
- **WHEN** an NcTextArea with label "Notitie" inside the field and a placeholder is at rest
- **THEN** the placeholder SHALL have opacity 0
- **AND** on focus the placeholder SHALL have opacity 1 in the theme's placeholder colour
- **AND** a field with its label outside, and a plain input, SHALL keep an opaque placeholder

@e2e exclude A cascade defect; the vitest guard matches the shipped opaque rule against field fixtures, and the computed placeholder opacity was measured in headless Chrome against Nextcloud's own rules.
