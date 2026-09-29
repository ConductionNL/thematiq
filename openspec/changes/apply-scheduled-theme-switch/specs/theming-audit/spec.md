# Spec delta: theming audit (theming-audit)

Applying a planned switch is an audited action.

## ADDED Requirements

### Requirement: Applied planned switches are audited

The closed action vocabulary MUST include `scheduled_switch_applied`. Its entry MUST carry actor `system`, `old` and `new` token set ids, and the planned switch id in its context. A planned switch that could not be applied MUST be audited with `new` empty and the reason.

#### Scenario: A start and an end leave two entries

- GIVEN a planned switch with a start and an end
- WHEN both have passed and the job has run
- THEN the audit log MUST contain two `scheduled_switch_applied` entries with actor `system`, the first to the planned set and the second back
