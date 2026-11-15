# primary-buttons-not-forced Specification

## Purpose
The primary button's ground, label, hover and border come from the set's tokens through the variables Nextcloud's buttons read, already declared per variant in component-scopes.css; the forced primary rule and its hover rule go. Created by change `primary-buttons-not-forced` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: The primary button and the button padding are not forced.

## Requirements

### Requirement: The primary button and the button padding are not forced

The nldesign layer SHALL NOT force an enabled primary button's ground, label colour, border colour, weight or corner,
nor any button's padding, with `!important`; the primary tokens SHALL reach the button through the Nextcloud variables
it reads, and its label spans through `--nldesign-color-on-surface`.

#### Scenario: An app draws its own primary button

- **GIVEN** an app rule more specific than the theme's that sets its primary button's padding
- **THEN** that padding shows
