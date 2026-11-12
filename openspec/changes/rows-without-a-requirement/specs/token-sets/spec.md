# token-sets Specification (delta)

## ADDED Requirements

### Requirement: Organisations that are not a municipality have ready-made sets

The shipped catalogue in `token-sets.json` SHALL include sets for organisations that are not a municipality, a province or VNG, such as a public agency, a supplier or a community project. Each SHALL be selectable in the token set dropdown in the same way as a municipal set.

#### Scenario: An administrator picks a non-municipal set
- GIVEN the catalogue ships the `opencatalogi` set
- WHEN the administrator opens the token set dropdown on the Theming page
- THEN `opencatalogi` is listed beside the municipal sets
- AND selecting it applies its tokens to every page
