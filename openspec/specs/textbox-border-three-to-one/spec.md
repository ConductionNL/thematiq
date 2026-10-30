# textbox-border-three-to-one Specification

## Purpose
The nldesign defaults and the utrecht bridge give `--nldesign-component-textbox-border-color` the control step, `--nldesign-color-border-dark`, in every set that does not declare its own field border. Created by change `textbox-border-three-to-one` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: A text field's edge is 3:1 against the field.

## Requirements

### Requirement: A text field's edge is 3:1 against the field

Under the nldesign system a text field's border SHALL be the set's own field border when that is at least 3:1 against
the field, and otherwise the control step (`--nldesign-color-border-dark`), which SHALL itself be at least 3:1 against
the page (WCAG 1.4.11).

#### Scenario: A set without a field border of its own

- **GIVEN** the Zuiddrecht set
- **THEN** a text field's border is #828A95, 3.49:1 against white

#### Scenario: A set whose own field border is too light

- **GIVEN** the buren set, whose upstream field border is #ced4da (1.49:1)
- **THEN** a text field's border is #8796a4, 3.03:1 against white
