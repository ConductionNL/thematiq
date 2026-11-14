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
- [ ] 3.1 `lib/Service/FontService.php` and `lib/Controller/FontController.php`: list the fonts the app bundles under `css/systems/nldesign/fonts/` beside the uploaded ones, each with its licence
- [ ] 3.2 `templates/settings/admin.php` and `js/admin.js`: let the administrator assign a listed font to the body or heading role without uploading a file
- [ ] 3.3 Unit test in `tests/Unit/Service/FontServiceTest.php`: assigning a bundled font to a role writes the same font token an upload would

## 4. Matrix
- [ ] 4.1 When the tasks are done, archive this change, and set `typ-org-font-library` back to `built` with its evidence
