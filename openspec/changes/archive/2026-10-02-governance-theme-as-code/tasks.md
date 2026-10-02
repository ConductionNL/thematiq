# Tasks: theme as code

Tick a box when the work is merged to `development`.

## 1. Branding package

- [x] 1.1 Package reader and writer in `ConfigBundleService` (directory and ZIP, `bundle.json`, `fonts/`, `tokens/`, `REVISION`). Verify: `tests/Unit/Service/ConfigBundlePackageTest.php` round-trips a package with two fonts and one DTCG source.
- [x] 1.2 Apply fonts from a package through `FontService` and its validator; a manifest entry without a file is a hard error. Verify: unit tests for both cases, and that a bare bundle still ignores font metadata.
- [x] 1.3 Convert `tokens/<id>.json` through `TokenSetConverterService` before validation. Verify: unit test with a DTCG fixture.
- [x] 1.4 `occ nldesign:config:export --package <dir>` and `occ nldesign:config:import <dir-or-zip>`. Verify: command tests.

## 2. Declarative mode

- [x] 2.1 `lib/Service/ConfigSourceService.php`: hash, apply-if-changed, error record, lock `thematiq-config-source`. Verify: unit tests for unchanged, changed and valid, changed and invalid, and a missing path.
- [x] 2.2 Post-migration repair step, a 5-minute background job, and `occ thematiq:config:apply`. Verify: command test exits non-zero on an invalid package.
- [x] 2.3 Audit `config_imported` with `source: deployment` and the revision. Verify: unit test.

## 3. Settings page

- [x] 3.1 Managed-by notice, last revision, last error and drift in the configuration bundle block. Verify: Playwright scenario "An administrator sees the house style is managed from Git".
- [x] 3.2 Optional lock: setters answer 423 and the controls are disabled. Verify: controller tests for two setters and a Playwright check that controls are disabled.

## 4. Quality

- [x] 4.1 Docs: a Helm values example with a ConfigMap, an Argo CD example with a Git source, and the Git repository layout. Verify: the docs build.
- [x] 4.2 l10n en and nl. Verify: `npm run test:l10n`.
- [x] 4.3 Apply a package holding an incomplete token set and a dark-mode set. Verify: manual check recorded in the PR.

## Notes at archive (2 Oct 2026)

- 1.1 The reader and writer live in `lib/Service/BrandingPackageReader.php` and the import and export in `lib/Service/BrandingPackageService.php`, around `ConfigBundleService` rather than inside it, so the bundle service gains no complexity. Verified by `tests/Unit/Service/BrandingPackageServiceTest.php::testPackageRoundTripsWithTwoFontsAndOneDtcgSource` (directory and a ZIP with a top-level folder, two fonts, one DTCG source).
- 1.2 Fonts in the package are added, or replace a font with the same id; fonts the server has that the package does not name stay. A missing file, a file that is not woff2, and a font id that does not match its name are hard errors (`testMissingFontFileRefusesThePackageWhole`, `testFontFileThatIsNotWoff2IsRefused`); a bare bundle is not a package (`testBareBundleFileIsNotAPackage`), so its fonts stay informational.
- 1.4 The new command is `occ thematiq:config:apply` (the app's new command prefix). The existing export and import keep their names in this change; lane A renames them.
- 2.1 Drift compares the running configuration with a fingerprint stored right after the last successful apply, not with the package text, so a re-serialised custom set does not show as drift. `tests/Unit/Service/ConfigSourceServiceTest.php` (8 tests).
- 2.2 `lib/Repair/ApplyConfigSource.php` (post-migration, last), `lib/BackgroundJob/ConfigSourceJob.php` (300 s), `lib/Command/ConfigApply.php`. `tests/Unit/Middleware/ConfigSourceLockMiddlewareTest.php::testApplyCommandFailsOnAnInvalidPackage`.
- 2.3 `ConfigSourceServiceTest::testChangedValidPackageIsAppliedAndAudited`.
- 3.1 `js/admin-config-source.js` with `tests/vitest/admin-config-source.spec.js` (jsdom) instead of Playwright; a browser run is owed (live check in the PR body).
- 3.2 The lock is `lib/Middleware/ConfigSourceLockMiddleware.php`: every non-GET action on a thematiq controller answers 423, except the session-only ones (preview start and discard, restore preview, notice dismissal, contrast evaluation). Two setters proven in `testSettersAnswer423WhileLocked`; disabled controls in the vitest file.
- 4.1 `docs/features/theme-as-code.md`.
- 4.3 Owed: apply a package with an incomplete token set and a dark-mode set on a live server (recipe in the PR body).
