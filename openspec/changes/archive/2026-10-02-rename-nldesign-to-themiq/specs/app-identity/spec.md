# app-identity Delta: rename-nldesign-to-themiq

**Status**: built (archived 2 Oct 2026)
**Scope**: nldesign → thematiq (written as `themiq`; the app shipped as `thematiq`, because the
fleet settled on that name on 21 and 22 Aug 2026)
**OpenSpec changes**:

- [rename-nldesign-to-themiq](../../)

## Purpose

Renames the app id from `nldesign` to `thematiq`, carrying the app id, namespace, appstore
identity, l10n domain and asset prefixes together, and migrating per-instance configuration so a
renamed app is not a differently named app with none of its settings. The NL Design System's own
names (the `--nldesign-*` tokens, class prefixes and the `nldesign` design-system id) are the
external standard and do not move. Related: ADR-086 §6.

## ADDED Requirements

### Requirement: The app identity MUST move as one unit

The app id, the PHP namespace, the appstore identity, the l10n domain, the asset prefixes and the
release configuration SHALL change in one change. The repository SHALL name the old app id only
where it means the old id: the repair steps that migrate data stored under it. Names that belong
to the NL Design System stay.

#### Scenario: No stale identity remains

@e2e exclude a repository property, not page behaviour; the move landed in b7b65de4 (#386) across 111 files, and the remaining occ command and l10n domain stragglers are their own issues

- **WHEN** the repository is searched for the old app id and namespace
- **THEN** the only matches are the repair steps that migrate data stored under the old id, and
  names that belong to the NL Design System

#### Scenario: Assets resolve under the new id

@e2e exclude every spec-coverage run loads the admin page under /apps/thematiq/ and fails on unstyled or missing assets; this change adds no page of its own

- **GIVEN** a page that loads this app's scripts and styles
- **WHEN** it renders
- **THEN** every asset resolves

### Requirement: Per-instance configuration MUST follow the rename

A repair step SHALL copy the app's `oc_appconfig` rows from the old id to the new one, report how
many it moved, and be idempotent. It SHALL be registered as a repair step, never on the install
hook alone.

#### Scenario: An instance keeps its theme after the rename

@e2e exclude a repair step, not a page; covered by MigrateAppConfigKeysTest::testCopiesStoredValuesToTheNewNamespace

- **GIVEN** an instance with a configured theme under the old id
- **WHEN** the renamed app is enabled
- **THEN** the same theme is active

#### Scenario: The migration reports what it moved

@e2e exclude a repair step, not a page; covered by MigrateAppConfigKeysTest::testReportsNothingToDoOnAFreshInstall and covered by MigrateAppConfigKeysTest::testCopiesStoredValuesToTheNewNamespace

- **WHEN** the repair step runs
- **THEN** it reports the number of configuration rows copied
- **AND** a run that copies none is distinguishable from a run that did not execute

#### Scenario: Re-running changes nothing

@e2e exclude a repair step, not a page; covered by MigrateAppConfigKeysTest::testIsIdempotent

- **GIVEN** the repair step has completed
- **WHEN** it runs again
- **THEN** it copies nothing and overwrites nothing

### Requirement: The old app id is not aliased

The app SHALL NOT register an alias, a second app id or `nldesign:` occ command names for the old
id. Reading configuration or exports written under the old name is the job of the import and
export feature, not of a runtime alias (decided 2 Oct 2026).

#### Scenario: Nothing answers to the old id

@e2e exclude a registration property; the occ command names are checked by the issue that renames them to thematiq:*

- **GIVEN** the renamed app is installed
- **WHEN** the registered app ids and occ commands are listed
- **THEN** none of them is `nldesign` or starts with `nldesign:`
