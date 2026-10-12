# Spec delta: dark mode (dark-action-colour)

## ADDED Requirements

### Requirement: The action colour is treated as the primary in a dark variant

When a set declares `--nldesign-color-action`, its generated dark variant MUST hold a dark value
for it and for `--nldesign-color-action-text`. When the light action text reaches 4.5:1 on the
light action colour, the action colour, its hover and its text MUST keep their light values, as the
primary does on its own pair. Otherwise the action colour MUST be derived like any other colour,
and its text MUST be repaired until it reaches 4.5:1 on it. A set that declares the action colour
and no text MUST get the text of its primary as the starting value. A set that declares no action
colour MUST get the dark variant it had.

#### Scenario: A green main button with white text
@e2e exclude Generator rule for a token no shipped set declares, covered by PHPUnit (DarkPaletteActionColourTest)
- GIVEN a set with `--nldesign-color-action: #1a7f37` and white action text
- WHEN its dark variant is generated
- THEN `--nldesign-color-action` MUST stay `#1a7f37`

#### Scenario: An action colour without a text of its own
@e2e exclude Generator rule for a token no shipped set declares, covered by PHPUnit (DarkPaletteActionColourTest)
- GIVEN a set that declares `--nldesign-color-action` and no `--nldesign-color-action-text`
- WHEN its dark variant is generated
- THEN generation MUST succeed
- AND the dark variant MUST hold an action text that reaches 4.5:1 on the dark action colour

#### Scenario: A set without an action colour
@e2e exclude Regression over every shipped set, covered by a forced regeneration and PHPUnit (ActionColourTokenTest)
- GIVEN any shipped token set
- WHEN its dark variant is regenerated
- THEN the file MUST be identical to the committed one
