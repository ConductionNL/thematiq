# button-tokens-not-forced Delta

## ADDED Requirements

### Requirement: Button weight and corner are tokens, not forced values

The nldesign layer SHALL hand the button weight, corner and border width tokens to buttons without `!important`: to an
NcButton through `--font-weight-element` and `--border-radius-element`, and to a core plain button through a rule of
core's own specificity.

#### Scenario: A component draws its own button weight

- **GIVEN** a component rule more specific than core's that sets a button's font weight
- **THEN** that weight shows, and no theme rule forces the token's weight over it
