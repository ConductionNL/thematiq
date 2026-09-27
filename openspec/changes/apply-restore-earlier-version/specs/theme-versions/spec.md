# Spec delta: theme versions (theme-versions)

A new capability. Every theming configuration change leaves a version behind, and an administrator can put any kept version back.

## ADDED Requirements

### Requirement: Every change leaves a version

After every theming configuration change that writes an audit entry, the app MUST store the complete configuration bundle, as `ConfigBundleService::export()` produces it, as a version in the app's app data. The app MUST keep at most 50 versions and at most 20 MB of versions, removing the oldest first. A failure to store a version MUST be logged as a warning and MUST NOT fail or alter the change.

#### Scenario: Switching the token set leaves a version

- GIVEN an administrator on Settings > Administration > Theming with token set `rijkshuisstijl` active
- WHEN they switch the token set to `amsterdam`
- THEN a new version MUST exist whose bundle has `config.tokenSet` equal to `amsterdam`
- AND the audit entry for the switch MUST carry that version's id

#### Scenario: A full app data folder does not block a change

- GIVEN the app data folder cannot be written
- WHEN an administrator saves token overrides
- THEN the overrides MUST be saved
- AND the Nextcloud log MUST contain a warning that no version was kept

### Requirement: An administrator restores a version from the audit log

The theming audit log panel MUST offer a restore action on every entry that has a version. Choosing it MUST first show what the restore will change, from a dry run of the bundle import, and apply nothing until the administrator confirms. The restore MUST go through `ConfigBundleService::import()`, so every validator that guards an upload guards a restore.

#### Scenario: An administrator restores the version from before a bad change

- GIVEN an administrator who uploaded a custom token set that turned every button unreadable
- WHEN they open Settings > Administration > Theming, find the entry before the upload in the audit log and choose restore
- THEN a dialog MUST list the changes the restore will make, including the active token set going back
- AND after they confirm, the page MUST render in the restored token set
- AND the audit log MUST show a new entry with action `version_restored`

#### Scenario: Cancelling the dialog changes nothing

- GIVEN the restore dialog is open
- WHEN the administrator cancels it
- THEN the active configuration MUST be unchanged
- AND no audit entry MUST be written

#### Scenario: A version that no longer validates is refused whole

- GIVEN a version holding a custom token set that fails today's CSS validation whitelist
- WHEN the administrator restores it
- THEN the dry run MUST name the failing set
- AND confirming MUST write nothing

### Requirement: A restore can itself be undone

A restore MUST store a new version like any other change, so the configuration from before the restore stays in the list.

#### Scenario: Undoing a restore

- GIVEN an administrator restored an older version
- WHEN they open the audit log
- THEN the version from just before the restore MUST be listed with a restore action

### Requirement: Versions are admin-only

`GET /settings/versions`, `POST /settings/versions/{id}/preview` and `POST /settings/versions/{id}/restore` MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]` and MUST be CSRF-protected. An unknown version id MUST answer 404.

#### Scenario: A non-admin cannot list or restore versions

- GIVEN a signed-in user who is not an administrator and has no delegated theming setting
- WHEN they call `POST /apps/thematiq/settings/versions/<id>/restore`
- THEN the response MUST be 403
- AND the configuration MUST be unchanged

### Requirement: Operators restore from the command line

`occ nldesign:config:versions` MUST list the kept versions with id, time, actor and audit action. `occ nldesign:config:restore <id>` MUST restore one, with `--dry-run` printing the changes without writing, and the audit entry MUST carry actor `cli`.

#### Scenario: An operator restores on a server without the web interface

- GIVEN an operator with shell access to the server
- WHEN they run `occ nldesign:config:restore <id> --dry-run` and then without `--dry-run`
- THEN the first run MUST print the changes and write nothing
- AND the second run MUST apply them and exit 0

### Requirement: Fonts that are gone are reported, not invented

A version names custom fonts by metadata only. When a version names a font that is no longer uploaded, the dry run MUST list it as no longer uploaded, and the restore MUST leave that font role on the default font.

#### Scenario: Restoring a version whose heading font was deleted

- GIVEN a version whose heading font was deleted after the version was kept
- WHEN the administrator previews the restore
- THEN the dialog MUST say the heading font is no longer uploaded
- AND after the restore, headings MUST render in the default font
