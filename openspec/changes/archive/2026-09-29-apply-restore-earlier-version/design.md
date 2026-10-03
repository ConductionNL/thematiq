# Design: restore an earlier version

## Where it fits (development b4e7568)

- `lib/Service/ThemingAuditService.php:189` `log()` is the single write path for theming changes; it is called from every controller that changes configuration (for example `lib/Controller/SettingsController.php:234`, `lib/Controller/OverridesController.php:154`, `lib/Controller/CustomTokenSetController.php:496`, `lib/Controller/ConfigBundleController.php:153`). It stores value summaries: CSS is reduced to a 12-character hash and a size (`openspec/specs/theming-audit/spec.md:6-19`), so the audit log alone cannot restore anything.
- `lib/Service/ConfigBundleService.php:241` `export()` serialises the complete instance-wide configuration; `:309` `import(array $bundle, bool $dryRun = false)` validates every section first and writes nothing on a hard failure. Font binaries are not in the bundle (`:50-57`).
- `lib/Controller/AuditController.php:82-96` exposes `list()` and `export()`, both `#[AuthorizedAdminSetting(Admin::class)]`; routes at `appinfo/routes.php:87-88`.
- `templates/settings/admin.php:553-579` renders the audit panel table (`#nldesign-audit-table`).

## Decisions

### 1. A version is a full bundle, taken after the change

`ThemeVersionService::capture(string $auditAction)` stores `ConfigBundleService::export()` as `versions/<id>.json` in the app's app data, with `id` a sortable timestamp plus a short random suffix. It runs from `ThemingAuditService::log()` after the entry is appended, and the entry gets a `versionId`. Taking the state after each change means the version list reads like the audit log: every row is "what the theme looked like after this".

Rejected: replaying audit entries backwards. The entries hold hashes, not CSS, by design.

Rejected: a snapshot before each change only. That needs a snapshot of the current state on first use and makes every row mean "before", which is harder to read next to the audit log.

### 2. Capped like the audit log

At most 50 versions and at most 20 MB in `versions/`, oldest removed first. A capture that fails (app data not writable) is logged as a warning and never fails the change, the same contract as the audit log.

### 3. Restore goes through the bundle import

Restore reads the version and calls `ConfigBundleService::import($bundle, dryRun: true)` to show the result, then `import($bundle)` on confirmation. No second write path exists, so every validator (custom token sets, overrides whitelist, token set ids, email footer) runs on a restore exactly as on an upload. A restore creates a new version itself, so it can be undone too.

Fonts: the bundle carries font metadata only. If a version names a font that has since been deleted, the dry run lists it as "font no longer uploaded" and the restore leaves that role on the default font.

### 4. Audit vocabulary gains one action

`version_restored` with `old` the version that was active and `new` the restored version id. The `theming-audit` action vocabulary is closed, so the spec delta adds it explicitly.

### 5. Endpoints and commands

- `GET /settings/versions` (list: id, time, actor, audit action), `POST /settings/versions/{id}/preview` (dry run), `POST /settings/versions/{id}/restore`. All `#[AuthorizedAdminSetting(Admin::class)]`, CSRF-protected.
- `occ nldesign:config:versions` and `occ nldesign:config:restore <id> [--dry-run]`, actor `cli` in the audit entry.

## Risks

- App data grows. The cap bounds it; the settings hint states the cap.
- A restore of a very old version can reintroduce a custom token set that was deleted on purpose. The dry run lists every custom set it will add, so the administrator sees that before confirming.

## Out of scope

- Restoring Nextcloud core theming values (logo, colours in the core Theming app). The bundle excludes them; the theming-sync dialog remains the path to re-apply them.
- Comparing two arbitrary versions side by side.
