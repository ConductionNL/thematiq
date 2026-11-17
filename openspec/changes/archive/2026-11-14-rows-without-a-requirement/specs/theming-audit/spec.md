# Spec delta: theming audit (rows-without-a-requirement)

The capability row `gov-audit-append-only` names a requirement this spec did not state.

## ADDED Requirements

### Requirement: An audit entry cannot be edited or deleted

Every audit entry MUST be written by appending one line to the audit log. Writing a later entry MUST
leave every earlier entry byte for byte as it was. The app MUST offer no API, endpoint or method that
changes or removes a single entry; the only removal is the capped rotation, which drops the oldest
generation whole.

#### Scenario: A later change leaves the earlier entry intact
@e2e exclude Storage-level guarantee; covered by PHPUnit (ThemingAuditServiceTest::testAnEntryCannotBeEditedOrDeleted)
- GIVEN an audit log holding the entry for switching to `amsterdam`
- WHEN an administrator switches to `utrecht`
- THEN the log MUST begin with the `amsterdam` entry exactly as it was written
- AND the `utrecht` entry MUST follow it on its own line
