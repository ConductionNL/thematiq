# theming-audit Specification (delta)

## ADDED Requirements

### Requirement: An audit entry cannot be edited or deleted

Once written, an audit entry SHALL stay as it is. No route and no service method SHALL edit or delete an existing entry. The only way an entry leaves the log SHALL be rotation when the log reaches its size cap.

#### Scenario: A later change is logged
- GIVEN the audit log holds an entry for a token set switch
- WHEN the administrator makes another theming change
- THEN the earlier entry is unchanged, byte for byte
- AND the new entry is added after it

#### Scenario: Someone looks for an edit path
- GIVEN the app's routes and the audit service
- WHEN they are listed
- THEN the audit routes are read-only (list, export, versions) or restore a version as a new audited action
- AND no route or method updates or deletes an entry
