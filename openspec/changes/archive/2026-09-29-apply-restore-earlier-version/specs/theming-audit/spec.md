# Spec delta: theming audit (theming-audit)

Audit entries point at the version they produced, and restoring a version is an audited action.

## ADDED Requirements

### Requirement: An audit entry names its version

Every audit entry written by `ThemingAuditService::log()` for which a version was stored MUST carry a `versionId` field with that version's id. An entry for which no version could be stored MUST omit the field rather than carry an empty value.

#### Scenario: The panel knows which rows can be restored

@e2e exclude needs audit entries written with and without a writable app data folder; proven by tests/Unit/Service/ThemingAuditServiceTest.php::testAnEntryNamesTheVersionItProduced and ::testAnEntryWithoutAVersionOmitsTheField

- GIVEN three audit entries, the second written while app data was not writable
- WHEN an administrator calls `GET /apps/thematiq/settings/audit?limit=3`
- THEN the first and third entries MUST carry a `versionId`
- AND the second MUST NOT carry the field

### Requirement: Restoring a version is an audited action

The closed action vocabulary MUST include `version_restored`. Its entry MUST carry `old` equal to the version id that was active before the restore and `new` equal to the restored version id, and the actor rules of every other entry (`cli` for occ, the user id otherwise).

#### Scenario: A restore from the command line is recorded

@e2e exclude occ, not a page; proven by tests/Unit/Service/ThemeVersionRestoreServiceTest.php::testARestoreImportsAndIsAudited and tests/Unit/Service/ThemingAuditServiceTest.php::testVersionRestoredIsAcceptedAction

- GIVEN an operator runs `occ nldesign:config:restore <id>`
- WHEN the restore completes
- THEN the audit log MUST contain one `version_restored` entry with actor `cli` and `new` equal to `<id>`
