# zuiddrecht-warning-text Delta

## ADDED Requirements

### Requirement: Zuiddrecht's warning text is its warning ink

The Zuiddrecht token set SHALL set `--nldesign-nc-color-warning-text` to its warning colour `#7A4F00`, and its dark
variant SHALL carry a generated dark value.

#### Scenario: a warning note

- **GIVEN** the Zuiddrecht set in the light theme
- **THEN** `--color-warning-text` is `#7A4F00`, not `#664700`
