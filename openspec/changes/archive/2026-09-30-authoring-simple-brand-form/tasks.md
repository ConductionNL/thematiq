# Tasks: create a house style from two colours and a logo

Tick a box when the work is merged to `development`.

## 1. Derivation

- [x] 1.1 `scripts/mapping/brand-form.json`: the derivation rules per required token. Verify: a unit test asserts every `TokenSetVocabularyAuditService::REQUIRED_TOKENS` entry has a rule.
- [x] 1.2 `lib/Service/BrandFormService.php` and the JS mirror in `js/lib/`. Verify: `tests/Unit/Service/BrandFormServiceTest.php` and a vitest fixture run the same inputs through both runtimes and compare byte for byte.
- [x] 1.3 Text-on-primary selection by contrast. Verify: unit tests with a light, a dark and a mid-tone primary.

## 2. Endpoint and form

- [x] 2.1 `POST /settings/tokensets/from-colours` on `CustomTokenSetController`, `#[AuthorizedAdminSetting]`, storing through `CustomTokenSetService::store()`. Verify: controller tests for success, a duplicate name (409), an invalid colour (400) and non-admin (403).
- [x] 2.2 "From colours" tab in the Custom token sets block with live preview and contrast readout. Verify: Playwright scenario "An administrator creates a house style from a red and a white".

## 3. Quality

- [x] 3.1 The generated set passes `npm run audit:token-sets` with no missing required token. Verify: a test runs the audit over a generated fixture.
- [x] 3.2 Dark mode: the dark variant is derived and passes the dark contrast pairs. Verify: unit test over `DarkPaletteService::deriveDarkDeclarations()` with a generated set.
- [x] 3.3 l10n en and nl. Verify: `npm run test:l10n`.
- [x] 3.4 Docs: "Start from your colours" in `docs/`. Verify: the docs build.

## Notes at archive (30 Sep 2026)

- 1.2 The JS mirror is `js/lib/brandForm.js` on `tokenConverter.js`'s colour helpers. Parity: `tests/Unit/fixtures/brand-form-parity.json` (written by `scripts/generate-brand-form-fixture.php`) is checked against PHP by `BrandFormServiceTest::testParityFixtureMatchesPhp` and against JS by `tests/vitest/brandForm.spec.js`.
- 2.1 The endpoint lives on `BrandFormController` (see design, change at build). 403 is proven by the `#[AuthorizedAdminSetting]` attribute test, as elsewhere in this repo.
- 2.2 Verified with `tests/vitest/admin-brand-form.spec.js` (jsdom) instead of Playwright; a browser run is owed (live check in the PR body).
- 3.1 `testAuditFindsNothingMissing` runs `TokenSetVocabularyAuditService::auditSet()`, the service `npm run audit:token-sets` mirrors, over a stored generated set.
- 3.2 Found and fixed: `DarkPaletteService` kept a passing brand primary in dark mode but remapped `--nldesign-color-primary-text` to grey (2.2:1 on `#c8102e`), for every custom set. The text on primary now stays with it.
- 3.3 en and nl translated; the other locales carry the English text, as for earlier strings.
