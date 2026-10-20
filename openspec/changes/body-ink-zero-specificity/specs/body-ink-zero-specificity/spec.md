# body-ink-zero-specificity Delta

## ADDED Requirements

### Requirement: The body text colour is inherited, never forced

The nldesign layer SHALL set the body text colour on `body` only, with zero specificity and without `!important`, so a
component's own text colour (for example `--color-text-maxcontrast`) is drawn.

#### Scenario: Muted text in a card

- **GIVEN** the Zuiddrecht set
- **WHEN** a card draws its empty line in `--color-text-maxcontrast`
- **THEN** the line is rgb(94, 97, 104), not the main text colour
