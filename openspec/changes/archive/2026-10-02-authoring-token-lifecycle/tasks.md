# Tasks: add your own tokens and retire them with notice

Tick a box when the work is merged to `development`, not when it is started.
Types beyond `color` and `text`, and dark values, arrive with `authoring-token-value-types`.
Writing `$deprecated` into the export arrives with `authoring-dtcg-export`.

## 1. Check the inputs before building

- [x] 1.1 Read `EditTokenForm.tsx:450-468` in tokens-studio/figma-plugin at 2.12.1 and record the
      severity values Tokens Studio writes. Map them to `info`, `warning` and `critical` in
      design.md decision 4, or change the three values. Verify: the mapping is in design.md.

## 2. Own tokens

- [x] 2.1 Add `lib/Service/OwnTokenService.php` (store, name rule, duplicate check, value check by
      type). Verify: PHPUnit `OwnTokenServiceTest::testNameRule`, `testDuplicateRefused`,
      `testValueCheckedByType`.
- [x] 2.2 Render own tokens in `CustomOverridesService` after the registry overrides, without
      `!important`, light in `:root` and dark in the dark scopes. Verify: PHPUnit
      `CustomOverridesServiceTest::testOwnTokensRenderedAfterOverrides`, `testOwnTokenDarkValue`.
- [x] 2.3 Add `lib/Controller/OwnTokenController.php` with list, create, update and delete on
      `/settings/tokens/own`, all `#[AuthorizedAdminSetting(Admin::class)]`. Verify: PHPUnit
      `OwnTokenControllerTest`, and `curl -u admin:$ADMIN_PASSWORD -H 'Content-Type: application/json'
      -d '{"name":"brand-accent","label":"Brand accent","type":"color","value":"#e17000"}'
      http://localhost:8080/apps/thematiq/settings/tokens/own` returns 200.
- [x] 2.4 Add a unit test that fails when any shipped file under `css/` other than
      `custom-overrides.css` declares or reads a `--nldesign-org-` name. Verify: the test is green
      on development and red on a fixture that declares one.

## 3. Deprecations

- [x] 3.1 Add `lib/Service/TokenDeprecationService.php` (record shape, replacement exists, date
      rule, `due`, `state`). Verify: PHPUnit `TokenDeprecationServiceTest::testReplacementMustExist`,
      `testPastDateRefused`, `testDueAfterDate`, `testValueNeverChanged`.
- [x] 3.2 Admin endpoints on `/settings/tokens/deprecations` and the non-admin
      `CatalogController::deprecations()` on `GET /api/token-deprecations`. Verify: PHPUnit
      `CatalogControllerTest::testDeprecationsShape`, and `curl -u $USER_NAME:$USER_PASSWORD
      http://localhost:8080/apps/thematiq/api/token-deprecations` as a non-admin returns 200.
- [x] 3.3 Write the deprecation comment above each deprecated own token in `custom-overrides.css`.
      Verify: PHPUnit `testDeprecationCommentWritten`, comment text as in the spec.
- [x] 3.4 "Record as deprecations" on the upload result. Verify: Playwright scenario "An
      administrator keeps the notices from an upload".
- [x] 3.5 When `authoring-dtcg-export` has landed: write `$deprecated` and the extension fields for
      each deprecated token. Verify: PHPUnit `DesignTokensWriterTest::testDeprecatedTokenCarriesNotice`.

## 4. Admin panel

- [x] 4.1 "Your own tokens" section, "Add a token" dialog, row actions Edit, Deprecate and Remove.
      Verify: Playwright `tests/e2e/spec-coverage/own-tokens.spec.ts`, scenarios "An administrator
      adds a brand accent colour", "A user in the dark theme gets the dark value", "Custom CSS can
      use an own token at once", "An administrator removes a token after its removal date".
- [x] 4.2 Deprecate dialog and deprecation badge on every `--nldesign-*` row, the deprecations list
      with "Due for removal" and "Notice only". Verify: Playwright
      `tests/e2e/spec-coverage/token-deprecations.spec.ts`, scenarios "An administrator deprecates
      an own token with a replacement and a date", "A shipped token is deprecated as a notice",
      "A due token keeps working".

## 5. Audit, bundle, integration tests

- [x] 5.1 Add `own_token_changed` and `token_deprecation_changed` to `ThemingAuditService::VOCABULARY`
      and call `log()` once per successful write. Verify: PHPUnit
      `OwnTokenControllerAuditTest`, `TokenDeprecationAuditTest`, including the refused-request case.
- [x] 5.2 Add `ownTokens` and `tokenDeprecations` to `ConfigBundleService::export()` and the import.
      Verify: PHPUnit `ConfigBundleServiceTest::testOwnTokensRoundTrip`.
- [x] 5.3 Prove an importer without the new keys skips them. Verify: PHPUnit
      `ConfigBundleServiceTest::testBundleWithoutOwnTokensLeavesThemUnchanged`.
- [x] 5.4 Newman: 200, 400, 401 and 403 paths for every new route, credentials from environment
      variables. Verify: `tests/integration/run-newman.sh`.

## 6. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [x] 6.1 Localisation: section, dialog, badge, list, "Due for removal", "Notice only" and every
      error text in `l10n/en.json` and `l10n/nl.json`, domain `thematiq`. Verify: `npm run test:l10n`.
- [x] 6.2 Documentation: `docs/features/token-editor.md` gains own tokens and deprecations, and a
      page for app builders on reading `/api/token-deprecations`. Verify: `npm run build` in `docs/`.
- [x] 6.3 WCAG AA contrast: an own colour token shows the editor's contrast hint against the page
      background. Verify: Playwright adds `#ffff00` and sees the fail hint.
- [x] 6.4 Dark mode: covered by task 2.2 and the Playwright dark scenario in 4.1.
- [x] 6.5 Incomplete token sets: own tokens do not depend on the active set. Switch to a set that
      declares few tokens and check the own token still resolves. Verify: Playwright.
- [x] 6.6 Accessibility: the dialog traps focus and returns it, every field has a visible label,
      the severity badge has text as well as colour. Verify: `npm run test:unit` on
      `tests/vitest/admin-a11y.spec.js`.
- [x] 6.7 Security: admin endpoints use `#[AuthorizedAdminSetting]`, the read endpoint carries no
      user data, token names are checked against the name rule before they reach the CSS writer,
      and error responses are generic. Verify: PHPUnit `testNameCannotInjectCss` with
      `brand;}body{display:none`.

## 7. Before the pull request

- [x] 7.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`.
- [x] 7.2 On a running instance, add an own token, use it in custom CSS, deprecate it with a
      replacement, read `/api/token-deprecations` as a normal user, and record what was seen in the
      PR body.

## Notes at archive (2 Oct 2026)

- 1.1 The mapping is in design.md decision 4: Tokens Studio writes `warning` and `error` (`EditTokenForm.tsx:444-489` at 2.12.1); `error` maps to `critical`.
- 2.2 Built by moving the file's CSS building out of `CustomOverridesService` into `OverridesCssBuilder` (the class was at its complexity limit); own tokens come from `OwnTokenService::css()` as an `OwnTokenCss`. `read()` and the dark read now return only editor tokens, so an own token never shows up as an editor override. Own tokens are instance-wide: `rewriteAll()` writes them into every set's overrides file.
- 2.3 The duplicate name answers 400, as the spec says (not 409). `PUT` and `DELETE /settings/tokens/own/{name}` carry the name in the path.
- 2.4 `tests/Unit/OrgTokenPrefixReservedTest.php`: green on the shipped `css/`, and its scan finds a declaration and a read in a fixture.
- 3.1 to 3.3 Storage lives in `DeprecationRecords` (shared by `TokenDeprecationService` and `OwnTokenService`, so neither depends on the other). A replacement may be an own token, an editor token or a name `defaults.css` declares.
- 3.4 The upload notices now carry the CSS variable they became (`token`) and the Tokens Studio severity; only those notices can be recorded. Verified in `tests/vitest/admin-dtcg-diagnostics.spec.js` (jsdom) instead of Playwright.
- 3.5 Moved to the `authoring-dtcg-export` pull request, which stacks on this one: the export writes `$deprecated` from `DeprecationRecords`.
- 4.1, 4.2 Built as `js/ownTokens.js` with two native `<dialog>` elements in the template. Verified with `tests/vitest/ownTokens.spec.js` (jsdom, over the template's own markup) instead of Playwright. A browser run is owed (live check in the PR body).
- 5.1 `tests/Unit/Controller/OwnTokenControllerTest.php` (`testCreateWritesFileAndAuditsOnce`, `testRefusedCreateIs400WithoutAudit`, `testDeprecationAuditedAndRefusalNot`).
- 5.2, 5.3 `ConfigBundleServiceTest::testOwnTokensRoundTrip`, `testBundleWithoutOwnTokensLeavesThemUnchanged`, `testBadOwnTokenRefusesTheBundle`.
- 5.4 Newman: 200, 400, 401 and 404 for the new routes. No 403 case: the collection has no non-admin user to log in with; the admin-only posture is asserted by `testEndpointsAreAdminOnly` (attribute check). The newman run needs an instance and is owed.
- 6.1 en and nl translated; the other locales carry the English text.
- 6.2 Written without screenshots (owed). The app-builder page is `docs/features/token-deprecations-api.md`.
- 6.3 An own colour shows its contrast against the page background (3:1, non-text), asserted in `tests/vitest/ownTokens.spec.js` with `#ffff00`.
- 6.5 Own tokens are written into every set's file (`testEveryFileGetsOwnTokens`); the browser check on a sparse set is owed.
- 6.6 Native modal dialogs keep focus inside and close on Escape; focus returns to the opener and every field has a visible label (`tests/vitest/ownTokens.spec.js`). The badge says the severity in words.
- 6.7 `OwnTokenServiceTest::testNameCannotInjectCss`; error texts are fixed, translated strings.
- 7.2 Owed: the live pass (recipe in the PR body).
