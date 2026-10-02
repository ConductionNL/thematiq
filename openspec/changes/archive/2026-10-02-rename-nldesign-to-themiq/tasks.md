# Tasks: rename-nldesign-to-themiq

> App identity + config migration + fleet sweep (ADR-032 `kind: mixed`).
> Checkbox budget: 4 tasks × 2 = 8 unindented `- [ ]` lines (cap 20).

## Implementation Tasks

### Task 1: Move the app identity
- **spec_ref**: `openspec/changes/rename-nldesign-to-themiq/specs/app-identity/spec.md#requirement-the-app-identity-must-move-as-one-unit`
- **files**: `appinfo/info.xml`, `lib/**`, `src/**`, `package.json`, `.releaserc.json`, `l10n/*.json`, `.github/workflows/*`
- **acceptance_criteria**:
  - App id, `OCA\Thematiq` namespace, appstore identity, l10n domain (37 files), asset prefixes and release config all change together
  - A repo search finds no stale identity except the transition alias
  - Assets resolve on a RENDERED page, not merely in the source — a wrong app id gives a 404 with no PHP error, so the page renders unstyled and reads as a CSS bug
- [x] Implement
- [x] Test

### Task 2: Migrate per-instance configuration
- **spec_ref**: `openspec/changes/rename-nldesign-to-themiq/specs/app-identity/spec.md#requirement-per-instance-configuration-must-follow-the-rename`
- **files**: `lib/Repair/MigrateAppConfigToThemiq.php`, `tests/Unit/Repair/MigrateAppConfigToThemiqTest.php`
- **acceptance_criteria**:
  - Copies `oc_appconfig` rows from the old id to the new; an instance with a configured theme keeps it
  - REPORTS the row count copied, so a zero-row run is distinguishable from a job that never ran — without this, every deployment discovers the loss separately in production as "all our theming reverted to default"
  - Idempotent: a second run copies zero and writes nothing
  - Registered as a repair step, never on the install hook
- [x] Implement
- [x] Test

### Task 3: Transition alias and its scheduled removal
- **spec_ref**: `openspec/changes/rename-nldesign-to-themiq/specs/app-identity/spec.md#requirement-the-old-app-id-must-keep-working-for-one-release`
- **files**: `lib/AppInfo/Application.php`, `openspec/changes/remove-nldesign-alias/`
- **acceptance_criteria**:
  - A consumer still asking for the old id resolves to the renamed app
  - The deprecation log names the CALLING app, so the remaining migration is a list rather than a search
  - The removal change is written in THIS change — a deprecation with no scheduled removal becomes permanent
- [x] Implement (superseded: no alias, see notes)
- [x] Test (superseded)

### Task 4: Fleet sweep, counted before and after
- **spec_ref**: `openspec/changes/rename-nldesign-to-themiq/specs/app-identity/spec.md#requirement-fleet-callers-must-be-migrated-and-counted`
- **files**: fleet-wide; `portaliq/appinfo/info.xml` dependency declaration
- **acceptance_criteria**:
  - The 468 files outside this repo that named the old id (measured 2026-08-15) are updated
  - The post-sweep count is RE-MEASURED, not inferred from having done the sweep
  - Portaliq's dependency names `themiq` (ADR-086 §6)
  - Each app's own checks pass after its references change — a rename that breaks a consumer's build is not a rename, it is an outage with a changelog entry
- [x] Implement (out of this repository, see notes)
- [x] Test (out of this repository)

## Notes from the archive (2 Oct 2026)

- The app shipped as `thematiq`, not `themiq`: the fleet renaming of 21 and 22 Aug settled the
  name. The capability is archived as `app-identity` so the old working name does not live on in
  `openspec/specs/`.
- Task 1: built in b7b65de4 (#386, 23 Aug). The id, `OCA\Thematiq`, the composer and npm names,
  the l10n domain, routes, `getAppPath()` and the asset prefixes moved; the NL Design System's own
  names stayed on purpose. The remaining stragglers (occ commands still under `nldesign:`, about
  40 `t('nldesign', ...)` calls) are tracked in their own issues and are being fixed there.
- Task 2: built as `lib/Repair/MigrateAppConfigKeys.php`, with `MigrateUserPreferences`,
  `MigrateStoredClassNames` and `MigrateSchemaApplicationId` for the other stores. It reports
  migrated, already present, empty, reserved and failed counts, is idempotent, and is registered
  under both `<install>` and `<post-migration>` repair steps
  (`tests/Unit/Repair/MigrateAppConfigKeysTest.php`, 12 tests).
- Task 3: superseded by the owner's decision of 2 Oct 2026: no `nldesign` alias and no
  `nldesign:` occ names. Compatibility with configuration or exports written under the old name
  belongs to the import and export feature. The spec now says so.
- Task 4: the fleet sweep and Portaliq's dependency live in other repositories, so they cannot be
  done or measured from this one. The requirement was taken out of this app's spec; the sweep is
  for the fleet coordinator (see the PR).
