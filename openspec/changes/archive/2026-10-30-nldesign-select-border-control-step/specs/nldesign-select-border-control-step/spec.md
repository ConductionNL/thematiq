# nldesign-select-border-control-step Delta

## ADDED Requirements

### Requirement: A select's edge is the control border

The nldesign defaults SHALL give `--nldesign-component-form-select-border-color` the control border
`--nldesign-color-border-dark`, directly and as the Utrecht bridge's fallback.

#### Scenario: the board view's selects

- **GIVEN** the Zuiddrecht set, which sets no select border of its own
- **THEN** the DqWerkbord "Zaaktype:" select has the border `rgb(130, 138, 149)`, the same as a text box
