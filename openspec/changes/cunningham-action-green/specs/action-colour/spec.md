# Spec delta: action colour (cunningham-action-green)

## ADDED Requirements

### Requirement: The Cunningham set has a green main button

The `cunningham` token set MUST declare `--nldesign-color-action: #1f883d`,
`--nldesign-color-action-hover: #197032` and `--nldesign-color-action-text: #ffffff`. The text MUST
reach 4.5:1 on the action colour. Its primary MUST stay `#1a509f`. In its dark variant the action
colour MUST keep its value. No other shipped set MAY declare an action colour without being named
beside it in `ActionColourTokenTest`.

#### Scenario: A Cunningham instance
@e2e exclude Token values of one set, covered by PHPUnit (ActionColourTokenTest, ShellCatalogueTest) and a browser comparison recorded in the pull request
- GIVEN the active token set is Cunningham
- WHEN a page with a main button and a checked checkbox is drawn
- THEN both MUST be `#1f883d`
- AND the header and the links MUST stay in the set's blue

#### Scenario: Every other set
@e2e exclude Regression over all shipped sets, covered by PHPUnit (ActionColourTokenTest)
- GIVEN any shipped token set other than Cunningham
- WHEN its stylesheets are loaded
- THEN it MUST declare no action colour
