# zuiddrecht-warning-text Specification

## Purpose
The Zuiddrecht set sets `--nldesign-nc-color-warning-text: #7A4F00`. Created by change `zuiddrecht-warning-text` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: Zuiddrecht's warning text is its warning ink.

## Requirements

### Requirement: Zuiddrecht's warning text is its warning ink

The Zuiddrecht token set SHALL set `--nldesign-nc-color-warning-text` to its warning colour `#7A4F00`, and its dark
variant SHALL carry a generated dark value.

#### Scenario: a warning note

- **GIVEN** the Zuiddrecht set in the light theme
- **THEN** `--color-warning-text` is `#7A4F00`, not `#664700`
