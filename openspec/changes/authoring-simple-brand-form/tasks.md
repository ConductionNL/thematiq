# Tasks: create a house style from two colours and a logo

Tick a box when the work is merged to `development`.

## 1. Derivation

- [ ] 1.1 `scripts/mapping/brand-form.json`: the derivation rules per required token. Verify: a unit test asserts every `TokenSetVocabularyAuditService::REQUIRED_TOKENS` entry has a rule.
- [ ] 1.2 `lib/Service/BrandFormService.php` and the JS mirror in `js/lib/`. Verify: `tests/Unit/Service/BrandFormServiceTest.php` and a vitest fixture run the same inputs through both runtimes and compare byte for byte.
- [ ] 1.3 Text-on-primary selection by contrast. Verify: unit tests with a light, a dark and a mid-tone primary.

## 2. Endpoint and form

- [ ] 2.1 `POST /settings/tokensets/from-colours` on `CustomTokenSetController`, `#[AuthorizedAdminSetting]`, storing through `CustomTokenSetService::store()`. Verify: controller tests for success, a duplicate name (409), an invalid colour (400) and non-admin (403).
- [ ] 2.2 "From colours" tab in the Custom token sets block with live preview and contrast readout. Verify: Playwright scenario "An administrator creates a house style from a red and a white".

## 3. Quality

- [ ] 3.1 The generated set passes `npm run audit:token-sets` with no missing required token. Verify: a test runs the audit over a generated fixture.
- [ ] 3.2 Dark mode: the dark variant is derived and passes the dark contrast pairs. Verify: unit test over `DarkPaletteService::deriveDarkDeclarations()` with a generated set.
- [ ] 3.3 l10n en and nl. Verify: `npm run test:l10n`.
- [ ] 3.4 Docs: "Start from your colours" in `docs/`. Verify: the docs build.
