# Tasks: write design tokens in the DTCG format

Tick a box when the work is merged to `development`, not when it is started.

## 1. Check the inputs before building

- [x] 1.1 Read the DTCG Format Module and Color Module v2025.10. Confirm the colour space
      identifiers, the `dimension` units (`px`, `rem`), the `duration` object form, the
      `cubicBezier` shape and the extension naming rule that design decision 4 assumes. Record the
      section numbers in design.md, or correct the table. Verify: the citations are in design.md.
- [x] 1.2 Write `tests/Unit/fixtures/dtcg/colour-spaces.tokens.json` with one colour per colour
      space, each with the sRGB value the CSS Color 4 sample code gives. Verify: the file parses,
      and each expected value is written next to its source in a comment field.

## 2. Colour conversion

- [x] 2.1 Add `lib/Service/ColorSpaceConverter.php`: to and from sRGB for every listed colour space,
      a gamut check, and clipping. Verify: PHPUnit `ColorSpaceConverterTest` pins one value per
      colour space within 1 per channel, including `oklch(0.5 0.1 250)` to `#32669a` and
      `display-p3` `[1, 0, 0]` to `#ff0000` out of gamut.
- [x] 2.2 Use it in `DesignTokensMapper::serializeColorObject()`: every listed colour space, the
      transfer function for `srgb-linear`, `alpha` as 8-digit hex, the two `out-of-gamut-*` report
      reasons. Verify: PHPUnit `DesignTokensMapperTest::testOklchConvertsToSrgb`,
      `testSrgbLinearAppliesTransferFunction`, `testAlphaKeptAsEightDigitHex`,
      `testOutOfGamutUsesHexFallback`, `testOutOfGamutWithoutHexIsClipped`, and every existing
      mapper test stays green.
- [x] 2.3 Carry the two new reason codes into the converter report and into the panel's reason
      labels (`reasonLabel()` in `js/admin.js`). Verify: `npm run test:unit` on
      `tests/vitest/admin-dtcg-diagnostics.spec.js` with a clipped colour.

## 3. The thematiq extension on import

- [x] 3.1 Read `$extensions["nl.conduction.thematiq"].cssVariable` in `resolveTarget()` before the
      suffix table, and the root `cssOnly` map as declarations. Both pass the validator. Verify:
      PHPUnit `testExtensionNamesTheTarget`, `testCssOnlyMapIsImported`,
      `testExtensionWithForbiddenValueIsRefused`.

## 4. The writer and the endpoint

- [x] 4.1 Add `lib/Service/DesignTokensWriter.php`: paths, the extension on every token, the typing
      table, aliases, the `cssOnly` map, the root description (design decisions 3 and 4). Verify:
      PHPUnit `DesignTokensWriterTest` with one case per row of the typing table.
- [x] 4.2 Add `CustomTokenSetController::exportDtcg()` on
      `GET /settings/tokensets/{id}/dtcg` with `#[AuthorizedAdminSetting(Admin::class)]`, 404 for an
      unknown id, `application/json` and the `{id}.tokens.json` attachment name. Verify: PHPUnit
      `CustomTokenSetControllerTest::testExportDtcgShippedSet`, `testExportDtcgUnknownIs404`, and
      `curl -u admin:$ADMIN_PASSWORD -OJ http://localhost:8080/apps/thematiq/settings/tokensets/amsterdam/dtcg`.
- [x] 4.3 Add the 200, 403 and 404 calls to `tests/integration/thematiq.postman_collection.json`,
      credentials from environment variables. Verify: `tests/integration/run-newman.sh` is green.
- [x] 4.4 Add `tests/Unit/Service/DesignTokensRoundTripTest.php`: export every shipped set, import
      it through `TokenSetConverterService::convert()`, and compare every `--nldesign-*` value.
      Verify: the test is green for all sets in `token-sets.json`.

## 5. Admin panel

- [x] 5.1 Add "Download as design tokens" next to the token set dropdown (`templates/settings/admin.php:66-76`)
      for the selected set, and next to "Download" in each custom set row (`js/admin.js:4381-4392`).
      Verify: Playwright `tests/e2e/spec-coverage/token-set-dtcg-export.spec.ts`, scenarios "An
      administrator downloads a shipped set" and "An administrator downloads a custom set from its row".
- [x] 5.2 Round trip by hand in the browser: download a set, upload it under a new name, compare.
      Verify: Playwright scenario "An administrator carries a set out and back in".

## 6. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [x] 6.1 Localisation: the button labels, the two new reason labels and every new error text in
      `l10n/en.json` and `l10n/nl.json`, domain `thematiq`. Verify: `npm run test:l10n`.
- [x] 6.2 Documentation: a "Download as design tokens" section in `docs/features/import-export.md`
      with a screenshot, the colour space rules and the round-trip limits. Verify: `npm run build`
      in `docs/`.
- [x] 6.3 WCAG AA contrast: a colour converted from another colour space reaches the contrast check
      as sRGB hex. Test an `oklch` primary that fails 4.5:1 on white and gets a warning. Verify:
      PHPUnit `CustomTokenSetServiceTest::testConvertedColourIsContrastChecked`.
- [x] 6.4 Dark mode: a set imported from `oklch` gets a dark variant for that token. Verify: PHPUnit
      `DarkPaletteServiceTest::testConvertedColourIsDerived`.
- [x] 6.5 Incomplete token sets: exporting a set that declares only a few tokens gives only those
      tokens, and importing it back falls back to the defaults for the rest. Verify: PHPUnit
      `DesignTokensRoundTripTest::testIncompleteSetStaysIncomplete`.
- [x] 6.6 Accessibility: both buttons are real `<button>` elements with visible text, reachable by
      keyboard. Verify: `npm run test:unit` on `tests/vitest/admin-a11y.spec.js`.
- [x] 6.7 Security: the endpoint is admin-only, reads only `css/tokens/{id}.css` for an id
      `isValidTokenSet()` accepts (no path traversal), and returns generic errors. Verify: PHPUnit
      `testExportDtcgRejectsTraversalId` with `../appinfo/info`.

## 7. Before the pull request

- [x] 7.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`.
- [x] 7.2 Open one exported file in Tokens Studio (or another DTCG tool) and record in the PR body
      whether it loads, and what it shows for the colour objects.

## Notes at archive (2 Oct 2026)

- 1.1 Citations are in design.md decision 4. One correction came out of it: DTCG §8.6 allows a curve's x values only in 0..1, so a `cubic-bezier()` outside that is written to `cssOnly`.
- 1.2 `tests/Unit/fixtures/dtcg/colour-spaces.tokens.json`, one colour per space with its reference sRGB in `$description` (lab, lch and hwb cross-checked against colord 2.9; the others from the CSS Color 4 sample matrices).
- 2.1 `lib/Service/ColorSpaceConverter.php`; the CSS side of reading colours is `lib/Service/CssColorParser.php`. `ColorSpaceConverterTest` pins every space within 1 per channel.
- 2.2 The mapper converts every listed colour space; the fixture `03-object-color-and-dimension` now uses `cmyk` as its unsupported space, since display-p3 is supported. `srgb-linear` and alpha tests already existed (lane 4) and stay green.
- 2.3 The report carries `adapted` entries with the original value; the upload result lists them through `reasonLabel()`. Verified in `tests/vitest/admin-dtcg-diagnostics.spec.js` (jsdom).
- 3.1 Besides `cssVariable`, a root `setId` marks the document as a thematiq export. The converter then keeps every name as it is (`ThematiqExportLayers`) instead of running the conversion rules; only the brand palette moves to the new prefix. That is what makes 4.4 exact.
- 4.2 Changed at build: the endpoint lives on `DtcgExportController` (one route, its own dependencies), not on `CustomTokenSetController`. It answers with a `JSONResponse` and a `Content-Disposition: attachment` header.
- 4.3 The three calls are in the collection; the newman run needs an instance and is owed. No 403 case: the collection has no non-admin login; `DtcgExportControllerTest::testAdminOnly` asserts the attribute.
- 4.4 `DesignTokensRoundTripTest` over all 51 sets in `token-sets.json`; values are compared after following `var()` inside each set and reading colours as sRGB hex.
- 5.1 and 5.2 Verified with `tests/vitest/admin-dtcg-diagnostics.spec.js` (jsdom) instead of Playwright; a browser run, including the by-hand round trip, is owed.
- 6.1 en and nl translated; the other locales carry the English text.
- 6.2 Written without a screenshot (owed); docs site build not run in the lane.
- 6.3 `DtcgImportPipelineTest::testConvertedColourIsContrastChecked`; 6.4 `::testConvertedColourIsDerived` (with an unconverted control the palette skips).
- 6.5 `DesignTokensRoundTripTest::testIncompleteSetStaysIncomplete`; 6.7 `DtcgExportControllerTest::testExportDtcgRejectsTraversalId`.
- Lifecycle task 3.5 (`$deprecated` in the export) is built here: `DesignTokensWriterTest::testDeprecatedTokenCarriesNotice`, `DtcgExportControllerTest::testDeprecationReachesTheDownload`.
- 7.2 Owed: open one exported file in Tokens Studio and record what it shows (recipe in the PR body).
