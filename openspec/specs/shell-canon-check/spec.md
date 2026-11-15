# shell-canon-check Specification

## Purpose
The shell measured against the canon. Created by change `shell-canon-check` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: The shell is measured against the canon.

## Requirements

### Requirement: The shell is measured against the canon

A check MUST measure, on the Files page at 1440 x 1000 with the Zuiddrecht set, the top bar height,
the navigation width, a navigation item's height, the primary button's height, the table header
row's height, a table row's height and the body text size, and compare each with the canon of the
OpenProject plugin. A part that differs MUST be recorded with the size thematiq draws, and the check
MUST fail when a part leaves the canon, when a recorded size changes, or when a recorded part reaches
the canon.

#### Scenario: Files with the Zuiddrecht set
@e2e tests/e2e/spec-coverage/shell-canon.spec.ts
- GIVEN the Zuiddrecht set
- WHEN Files is opened at 1440 x 1000
- THEN the top bar MUST be 68 px high and the navigation 264 px wide
- AND every other part MUST match the canon or its recorded size
