# Tasks: one token source, several brands

Tick a box when the work is merged to `development`, not when it is started.
This change lands after tasks 6.1 to 6.3 of `openspec/changes/nlds-theme-converter`.

## 1. Check the inputs before building

- [ ] 1.1 Read `packages/tokens-studio-for-figma/src/types/ThemeObject.ts` and the token set status
      enum at tokens-studio/figma-plugin tag 2.12.1. Confirm the `enabled`, `source` and `disabled`
      values and the `$metadata.tokenSetOrder` rule that design decision 3 assumes. Record the file
      and line in design.md, or correct the decision. Verify: the cited lines are in design.md.
- [ ] 1.2 Build three fixtures under `tests/Unit/fixtures/multi-brand/`: a Tokens Studio file with
      three themes in `$value` form, the same in legacy `value` form, and built CSS with `:root`
      plus two `.{key}-theme` blocks. Hand-authored, no upstream package (as `nlds-theme-converter`
      decision 3). Verify: each file parses (`php -r 'json_decode(...)'` for JSON).

## 2. Detection and conversion

- [ ] 2.1 Add `TokenSetConverterService::detectBrands()` (design decision 1). Verify: PHPUnit
      `TokenSetConverterMultiBrandTest::testDetectsTokensStudioThemes`,
      `testDetectsBrandClasses`, `testRootAndModifierBlocksAreNotBrands`, `testSingleBrandReturnsOne`.
- [ ] 2.2 Cut one brand out of a source and feed it to `convert()` (decision 3). Verify: PHPUnit
      `testBrandInheritsSharedBlocks`, `testLaterTokenSetWins`, `testLegacyFormatRoutesToInputC`.
- [ ] 2.3 Add the optional `referenceOnlyPaths` argument to `DesignTokensMapper::map()` and to
      `collectStyleDictionaryLeaves()`. Verify: PHPUnit
      `DesignTokensMapperTest::testReferenceOnlyLeafResolvesAliasButIsNotEmitted`, and the existing
      `DesignTokensMapperTest` stays green.
- [ ] 2.4 Mirror 2.1 and 2.2 in `js/lib/tokenConverter.js`, so the CLI and the admin path agree.
      Verify: `npm run test:unit` runs `tests/vitest/tokenConverterMultiBrand.spec.js` over the same
      three fixtures, and the parity test gets the same output as PHP.

## 3. Storage and endpoints

- [ ] 3.1 Add the `brands` parameter to `CustomTokenSetController::upload()`: no parameter lists
      brands and stores nothing, a parameter imports those brands, unknown keys give 422, more than
      20 give 422 (decision 2). Verify: PHPUnit `CustomTokenSetControllerMultiBrandTest`, and
      `curl -u admin:$ADMIN_PASSWORD -F name=Voorbeeld -F file=@three-themes.json
      http://localhost:8080/apps/thematiq/settings/tokensets/upload` returns `multiBrand: true`.
- [ ] 3.2 Store each brand through `CustomTokenSetService::store()` with the `source` link, write the
      `custom_token_sources` record, and check every id for a collision first (decision 4). Verify:
      PHPUnit `CustomTokenSetServiceTest::testCollisionRefusesWholeImport`,
      `testSourceRecordListsBrands`.
- [ ] 3.3 Add `POST /settings/tokensets/sources/{sourceId}` (`updateSource()`) with
      `#[AuthorizedAdminSetting(Admin::class)]`, reusing `replace()` and the dark variant
      generator (decision 5). Verify: PHPUnit `testUpdateReplacesEveryBrand`,
      `testMissingBrandIsKept`, `testOneFailingBrandReplacesNothing`.
- [ ] 3.4 Remove the source record when its last brand set is deleted (decision 6). Verify: PHPUnit
      `testDeletingLastBrandRemovesSource`.
- [ ] 3.5 Add `custom_source_updated` to `ThemingAuditService::VOCABULARY`, and carry `sourceId` and
      `brand` on every `custom_set_uploaded` entry from a multi-brand import. Verify: PHPUnit
      `CustomTokenSetControllerAuditTest::testSourceUpdateIsAudited`, `testRefusedUpdateIsNotAudited`.
- [ ] 3.6 Add the upload and update calls, with 200, 401, 403, 409 and 422 paths, to
      `tests/integration/thematiq.postman_collection.json`. Credentials come from environment
      variables. Verify: `tests/integration/run-newman.sh` is green.

## 4. Admin panel

- [ ] 4.1 Render the brand picker after a `multiBrand` response: one labelled checkbox per brand,
      all ticked, token count beside it, a confirm button and a cancel button. Keyboard operable,
      focus moves to the picker heading when it opens. Verify: Playwright
      `tests/e2e/spec-coverage/multi-brand-token-sources.spec.ts`, scenario "The administrator
      imports two of three brands".
- [ ] 4.2 Group brand sets under their source name in `renderCustomSetList()` with an "Update
      source" action per source. Verify: Playwright scenario "The set list groups brands under
      their source".
- [ ] 4.3 Render the update report (`updated`, `missing`, `new`) in the existing result region
      (`role="status"`). Verify: Playwright scenarios "The administrator updates a source with a
      changed primary colour" and "A brand that disappeared is kept and reported".

## 5. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [ ] 5.1 Localisation: every new string in `l10n/en.json` and `l10n/nl.json`, using the `thematiq`
      domain. Verify: `npm run test:l10n`.
- [ ] 5.2 Documentation: a "Several brands in one source" section in
      `docs/features/custom-token-sets.md`, with a screenshot of the brand picker from a running
      instance. Verify: `npm run build` in `docs/` succeeds.
- [ ] 5.3 WCAG AA contrast: each brand set gets its own contrast warnings through `store()`. Test a
      brand whose primary fails 4.5:1 against white. Verify: PHPUnit
      `testEachBrandCarriesItsOwnContrastWarnings`.
- [ ] 5.4 Dark mode: each brand set gets a dark variant on import and on update. Verify: PHPUnit
      `testDarkVariantWrittenPerBrand`, and Playwright opens a brand set with the dark theme on.
- [ ] 5.5 Incomplete token sets: a brand that sets only a primary colour still stores all required
      semantic tokens through the converter's fallbacks. Verify: PHPUnit
      `testIncompleteBrandFallsBackToDefaults`.
- [ ] 5.6 Accessibility of the picker: checkboxes have visible labels, the picker works without a
      mouse, and colour is never the only signal. Verify: `tests/vitest/admin-a11y.spec.js` gains a
      picker case (`npm run test:unit`), and a keyboard-only pass in Playwright.
- [ ] 5.7 Security: the new endpoint is admin-only, CSRF-protected, and returns generic error text
      without stack traces or paths. Verify: Newman 403 case, and `composer check:strict`.

## 6. Before the pull request

- [ ] 6.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`. The last two are required CI checks that
      `check:strict` does not run.
- [ ] 6.2 Upload a real multi-brand source by hand on a running instance, import two brands, map one
      to a group, update the source, and record what was seen in the PR body.
