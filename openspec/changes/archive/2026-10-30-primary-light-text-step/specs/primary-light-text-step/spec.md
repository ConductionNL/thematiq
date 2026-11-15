# primary-light-text-step Delta

## ADDED Requirements

### Requirement: Text on the light primary reaches 4.5:1

Under the nldesign system, text on the light primary band and its hover (`--color-primary-element-light-text`,
`--color-primary-light-text`) SHALL be `--nldesign-color-primary-light-text`, at least 4.5:1 against
`--nldesign-color-primary-light` and `--nldesign-color-primary-light-hover`. A set whose primary reaches that SHALL keep
the primary; otherwise the step SHALL be a deeper shade of the primary's hue.

#### Scenario: The board's save-view button under Zuiddrecht

- **GIVEN** the Zuiddrecht set and the board look
- **THEN** "Weergave opslaan" is #234A78 on #DCE6F2, 7.17:1

#### Scenario: A set whose primary already passes

- **GIVEN** a set whose primary is at least 4.5:1 on its light primary and its hover
- **THEN** the set declares no light-text step and the text is the primary
