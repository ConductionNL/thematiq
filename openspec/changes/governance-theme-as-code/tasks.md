# Tasks: theme as code

Tick a box when the work is merged to `development`.

## 1. Branding package

- [ ] 1.1 Package reader and writer in `ConfigBundleService` (directory and ZIP, `bundle.json`, `fonts/`, `tokens/`, `REVISION`). Verify: `tests/Unit/Service/ConfigBundlePackageTest.php` round-trips a package with two fonts and one DTCG source.
- [ ] 1.2 Apply fonts from a package through `FontService` and its validator; a manifest entry without a file is a hard error. Verify: unit tests for both cases, and that a bare bundle still ignores font metadata.
- [ ] 1.3 Convert `tokens/<id>.json` through `TokenSetConverterService` before validation. Verify: unit test with a DTCG fixture.
- [ ] 1.4 `occ nldesign:config:export --package <dir>` and `occ nldesign:config:import <dir-or-zip>`. Verify: command tests.

## 2. Declarative mode

- [ ] 2.1 `lib/Service/ConfigSourceService.php`: hash, apply-if-changed, error record, lock `thematiq-config-source`. Verify: unit tests for unchanged, changed and valid, changed and invalid, and a missing path.
- [ ] 2.2 Post-migration repair step, a 5-minute background job, and `occ nldesign:config:apply`. Verify: command test exits non-zero on an invalid package.
- [ ] 2.3 Audit `config_imported` with `source: deployment` and the revision. Verify: unit test.

## 3. Settings page

- [ ] 3.1 Managed-by notice, last revision, last error and drift in the configuration bundle block. Verify: Playwright scenario "An administrator sees the house style is managed from Git".
- [ ] 3.2 Optional lock: setters answer 423 and the controls are disabled. Verify: controller tests for two setters and a Playwright check that controls are disabled.

## 4. Quality

- [ ] 4.1 Docs: a Helm values example with a ConfigMap, an Argo CD example with a Git source, and the Git repository layout. Verify: the docs build.
- [ ] 4.2 l10n en and nl. Verify: `npm run test:l10n`.
- [ ] 4.3 Apply a package holding an incomplete token set and a dark-mode set. Verify: manual check recorded in the PR.
