# nldesign-select-border-control-step Specification

## Purpose
`--nldesign-component-form-select-border-color` defaults to `--nldesign-color-border-dark` in `defaults.css`, and the Utrecht bridge falls back to it when a set gives no `--utrecht-select-border-color`, the same chain the text box uses through `--utrecht-form-input-border-color`. Created by change `nldesign-select-border-control-step` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: A select's edge is the control border.

## Requirements

### Requirement: A select's edge is the control border

The nldesign defaults SHALL give `--nldesign-component-form-select-border-color` the control border
`--nldesign-color-border-dark`, directly and as the Utrecht bridge's fallback.

#### Scenario: the board view's selects

- **GIVEN** the Zuiddrecht set, which sets no select border of its own
- **THEN** the DqWerkbord "Zaaktype:" select has the border `rgb(130, 138, 149)`, the same as a text box
