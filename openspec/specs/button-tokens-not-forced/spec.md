# button-tokens-not-forced Specification

## Purpose
An NcButton gets the button tokens through the Nextcloud variables it reads, `--font-weight-element` and `--border-radius-element`, in a zero-specificity rule, so the per-variant scopes in component-scopes.css (a primary or secondary button's own token) still decide for their variant. Created by change `button-tokens-not-forced` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: Button weight and corner are tokens, not forced values.

## Requirements

### Requirement: Button weight and corner are tokens, not forced values

The nldesign layer SHALL hand the button weight, corner and border width tokens to buttons without `!important`: to an
NcButton through `--font-weight-element` and `--border-radius-element`, and to a core plain button through a rule of
core's own specificity.

#### Scenario: A component draws its own button weight

- **GIVEN** a component rule more specific than core's that sets a button's font weight
- **THEN** that weight shows, and no theme rule forces the token's weight over it
