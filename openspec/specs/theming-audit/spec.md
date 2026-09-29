# theming-audit Specification

## Purpose
TBD - created by archiving change theming-audit-log. Update Purpose after archive.
## Requirements
### Requirement: Append-Only Audit Entries

The app MUST record every theming configuration change as an append-only audit entry written by
a single `ThemingAuditService::log(string $action, array $context): void` API — the only write
path. Each entry MUST be one JSON object containing: `ts` (ISO 8601 UTC), `actor` (the acting
uid from `IUserSession`; `cli` for occ contexts; `system` when neither resolves), `action` (from
the closed vocabulary below), `old` and `new` value summaries, and `tokenSetVersion`. Value
summaries MUST NOT embed full CSS bodies — CSS payloads are summarized as a `sha256` 12-hex-char
content hash plus byte size. `tokenSetVersion` MUST be the `token-sets.json` manifest `version`
field when present, otherwise the app's `installed_version` app value (which pins shipped set
content); entries about `custom-*` sets MUST additionally carry the set's content hash. The
service MUST expose no update or delete API; rotation (below) is the only removal mechanism.
Audit write failures MUST be caught, logged as warnings, and MUST NEVER fail or alter the
outcome of the operation being audited.

Action vocabulary (closed; extending it requires a spec change): `token_set_changed`,
`toggle_changed`, `overrides_written`, `overrides_imported`, `app_exclusions_changed`,
`custom_set_uploaded`, `custom_set_deleted`, `theming_sync_applied`, `config_imported`
(reserved for change `theme-config-portability`), `preview_published` (reserved for change
`theme-preview-workflow`).

@e2e exclude audit-record content — what an entry records (actor, old and new value, append-only ordering) is state written behind the request; a browser sees the panel table, not whether the underlying entry is append-only; proven by tests/Unit/Service/ThemingAuditServiceTest.php.

#### Scenario: Token set change is recorded with old and new values

- GIVEN the active token set is `rijkshuisstijl` and admin `ruben` changes it to `amsterdam`
- WHEN `SettingsController::setTokenSet()` completes the write
- THEN exactly one entry MUST be appended with `actor: "ruben"`,
  `action: "token_set_changed"`, `old: "rijkshuisstijl"`, `new: "amsterdam"`, an ISO 8601 UTC
  `ts`, and a `tokenSetVersion`

#### Scenario: An auditor can answer "which version was live when"

- GIVEN the audit file contains a `token_set_changed` entry
- WHEN the entry is read
- THEN its `tokenSetVersion` MUST identify the shipped content (manifest version, else app
  `installed_version`), so the entry pins WHICH revision of the set went live at `ts`

#### Scenario: CSS payloads are hashed, not embedded

- GIVEN an admin imports a 40 KB overrides CSS file
- WHEN the `overrides_imported` entry is written
- THEN the entry MUST contain a `sha256:` 12-hex-char hash and the byte size, not the CSS text

#### Scenario: Unknown action is refused

- GIVEN a caller passes an action outside the vocabulary
- WHEN `log()` runs
- THEN no entry MUST be appended and a warning MUST be logged

#### Scenario: Audit failure never breaks the audited operation

- GIVEN appdata is unwritable and `log()` throws internally
- WHEN an admin changes the token set
- THEN the token set change MUST still succeed with its normal response
- AND a warning MUST be logged about the failed audit write

### Requirement: JSONL Appdata Storage With Capped Rotation

Audit entries MUST be stored as JSON Lines in the app's appdata (via `IAppDataFactory`), folder
`audit`, file `audit.jsonl` — NOT in IConfig and NOT in database tables. Rationale (normative
for future maintainers): IConfig values are single mutable rows, giving no append semantics and
allowing silent truncation — the opposite of append-only — while the app's architecture forbids
DB tables; appdata lives outside the webroot, is not directly HTTP-reachable, and streams as a
download. When `audit.jsonl` exceeds 1 MB after an append, it MUST be rotated to `audit.jsonl.1`
(replacing any previous generation) and a fresh file started; exactly one rotated generation is
kept, bounding total storage at ~2 MB. Read APIs `getRecent(int $limit)` (newest first) and
`exportAll()` (rotated generation first, oldest first overall) MUST span both files.

@e2e exclude appdata storage mechanics — JSONL line format, size-capped rotation and cross-generation ordering are properties of files in appdata; no browser can open them; proven by tests/Unit/Service/ThemingAuditServiceTest.php.

#### Scenario: Entries are appended as parseable JSON lines

- GIVEN three theming changes occur
- WHEN `audit.jsonl` is read
- THEN it MUST contain three lines, each independently parseable as a JSON object, in write
  order

#### Scenario: Rotation at the size cap keeps one generation

- GIVEN `audit.jsonl` exceeds 1 MB after an append
- WHEN the rotation runs
- THEN the full file MUST become `audit.jsonl.1`, replacing any older generation
- AND subsequent entries MUST start a fresh `audit.jsonl`
- AND `exportAll()` MUST still return the rotated entries before the current ones

#### Scenario: Log file is not directly reachable over HTTP

- GIVEN the audit file exists under `appdata_*/nldesign/audit/`
- WHEN any unauthenticated HTTP path is tried against it
- THEN the file MUST NOT be served (appdata is outside the web-served roots; access is only via
  the admin export endpoint)

### Requirement: Complete Call-Site Coverage

Every state-changing theming operation in the app MUST call `ThemingAuditService::log()` exactly
once, after its write succeeds and never on a validation-failure path. The complete set of call
sites as of this spec: `SettingsController::setTokenSet()` (`token_set_changed`),
`SettingsController::setSloganSetting()` and `setMenuLabelsSetting()` (`toggle_changed`, with
the config key in context), `SettingsController::updateThemingValues()`
(`theming_sync_applied`), `SettingsController::setAppTheming()` (`app_exclusions_changed`),
`OverridesController::setOverrides()` (`overrides_written`),
`OverridesController::importOverrides()` (`overrides_imported`),
`CustomTokenSetController::upload()` (`custom_set_uploaded`), and
`CustomTokenSetController::delete()` (`custom_set_deleted`). Any FUTURE endpoint or service that
mutates theming configuration MUST add its call site and, if needed, a vocabulary entry in the
same change — an unaudited theming write is a spec violation.

@e2e exclude call-site coverage — the assertion is that EVERY theming write path emits an entry and that rejected input emits none; that is enumerated per controller, not walked in a browser; proven by tests/Unit/Controller/SettingsControllerAuditTest.php, OverridesControllerAuditTest.php, CustomTokenSetControllerAuditTest.php and CustomCssControllerAuditTest.php.

#### Scenario: Rejected input produces no entry

- GIVEN `setTokenSet()` is called with an invalid id and returns 400
- WHEN the request completes
- THEN no audit entry MUST have been written

#### Scenario: Custom set deletion records the side effect

- GIVEN the active token set is `custom-gemeente-x` and the admin deletes that set
- WHEN the `custom_set_deleted` entry is written
- THEN it MUST record the deleted id AND that the active set was reset to `nextcloud`

#### Scenario: Reserved cross-change actions

- GIVEN changes `theme-config-portability` and/or `theme-preview-workflow` are merged
- WHEN a config bundle import applies or a preview is published
- THEN those operations MUST log `config_imported` / `preview_published` respectively (wired by
  whichever change lands second; the action names are reserved here either way)

### Requirement: Admin Audit Endpoints

The app MUST expose the trail to admins only, via a dedicated controller with
`#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]` on every method:
`GET /settings/audit?limit=N` MUST return the most recent entries as JSON (default 20, hard cap
200, newest first) and `GET /settings/audit/export` MUST stream the FULL log (rotated generation
included, oldest first) as Content-Type `application/x-ndjson` with Content-Disposition
`attachment; filename="nldesign-audit.jsonl"`.

@e2e exclude audit endpoint responses — pagination shape and the export's Content-Disposition are network-layer facts; proven by tests/Unit/Controller/AuditControllerTest.php. The panel table and its download button are covered by the admin-settings spec.

#### Scenario: Recent entries for the panel table

- GIVEN 30 entries exist
- WHEN `GET /settings/audit?limit=20` is called by an admin
- THEN the 20 newest entries MUST be returned, newest first

#### Scenario: Full export includes rotated entries

- GIVEN a rotation has occurred
- WHEN the admin downloads the full log
- THEN the response MUST contain the rotated generation's entries followed by the current
  file's, every line valid JSON

#### Scenario: Non-admin access is rejected

- GIVEN an unauthenticated caller or a non-admin user
- WHEN either audit endpoint is called
- THEN the request MUST be rejected with no audit content in the response

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
