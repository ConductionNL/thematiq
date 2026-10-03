# Restore an earlier version of the theme

## Why

A theme change that goes wrong today can only be undone by hand. The audit log shows who changed what and when, but it stores hashes of the CSS, not the CSS itself, so nothing can be put back from it. An administrator who published a broken house style on a Friday afternoon has to remember every value, or restore a backup of the whole server.

Restoring an earlier version is in the core of what thematiq does (applying and switching a theme). Four of the five competitors offer a partial form of it: Microsoft 365 through SharePoint file versions, openDesk through a Helm rollback, Liferay through reverting a publication, Tokens Studio through undo and immutable releases.

#### Row `app-rollback-history` (thematiq matrix, area apply)

- Capability: Roll back to an earlier version of the theme from a version history.
- Own rating: no; built.state `none`. Built evidence: lib/Controller/AuditController.php only exposes list() and export() (both #[AuthorizedAdminSetting]); lib/Service/ThemingAuditService.php has log()/getRecent()/exportAll() but no restore/revert method; grep -rniF 'rollback' in lib/ only turns up comments about the app-id migration rollback, not a theme-version rollback feature
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `partial`: https://learn.microsoft.com/en-us/sharepoint/organization-assets-library ; https://learn.microsoft.com/en-us/sharepoint/brand-center-overview : brand assets live in ordinary SharePoint document libraries, which carry SharePoint's standard file version history, but the Entra sign-in and M365 org-theme settings themselves have no documented version history
- openDesk theming rated `partial`: opendesk@v1.18.2: the brand is Helm release values (theme.yaml.gotmpl), so reverting the values and redeploying (or helm rollback) restores it; opendesk-nextcloud-image@v2.17.2 functions.php:791-801 reapplies Nextcloud theming when commands change; docs/migrations.md:18 warns app-level rollbacks may need backups. Release history, not a theme history UI
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/sites/publishing-tools/publications/reverting-changes : published publications can be reverted from the History tab, covering page changes; style book entities are not in the Publications compatibility list and the editor offers only an undo History (https://liferay.atlassian.net/browse/LPD-101913), no version history of a style book
- Tokens Studio (Figma plugin and platform) rated `partial`: tokens-studio/figma-plugin@2.12.1 packages/tokens-studio-for-figma/src/app/components/NavbarUndoButton/NavbarUndoButton.tsx:31 undoes the last change; git sync branches keep history; Studio keeps immutable releases with per-release token diffs (https://documentation-v2.tokens.studio/releases/version-history.html) and the CLI can pin a source to an earlier release (`studio config set-ref`, https://documentation-v2.tokens.studio/cli/overview.html); no one-click restore of a live theme
- Rated no or unknown: Nextcloud Theming (built-in app) `no`

The 2026-03-03 theme editor change listed "undoing individual previous saves (no history)" as a limit of that change. The theming-audit-log change later added the history this change restores from, so that limit no longer describes the product.

## What changes

- After every theming configuration change, thematiq keeps a version: the complete configuration bundle at that moment, stored in app data, capped by count and size.
- The theming audit log panel gets a restore action on every entry that has a version.
- Restoring shows what will change first, then applies the version through the existing validate-everything-first bundle import, and records a `version_restored` audit entry.
- `occ nldesign:config:versions` lists versions and `occ nldesign:config:restore <version>` restores one, for operators without the web interface.

## Capabilities

### New capabilities

- `theme-versions`: keeping versions of the configuration and restoring one.

### Modified capabilities

- `theming-audit`: an audit entry names the version it produced, and the action vocabulary gains `version_restored`.

## Impact

- New `lib/Service/ThemeVersionService.php` (app data folder `versions`), called from `ThemingAuditService::log()`.
- `lib/Service/ConfigBundleService.php`: `export()` produces the snapshot, `import()` (with `dryRun`) restores it.
- `lib/Controller/AuditController.php`: version list, dry run and restore endpoints, all `#[AuthorizedAdminSetting]`.
- `templates/settings/admin.php` and `js/admin.js`: restore action in the audit panel and a confirmation dialog that shows the dry-run result.
- Two occ commands in `lib/Command/`.

## Rows

- `app-rollback-history` (thematiq matrix).
