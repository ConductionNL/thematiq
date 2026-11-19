# table-header-colour-scope Delta

## ADDED Requirements

### Requirement: The table header colour paints header cells only

The table header colour token SHALL colour a table's header cells and SHALL NOT redirect the muted text colour of the
table's other cells.

#### Scenario: A muted line in a table cell

- **GIVEN** a token set whose table header colour is the body ink
- **THEN** a table's header labels are drawn in the body ink
- **AND** a muted line inside a body cell keeps the set's muted colour
