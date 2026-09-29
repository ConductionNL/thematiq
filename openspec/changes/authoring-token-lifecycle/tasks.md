# Tasks: add your own tokens and retire them with notice

Tick a box when the work is merged to `development`, not when it is started.
Types beyond `color` and `text`, and dark values, arrive with `authoring-token-value-types`.
Writing `$deprecated` into the export arrives with `authoring-dtcg-export`.

## 1. Check the inputs before building

- [ ] 1.1 Read `EditTokenForm.tsx:450-468` in tokens-studio/figma-plugin at 2.12.1 and record the
      severity values Tokens Studio writes. Map them to `info`, `warning` and `critical` in
      design.md decision 4, or change the three values. Verify: the mapping is in design.md.

## 2. Own tokens

- [ ] 2.1 Add `lib/Service/OwnTokenService.php` (store, name rule, duplicate check, value check by
      type). Verify: PHPUnit `OwnTokenServiceTest::testNameRule`, `testDuplicateRefused`,
      `testValueCheckedByType`.
- [ ] 2.2 Render own tokens in `CustomOverridesService` after the registry overrides, without
      `!important`, light in `:root` and dark in the dark scopes. Verify: PHPUnit
      `CustomOverridesServiceTest::testOwnTokensRenderedAfterOverrides`, `testOwnTokenDarkValue`.
- [ ] 2.3 Add `lib/Controller/OwnTokenController.php` with list, create, update and delete on
      `/settings/tokens/own`, all `#[AuthorizedAdminSetting(Admin::class)]`. Verify: PHPUnit
      `OwnTokenControllerTest`, and `curl -u admin:$ADMIN_PASSWORD -H 'Content-Type: application/json'
      -d '{"name":"brand-accent","label":"Brand accent","type":"color","value":"#e17000"}'
      http://localhost:8080/apps/thematiq/settings/tokens/own` returns 200.
- [ ] 2.4 Add a unit test that fails when any shipped file under `css/` other than
      `custom-overrides.css` declares or reads a `--nldesign-org-` name. Verify: the test is green
      on development and red on a fixture that declares one.

## 3. Deprecations

- [ ] 3.1 Add `lib/Service/TokenDeprecationService.php` (record shape, replacement exists, date
      rule, `due`, `state`). Verify: PHPUnit `TokenDeprecationServiceTest::testReplacementMustExist`,
      `testPastDateRefused`, `testDueAfterDate`, `testValueNeverChanged`.
- [ ] 3.2 Admin endpoints on `/settings/tokens/deprecations` and the non-admin
      `CatalogController::deprecations()` on `GET /api/token-deprecations`. Verify: PHPUnit
      `CatalogControllerTest::testDeprecationsShape`, and `curl -u $USER_NAME:$USER_PASSWORD
      http://localhost:8080/apps/thematiq/api/token-deprecations` as a non-admin returns 200.
- [ ] 3.3 Write the deprecation comment above each deprecated own token in `custom-overrides.css`.
      Verify: PHPUnit `testDeprecationCommentWritten`, comment text as in the spec.
- [ ] 3.4 "Record as deprecations" on the upload result. Verify: Playwright scenario "An
      administrator keeps the notices from an upload".
- [ ] 3.5 When `authoring-dtcg-export` has landed: write `$deprecated` and the extension fields for
      each deprecated token. Verify: PHPUnit `DesignTokensWriterTest::testDeprecatedTokenCarriesNotice`.

## 4. Admin panel

- [ ] 4.1 "Your own tokens" section, "Add a token" dialog, row actions Edit, Deprecate and Remove.
      Verify: Playwright `tests/e2e/spec-coverage/own-tokens.spec.ts`, scenarios "An administrator
      adds a brand accent colour", "A user in the dark theme gets the dark value", "Custom CSS can
      use an own token at once", "An administrator removes a token after its removal date".
- [ ] 4.2 Deprecate dialog and deprecation badge on every `--nldesign-*` row, the deprecations list
      with "Due for removal" and "Notice only". Verify: Playwright
      `tests/e2e/spec-coverage/token-deprecations.spec.ts`, scenarios "An administrator deprecates
      an own token with a replacement and a date", "A shipped token is deprecated as a notice",
      "A due token keeps working".

## 5. Audit, bundle, integration tests

- [ ] 5.1 Add `own_token_changed` and `token_deprecation_changed` to `ThemingAuditService::VOCABULARY`
      and call `log()` once per successful write. Verify: PHPUnit
      `OwnTokenControllerAuditTest`, `TokenDeprecationAuditTest`, including the refused-request case.
- [ ] 5.2 Add `ownTokens` and `tokenDeprecations` to `ConfigBundleService::export()` and the import.
      Verify: PHPUnit `ConfigBundleServiceTest::testOwnTokensRoundTrip`.
- [ ] 5.3 Prove an importer without the new keys skips them. Verify: PHPUnit
      `ConfigBundleServiceTest::testBundleWithoutOwnTokensLeavesThemUnchanged`.
- [ ] 5.4 Newman: 200, 400, 401 and 403 paths for every new route, credentials from environment
      variables. Verify: `tests/integration/run-newman.sh`.

## 6. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [ ] 6.1 Localisation: section, dialog, badge, list, "Due for removal", "Notice only" and every
      error text in `l10n/en.json` and `l10n/nl.json`, domain `thematiq`. Verify: `npm run test:l10n`.
- [ ] 6.2 Documentation: `docs/features/token-editor.md` gains own tokens and deprecations, and a
      page for app builders on reading `/api/token-deprecations`. Verify: `npm run build` in `docs/`.
- [ ] 6.3 WCAG AA contrast: an own colour token shows the editor's contrast hint against the page
      background. Verify: Playwright adds `#ffff00` and sees the fail hint.
- [ ] 6.4 Dark mode: covered by task 2.2 and the Playwright dark scenario in 4.1.
- [ ] 6.5 Incomplete token sets: own tokens do not depend on the active set. Switch to a set that
      declares few tokens and check the own token still resolves. Verify: Playwright.
- [ ] 6.6 Accessibility: the dialog traps focus and returns it, every field has a visible label,
      the severity badge has text as well as colour. Verify: `npm run test:unit` on
      `tests/vitest/admin-a11y.spec.js`.
- [ ] 6.7 Security: admin endpoints use `#[AuthorizedAdminSetting]`, the read endpoint carries no
      user data, token names are checked against the name rule before they reach the CSS writer,
      and error responses are generic. Verify: PHPUnit `testNameCannotInjectCss` with
      `brand;}body{display:none`.

## 7. Before the pull request

- [ ] 7.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`.
- [ ] 7.2 On a running instance, add an own token, use it in custom CSS, deprecate it with a
      replacement, read `/api/token-deprecations` as a normal user, and record what was seen in the
      PR body.
