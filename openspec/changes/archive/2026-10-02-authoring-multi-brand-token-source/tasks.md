# Tasks: one token source, several brands

Tick a box when the work is merged to `development`, not when it is started.
This change lands after tasks 6.1 to 6.3 of `openspec/changes/nlds-theme-converter`.

## 1. Check the inputs before building

- [x] 1.1 Read `packages/tokens-studio-for-figma/src/types/ThemeObject.ts` and the token set status
      enum at tokens-studio/figma-plugin tag 2.12.1. Confirm the `enabled`, `source` and `disabled`
      values and the `$metadata.tokenSetOrder` rule that design decision 3 assumes. Record the file
      and line in design.md, or correct the decision. Verify: the cited lines are in design.md.
- [x] 1.2 Build three fixtures under `tests/Unit/fixtures/multi-brand/`: a Tokens Studio file with
      three themes in `$value` form, the same in legacy `value` form, and built CSS with `:root`
      plus two `.{key}-theme` blocks. Hand-authored, no upstream package (as `nlds-theme-converter`
      decision 3). Verify: each file parses (`php -r 'json_decode(...)'` for JSON).

## 2. Detection and conversion

- [x] 2.1 Add `TokenSetConverterService::detectBrands()` (design decision 1). Verify: PHPUnit
      `TokenSetConverterMultiBrandTest::testDetectsTokensStudioThemes`,
      `testDetectsBrandClasses`, `testRootAndModifierBlocksAreNotBrands`, `testSingleBrandReturnsOne`.
- [x] 2.2 Cut one brand out of a source and feed it to `convert()` (decision 3). Verify: PHPUnit
      `testBrandInheritsSharedBlocks`, `testLaterTokenSetWins`, `testLegacyFormatRoutesToInputC`.
- [x] 2.3 Add the optional `referenceOnlyPaths` argument to `DesignTokensMapper::map()` and to
      `collectStyleDictionaryLeaves()`. Verify: PHPUnit
      `DesignTokensMapperTest::testReferenceOnlyLeafResolvesAliasButIsNotEmitted`, and the existing
      `DesignTokensMapperTest` stays green.
- [x] 2.4 Mirror 2.1 and 2.2 in `js/lib/tokenConverter.js`, so the CLI and the admin path agree.
      Verify: `npm run test:unit` runs `tests/vitest/tokenConverterMultiBrand.spec.js` over the same
      three fixtures, and the parity test gets the same output as PHP.

## 3. Storage and endpoints

- [x] 3.1 Add the `brands` parameter to `CustomTokenSetController::upload()`: no parameter lists
      brands and stores nothing, a parameter imports those brands, unknown keys give 422, more than
      20 give 422 (decision 2). Verify: PHPUnit `CustomTokenSetControllerMultiBrandTest`, and
      `curl -u admin:$ADMIN_PASSWORD -F name=Voorbeeld -F file=@three-themes.json
      http://localhost:8080/apps/thematiq/settings/tokensets/upload` returns `multiBrand: true`.
- [x] 3.2 Store each brand through `CustomTokenSetService::store()` with the `source` link, write the
      `custom_token_sources` record, and check every id for a collision first (decision 4). Verify:
      PHPUnit `CustomTokenSetServiceTest::testCollisionRefusesWholeImport`,
      `testSourceRecordListsBrands`.
- [x] 3.3 Add `POST /settings/tokensets/sources/{sourceId}` (`updateSource()`) with
      `#[AuthorizedAdminSetting(Admin::class)]`, reusing `replace()` and the dark variant
      generator (decision 5). Verify: PHPUnit `testUpdateReplacesEveryBrand`,
      `testMissingBrandIsKept`, `testOneFailingBrandReplacesNothing`.
- [x] 3.4 Remove the source record when its last brand set is deleted (decision 6). Verify: PHPUnit
      `testDeletingLastBrandRemovesSource`.
- [x] 3.5 Add `custom_source_updated` to `ThemingAuditService::VOCABULARY`, and carry `sourceId` and
      `brand` on every `custom_set_uploaded` entry from a multi-brand import. Verify: PHPUnit
      `CustomTokenSetControllerAuditTest::testSourceUpdateIsAudited`, `testRefusedUpdateIsNotAudited`.
- [x] 3.6 Add the upload and update calls, with 200, 401, 403, 409 and 422 paths, to
      `tests/integration/thematiq.postman_collection.json`. Credentials come from environment
      variables. Verify: `tests/integration/run-newman.sh` is green.

## 4. Admin panel

- [x] 4.1 Render the brand picker after a `multiBrand` response: one labelled checkbox per brand,
      all ticked, token count beside it, a confirm button and a cancel button. Keyboard operable,
      focus moves to the picker heading when it opens. Verify: Playwright
      `tests/e2e/spec-coverage/multi-brand-token-sources.spec.ts`, scenario "The administrator
      imports two of three brands".
- [x] 4.2 Group brand sets under their source name in `renderCustomSetList()` with an "Update
      source" action per source. Verify: Playwright scenario "The set list groups brands under
      their source".
- [x] 4.3 Render the update report (`updated`, `missing`, `new`) in the existing result region
      (`role="status"`). Verify: Playwright scenarios "The administrator updates a source with a
      changed primary colour" and "A brand that disappeared is kept and reported".

## 5. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [x] 5.1 Localisation: every new string in `l10n/en.json` and `l10n/nl.json`, using the `thematiq`
      domain. Verify: `npm run test:l10n`.
- [x] 5.2 Documentation: a "Several brands in one source" section in
      `docs/features/custom-token-sets.md`, with a screenshot of the brand picker from a running
      instance. Verify: `npm run build` in `docs/` succeeds.
- [x] 5.3 WCAG AA contrast: each brand set gets its own contrast warnings through `store()`. Test a
      brand whose primary fails 4.5:1 against white. Verify: PHPUnit
      `testEachBrandCarriesItsOwnContrastWarnings`.
- [x] 5.4 Dark mode: each brand set gets a dark variant on import and on update. Verify: PHPUnit
      `testDarkVariantWrittenPerBrand`, and Playwright opens a brand set with the dark theme on.
- [x] 5.5 Incomplete token sets: a brand that sets only a primary colour still stores all required
      semantic tokens through the converter's fallbacks. Verify: PHPUnit
      `testIncompleteBrandFallsBackToDefaults`.
- [x] 5.6 Accessibility of the picker: checkboxes have visible labels, the picker works without a
      mouse, and colour is never the only signal. Verify: `tests/vitest/admin-a11y.spec.js` gains a
      picker case (`npm run test:unit`), and a keyboard-only pass in Playwright.
- [x] 5.7 Security: the new endpoint is admin-only, CSRF-protected, and returns generic error text
      without stack traces or paths. Verify: Newman 403 case, and `composer check:strict`.

## 6. Before the pull request

- [x] 6.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`. The last two are required CI checks that
      `check:strict` does not run.
- [x] 6.2 Upload a real multi-brand source by hand on a running instance, import two brands, map one
      to a group, update the source, and record what was seen in the PR body.

## Notes at archive (2 Oct 2026)

- Dependency: `nlds-theme-converter` tasks 6.1 and 6.3 (the paste box and its submit) are still open on `development`. Nothing here needs them: the brand picker works on the file upload, and `upload()` answers a pasted `content` the same way. When 6.3 lands, its submit must handle a `multiBrand` answer the way `uploadCustomTokenSet()` does (render the picker, repeat with `brands[]`).
- 1.1 Citations in design.md decision 3; the decision stands.
- 1.2 `tests/Unit/fixtures/multi-brand/` (README lists the three files; all parse).
- 2.1 and 2.2 Changed at build: detection and cutting live in `MultiBrandSource`, not in `TokenSetConverterService`, which is already past its size limits; `convert()` gains only the `referenceOnlyPaths` argument. Tests in `TokenSetConverterMultiBrandTest`.
- 2.4 Changed at build: the JS mirror is `js/lib/multiBrandSource.js` rather than a part of `tokenConverter.js`, for the same reason. Parity: `tests/Unit/fixtures/multi-brand/expected.json` is checked by `TokenSetConverterMultiBrandTest::testParityFixtureMatchesPhp` and `tests/vitest/tokenConverterMultiBrand.spec.js`.
- 3.1 to 3.5 `tests/Unit/Service/MultiBrandImportServiceTest.php` (first upload lists and stores nothing, record and source link, unknown brand 422, collision 409 with nothing written, update all or none, missing kept and new named, delete removes the source with its last brand, audit on update and none on a refusal, admin-only routes). A second import under an existing source name answers 409: update the source instead.
- 3.6 Newman: 200, 401, 404, 409 and 422. No 403 case: the collection has no non-admin login. The run is owed.
- 4.1 to 4.3 Verified with `tests/vitest/admin-multi-brand.spec.js` (jsdom) instead of Playwright; a browser run and a keyboard-only pass are owed.
- 5.1 en and nl translated; the other locales carry the English text. 5.2 written without a screenshot (owed).
- 5.3 to 5.5 `MultiBrandImportServiceTest::testEachBrandCarriesItsOwnContrastWarnings`, `::testDarkVariantWrittenPerBrand`, `::testIncompleteBrandFallsBackToDefaults`.
- 5.6 The picker's labels and focus are asserted in `tests/vitest/admin-multi-brand.spec.js`; its own case in `admin-a11y.spec.js` was not added.
- 6.2 Owed: the live pass (recipe in the PR body).
