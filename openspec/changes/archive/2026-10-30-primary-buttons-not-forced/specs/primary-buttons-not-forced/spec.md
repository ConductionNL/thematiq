# primary-buttons-not-forced Delta

## ADDED Requirements

### Requirement: The primary button and the button padding are not forced

The nldesign layer SHALL NOT force an enabled primary button's ground, label colour, border colour, weight or corner,
nor any button's padding, with `!important`; the primary tokens SHALL reach the button through the Nextcloud variables
it reads, and its label spans through `--nldesign-color-on-surface`.

#### Scenario: An app draws its own primary button

- **GIVEN** an app rule more specific than the theme's that sets its primary button's padding
- **THEN** that padding shows
