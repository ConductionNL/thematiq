# error-text-ink Specification

## Purpose
`--color-text-error` reads a set's `--nldesign-color-error-text` when it declares one, and its error colour otherwise, so every other set renders as before. Created by change `error-text-ink` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: Error text reads a set's error ink.

## Requirements

### Requirement: Error text reads a set's error ink

`--color-text-error` SHALL read `--nldesign-color-error-text` when the active token set declares it, and
`--nldesign-color-error` otherwise. The Zuiddrecht set SHALL declare `#A30000`, and its dark variant a generated value.

#### Scenario: a late deadline on DqDagAfsluiten

- **GIVEN** the Zuiddrecht set
- **THEN** "1 dag te laat" is drawn in `#A30000`, and the error buttons and badges keep `#CC0000`
