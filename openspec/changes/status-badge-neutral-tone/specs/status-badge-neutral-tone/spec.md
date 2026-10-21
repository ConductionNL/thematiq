# status-badge-neutral-tone Delta

## ADDED Requirements

### Requirement: A neutral status pill takes the set's neutral pill tokens

The status-badge scope SHALL draw a pill's default tone from `--nldesign-component-status-badge-neutral-background-color`
and `--nldesign-component-status-badge-neutral-color`, falling back to Nextcloud's own when a set declares neither. In the
Zuiddrecht-type sets the pair SHALL be the boards' grey tint and pill text, at least 4.5:1 in the light and in both dark
scopes.

#### Scenario: A neutral priority pill on the Taken board

- **GIVEN** the Zuiddrecht set
- **WHEN** a `.cn-status-badge--default` pill renders
- **THEN** its fill is #F0F1F3 and its text #3D4047
