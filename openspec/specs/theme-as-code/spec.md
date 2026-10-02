# theme-as-code Specification

## Purpose
The house style is a branding package that can live in Git and is applied from deployment configuration. Created by archiving change governance-theme-as-code.

## Requirements
### Requirement: A branding package holds the whole house style

A branding package MUST be a directory, or a ZIP of one, holding `bundle.json` in the existing bundle format, `fonts/<id>.woff2` for every font in the bundle, optional `tokens/<id>.json` DTCG sources, and an optional `REVISION` file. `occ thematiq:config:export --package <dir>` MUST write a package of the running configuration, fonts included. `occ thematiq:config:import` MUST accept a package directory or ZIP as well as a bare bundle file.

#### Scenario: An operator moves the house style with its fonts

@e2e exclude occ on two servers, not a page; proven by tests/Unit/Service/BrandingPackageServiceTest.php::testPackageRoundTripsWithTwoFontsAndOneDtcgSource

- GIVEN a test server with a custom heading font and a custom token set
- WHEN an operator runs `occ thematiq:config:export --package /srv/branding` there and `occ thematiq:config:import /srv/branding` on production
- THEN production MUST render headings in the custom font
- AND no administrator MUST re-upload the font

#### Scenario: A DTCG source in the package is converted on apply

@e2e exclude occ, not a page; proven by tests/Unit/Service/BrandingPackageServiceTest.php::testPackageRoundTripsWithTwoFontsAndOneDtcgSource

- GIVEN a package whose `tokens/custom-gemeente.json` holds a DTCG document
- WHEN the package is imported
- THEN the custom set `custom-gemeente` MUST hold the converted values from that document

### Requirement: The server applies the package named in config.php

When `thematiq.config_source` in `config.php` names a package path, the app MUST apply the package whenever its content hash differs from the last applied hash: after every upgrade, from a background job that runs at least every five minutes, and when an operator runs `occ thematiq:config:apply`. The app MUST NOT fetch the package from a network location; the deployment puts it on disk.

#### Scenario: A merged pull request reaches production

@e2e exclude needs config.php and a background job run; proven by tests/Unit/Service/ConfigSourceServiceTest.php::testChangedValidPackageIsAppliedAndAudited

- GIVEN production with `thematiq.config_source` pointing at a volume that Argo CD syncs from a Git repository
- WHEN a pull request changing the primary colour is merged and the volume is updated
- THEN within ten minutes a user who opens the dashboard MUST see the new primary colour
- AND the audit log MUST contain a `config_imported` entry with actor `system` and the commit from `REVISION`

#### Scenario: An unchanged package is not applied again

@e2e exclude background job, not a page; proven by tests/Unit/Service/ConfigSourceServiceTest.php::testUnchangedPackageIsNotAppliedAgain

- GIVEN a package that was applied and has not changed
- WHEN the background job runs
- THEN the app MUST NOT write any configuration or audit entry

### Requirement: A failing package changes nothing

A package that fails validation MUST change nothing. The app MUST keep the running configuration, log an error once per distinct package hash, and show the error listing on Settings > Administration > Theming. `occ thematiq:config:apply` MUST exit non-zero on a failing package.

#### Scenario: A typo in the package does not break production

@e2e exclude needs config.php and a background job run; proven by tests/Unit/Service/ConfigSourceServiceTest.php::testChangedInvalidPackageChangesNothingAndLogsOnce and tests/vitest/admin-config-source.spec.js

- GIVEN a running configuration and a new package whose custom token set fails the CSS validation whitelist
- WHEN the background job runs
- THEN users MUST keep seeing the running house style
- AND an administrator on Settings > Administration > Theming MUST see the validation error naming the set

### Requirement: The settings page shows who manages the house style

While `thematiq.config_source` is set, the configuration bundle block MUST show the source path, the last applied revision and time, the last error if any, and whether the running configuration differs from the package. When `thematiq.config_source_lock` is true, every configuration setter MUST answer 423 naming the source, and the settings page MUST disable its configuration controls.

#### Scenario: An administrator sees the house style is managed from Git

@e2e exclude needs thematiq.config_source in config.php of the shared CI instance; proven by tests/vitest/admin-config-source.spec.js and tests/Unit/Service/ConfigSourceServiceTest.php::testStatusShowsRevisionAndDrift

- GIVEN a server with `thematiq.config_source` set and an applied package with revision `3f2a9c1`
- WHEN an administrator opens Settings > Administration > Theming
- THEN the configuration bundle block MUST state that the house style is managed from deployment configuration, with the path and revision `3f2a9c1`

#### Scenario: A web change shows as drift

@e2e exclude needs thematiq.config_source in config.php of the shared CI instance; proven by tests/Unit/Service/ConfigSourceServiceTest.php::testStatusShowsRevisionAndDrift and tests/vitest/admin-config-source.spec.js

- GIVEN a managed server without the lock
- WHEN an administrator changes the token set on the settings page
- THEN the block MUST show that the running configuration differs from the package and will be replaced on the next apply

#### Scenario: The lock refuses web changes

@e2e exclude needs thematiq.config_source_lock in config.php of the shared CI instance; proven by tests/Unit/Middleware/ConfigSourceLockMiddlewareTest.php::testSettersAnswer423WhileLocked

- GIVEN a managed server with `thematiq.config_source_lock` true
- WHEN an administrator calls `POST /apps/thematiq/settings/tokenset`
- THEN the response MUST be 423 with a message naming the source
- AND the active token set MUST be unchanged
