# table-header-colour-scope Specification

## Purpose
The table header colour token is a paint on `th` (`color: var(--nldesign-component-table-header-color, inherit)`), no longer a table-wide redirect of the muted colour. Created by change `table-header-colour-scope` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: The table header colour paints header cells only.

## Requirements

### Requirement: The table header colour paints header cells only

The table header colour token SHALL colour a table's header cells and SHALL NOT redirect the muted text colour of the
table's other cells.

#### Scenario: A muted line in a table cell

- **GIVEN** a token set whose table header colour is the body ink
- **THEN** a table's header labels are drawn in the body ink
- **AND** a muted line inside a body cell keeps the set's muted colour
