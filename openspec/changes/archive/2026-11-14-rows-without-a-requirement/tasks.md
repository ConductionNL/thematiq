# Tasks: rows-without-a-requirement

## 1. Tags
- [x] 1.1 Add `@spec openspec/specs/theming-audit/spec.md#requirement-an-audit-entry-cannot-be-edited-or-deleted` to `ThemingAuditService::appendLine()` in `lib/Service/ThemingAuditService.php`
- [x] 1.2 Add `@spec openspec/specs/css-architecture/spec.md#requirement-the-theme-reaches-every-page-as-plain-css-variables` and `#requirement-the-theme-adds-no-script-to-an-ordinary-page` to `CssInjectionService::inject()` in `lib/Service/CssInjectionService.php`

  > The three requirements these tags name did not exist yet: they are added as deltas under `specs/theming-audit` and `specs/css-architecture` of this change, beside `specs/token-sets` and `specs/nextcloud-variable-mapping` for 2.3 and 2.4.

## 2. Tests that keep the rows true
- [x] 2.1 Extend `tests/Unit/Service/ThemingAuditServiceTest.php`: a second `log()` call leaves the first entry byte for byte unchanged, and the class exposes no public method that edits or deletes an entry
- [x] 2.2 Extend `tests/Unit/Service/CssInjectionServiceTest.php`: with a token set active and no opt-in page feature on, `inject()` adds stylesheets and headers only and calls no `addScript()`
- [x] 2.3 Extend the token-set manifest test in `tests/Unit/`: `token-sets.json` lists at least one set for an organisation that is not a municipality, province or VNG (for example `opencatalogi`, `demodam`)
  > No single manifest test existed: 2.3 and 2.4 live in the new `tests/Unit/CapabilityRowsTest.php`.
- [x] 2.4 Extend the overrides test for `css/systems/nldesign/overrides.css`: it maps `--color-primary-element` to the set's primary and does not set `--color-background-selection`

## 3. Approved font library (typ-org-font-library)
- [x] 3.1 `lib/Service/FontService.php` and `lib/Controller/FontController.php`: list the fonts the app bundles under `css/systems/nldesign/fonts/` beside the uploaded ones, each with its licence
- [x] 3.2 `templates/settings/admin.php` and `js/admin.js`: let the administrator assign a listed font to the body or heading role without uploading a file
- [x] 3.3 Unit test in `tests/Unit/Service/FontServiceTest.php`: assigning a bundled font to a role writes the same font token an upload would

  > `lib/Service/BundledFontLibrary.php` reads the families `css/systems/nldesign/fonts.css` declares, keeping those whose files `REUSE.toml` names under one licence. The library has its own endpoints (`GET`/`POST /settings/fonts/library`, `DELETE /settings/fonts/library/{role}`) so `list()` keeps its answer. A library font takes its role over from an uploaded font. The tests are in `tests/Unit/Service/FontServiceLibraryTest.php` (3.3: `testAssigningABundledFontWritesTheSameTokenAnUploadWould`), `BundledFontLibraryTest.php` and `tests/Unit/Controller/FontControllerLibraryTest.php`.

## 4. Matrix
- [x] 4.1 When the tasks are done, archive this change, and set `typ-org-font-library` back to `built` with its evidence

  > Archived as `archive/2026-11-14-rows-without-a-requirement`; the seven requirements are in the main specs and the rows point there.
