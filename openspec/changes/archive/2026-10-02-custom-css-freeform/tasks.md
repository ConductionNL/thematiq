Note: no OpenRegister schemas are involved in this change (a flat CSS file plus one `IConfig`
appconfig flag, exactly like `custom-css-overrides`) — there is no Seed Data section and no seed
task. There is no lifecycle/aggregation/notification behaviour either, so the ADR-031
declarative-vs-imperative notification-dialect distinction does not apply.

## 1. Sanitisation and File I/O Services

- [x] 1.1 Create `lib/Service/CustomCssValidator.php` (SPDX docblock, `@spec` tags referencing
      `specs/custom-css-freeform/spec.md`'s "Freeform CSS Is Sanitised Before Persisting" and
      "Reserved Dark-Mode Variables Cannot Be Set By Freeform CSS" requirements). One method per
      rule (size cap 64 KB, `@import`/`@charset`, external `url()` vs. same-origin/relative/`data:`,
      `expression(`/`behavior:`/`-moz-binding:`, `</style`/`<script`, unbalanced braces after
      comment/string stripping, reserved REQ-CSS-007 variable names matched anywhere in the
      document). A `validate(string $css): array` entry point returns the list of failed rule
      messages (empty array = valid); fail-closed, no partial acceptance.
- [x] 1.2 Create `lib/Service/CustomCssService.php` (SPDX docblock, `@spec` tags), mirroring
      `CustomOverridesService`'s file-handling shape against `css/custom-css.css`:
      `ensureExists()` (header-comment-only file), `read()`, `getRawContent()`, and
      `write(string $css): void` which calls `CustomCssValidator::validate()` first and throws a
      typed exception (validation errors) or `RuntimeException` (I/O failure) before any atomic
      temp-file + rename write occurs.

## 2. CSS Injection Wiring

- [x] 2.1 In `lib/Service/CssInjectionService.php`, add a new step immediately after the existing
      `// 4. Custom overrides` block (around line 245-246): when
      `IConfig::getAppValue('nldesign', 'custom_css_enabled', '0') === '1'`, call
      `CustomCssService::ensureExists()` then `emitStyle(file: 'custom-css')`; otherwise no-op.
      Constructor-inject `CustomCssService`.
- [x] 2.2 Bump `appinfo/info.xml` `<version>` (new stylesheet in the cascade — bust the `?v=`
      cache-buster) and add a CHANGELOG.md "Unreleased" entry.

## 3. Controller and Routes

- [x] 3.1 Create `lib/Controller/CustomCssController.php` (SPDX docblock, `@spec` tags) with
      `getCustomCss()` (GET: returns raw content + `custom_css_enabled` state) and
      `setCustomCss()` (POST: validates via `CustomCssService::write()`, returns 200 on success,
      422 with the failed-rule list on validation failure, 500 on I/O failure) and a third method
      to toggle `custom_css_enabled`. Every method carries
      `#[AuthorizedAdminSetting(Admin::class)]`. Every successful write (content or flag) calls
      `ThemingAuditService::log()` with action names `custom_css_written` /
      `custom_css_enabled_changed` before returning.
- [x] 3.2 Register the new routes in `appinfo/routes.php` following the existing
      `nldesign.overrides.*` naming convention (e.g. `nldesign.customCss.get` /
      `.set` / `.setEnabled`).

## 4. Admin Settings UI

- [x] 4.1 Extend `templates/settings/admin.php` with a "Custom CSS" section: an enable/disable
      toggle, a `<textarea>` bound to the current content, a Save button, and inline display of
      the validation error list returned by a 422 response — vanilla JS `fetch()` calls matching
      the existing overrides section's pattern (no Vue).

## 5. Backend Tests

- [x] 5.1 `tests/Unit/Service/CustomCssValidatorTest.php`: one test per rule in task 1.1 (reject
      case + a passing boundary case per rule), plus a fully valid multi-selector/multi-`@media`
      CSS string that must pass all rules together.
- [x] 5.2 `tests/Unit/Service/CustomCssServiceTest.php`: atomic write (temp file + rename),
      `ensureExists()` no-op when file present, `read()`/`getRawContent()` on missing file,
      write failure surfaces `RuntimeException` when the directory is not writable, validation
      failure prevents any file write.
- [x] 5.3 `tests/Unit/Controller/CustomCssControllerAuditTest.php` (mirroring the existing
      `OverridesControllerAuditTest.php` pattern): asserts `ThemingAuditService::log()` is called
      exactly once per successful save and once per enabled-flag toggle, with the correct action
      name and acting-user context, and NOT called on a validation failure.
- [x] 5.4 Extend `tests/Unit/Service/CssInjectionServiceTest.php`: assert `custom-css` is emitted
      immediately after `custom-overrides` in the stylesheet sequence when
      `custom_css_enabled === '1'`, and is absent from the sequence when `'0'` (default).

## 6. Quality Gates

- [x] 6.1 Run `composer check:strict` (PHPCS, PHPMD, Psalm, PHPStan) against all new/changed PHP
      files and fix any findings, including pre-existing issues encountered in touched files.
- [x] 6.2 Run the full `phpunit` suite and confirm all new and existing tests pass.
- [x] 6.3 Add/extend a Playwright spec-coverage test (`tests/e2e/spec-coverage/custom-css-freeform.spec.ts`,
      mirroring `custom-css-overrides.spec.ts`) covering the admin UI save/enable flow, or apply a
      reason-bearing `@e2e exclude` on any scenario that is backend-only file/cascade behaviour
      with no distinct UI surface (matching the precedent set in
      `openspec/specs/custom-css-overrides/spec.md`).
- [x] 6.4 Run `npm run test:lasuite-tokens`, `test:lasuite-override`, `test:lasuite-bridge-coverage`,
      `npx stylelint css/systems/lasuite/*.css`, and `npm run test:unit`; confirm all remain green
      (no regression from the new CSS layer or admin.php changes).

## Notes from the build (2 Oct 2026)

- Task 5.2 was ticked but `tests/Unit/Service/CustomCssServiceTest.php` did not exist. Writing it
  found a bug: `write()` puts a header comment above the CSS and `read()` returned it as part of
  the CSS. The editor showed the comment, and every save stacked one more header on top
  (and counted it towards the 64 KB cap). Clearing the textarea left a header-only file that
  `hasContent()` read as content, so an empty stylesheet was still linked. `read()` now peels
  every header (which heals a file that already stacked several on the next save), and the test
  covers the round trip, the stacked case, the empty case, the 422 path and an unwritable
  file. Since #811 the file lives in the runtime file store (app data) instead of the app's
  `css/` directory, so the temp-file-and-rename part of task 5.2 belongs to that store now; the
  test runs on a real `DirectoryRuntimeFileStore`.
- Task 3.1 shipped with one endpoint pair instead of three methods: `POST /settings/custom-css`
  carries `enabled` with the CSS, and the single `custom_css_written` entry records the new state.
  A rejected save is audited too (`custom_css_rejected`), so the log shows attempts as well as
  writes. The POST answers with the stored size and the enabled state, not the content; the spec
  says so now.
- Task 6.3: every scenario carries `@e2e exclude` naming the unit test that proves it. The admin
  textarea flow is thin relay code; a Playwright pass of save and enable is owed live.

## Acceptance Criteria

- Freeform CSS never loads or has any effect while `custom_css_enabled` is `'0'` (default).
- `custom-css.css` is emitted only after `custom-overrides.css`, so freeform admin CSS always wins
  the cascade over the token editor and every design-system layer.
- A submission containing `@import`, `@charset`, an external-scheme `url()`, `expression(`,
  `behavior:`, `-moz-binding:`, `</style`, `<script`, unbalanced braces, or a declaration of a
  REQ-CSS-007-reserved variable is rejected in full, with nothing written to disk.
- Same-origin/relative `url()` values and `data:` URIs are accepted.
- A submission over 64 KB is rejected.
- Every successful save and every enabled-flag toggle produces exactly one new
  `ThemingAuditService` entry naming the acting user.
- All endpoints are reachable only through `#[AuthorizedAdminSetting(Admin::class)]`, including for
  a delegated (non-full) admin.
- `composer check:strict`, `phpunit`, and the existing `npm run test:lasuite-*` /
  `test:unit` / stylelint guards all pass with no regressions.
