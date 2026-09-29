# Tasks: write design tokens in the DTCG format

Tick a box when the work is merged to `development`, not when it is started.

## 1. Check the inputs before building

- [ ] 1.1 Read the DTCG Format Module and Color Module v2025.10. Confirm the colour space
      identifiers, the `dimension` units (`px`, `rem`), the `duration` object form, the
      `cubicBezier` shape and the extension naming rule that design decision 4 assumes. Record the
      section numbers in design.md, or correct the table. Verify: the citations are in design.md.
- [ ] 1.2 Write `tests/Unit/fixtures/dtcg/colour-spaces.tokens.json` with one colour per colour
      space, each with the sRGB value the CSS Color 4 sample code gives. Verify: the file parses,
      and each expected value is written next to its source in a comment field.

## 2. Colour conversion

- [ ] 2.1 Add `lib/Service/ColorSpaceConverter.php`: to and from sRGB for every listed colour space,
      a gamut check, and clipping. Verify: PHPUnit `ColorSpaceConverterTest` pins one value per
      colour space within 1 per channel, including `oklch(0.5 0.1 250)` to `#32669a` and
      `display-p3` `[1, 0, 0]` to `#ff0000` out of gamut.
- [ ] 2.2 Use it in `DesignTokensMapper::serializeColorObject()`: every listed colour space, the
      transfer function for `srgb-linear`, `alpha` as 8-digit hex, the two `out-of-gamut-*` report
      reasons. Verify: PHPUnit `DesignTokensMapperTest::testOklchConvertsToSrgb`,
      `testSrgbLinearAppliesTransferFunction`, `testAlphaKeptAsEightDigitHex`,
      `testOutOfGamutUsesHexFallback`, `testOutOfGamutWithoutHexIsClipped`, and every existing
      mapper test stays green.
- [ ] 2.3 Carry the two new reason codes into the converter report and into the panel's reason
      labels (`reasonLabel()` in `js/admin.js`). Verify: `npm run test:unit` on
      `tests/vitest/admin-dtcg-diagnostics.spec.js` with a clipped colour.

## 3. The thematiq extension on import

- [ ] 3.1 Read `$extensions["nl.conduction.thematiq"].cssVariable` in `resolveTarget()` before the
      suffix table, and the root `cssOnly` map as declarations. Both pass the validator. Verify:
      PHPUnit `testExtensionNamesTheTarget`, `testCssOnlyMapIsImported`,
      `testExtensionWithForbiddenValueIsRefused`.

## 4. The writer and the endpoint

- [ ] 4.1 Add `lib/Service/DesignTokensWriter.php`: paths, the extension on every token, the typing
      table, aliases, the `cssOnly` map, the root description (design decisions 3 and 4). Verify:
      PHPUnit `DesignTokensWriterTest` with one case per row of the typing table.
- [ ] 4.2 Add `CustomTokenSetController::exportDtcg()` on
      `GET /settings/tokensets/{id}/dtcg` with `#[AuthorizedAdminSetting(Admin::class)]`, 404 for an
      unknown id, `application/json` and the `{id}.tokens.json` attachment name. Verify: PHPUnit
      `CustomTokenSetControllerTest::testExportDtcgShippedSet`, `testExportDtcgUnknownIs404`, and
      `curl -u admin:$ADMIN_PASSWORD -OJ http://localhost:8080/apps/thematiq/settings/tokensets/amsterdam/dtcg`.
- [ ] 4.3 Add the 200, 403 and 404 calls to `tests/integration/thematiq.postman_collection.json`,
      credentials from environment variables. Verify: `tests/integration/run-newman.sh` is green.
- [ ] 4.4 Add `tests/Unit/Service/DesignTokensRoundTripTest.php`: export every shipped set, import
      it through `TokenSetConverterService::convert()`, and compare every `--nldesign-*` value.
      Verify: the test is green for all sets in `token-sets.json`.

## 5. Admin panel

- [ ] 5.1 Add "Download as design tokens" next to the token set dropdown (`templates/settings/admin.php:66-76`)
      for the selected set, and next to "Download" in each custom set row (`js/admin.js:4381-4392`).
      Verify: Playwright `tests/e2e/spec-coverage/token-set-dtcg-export.spec.ts`, scenarios "An
      administrator downloads a shipped set" and "An administrator downloads a custom set from its row".
- [ ] 5.2 Round trip by hand in the browser: download a set, upload it under a new name, compare.
      Verify: Playwright scenario "An administrator carries a set out and back in".

## 6. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [ ] 6.1 Localisation: the button labels, the two new reason labels and every new error text in
      `l10n/en.json` and `l10n/nl.json`, domain `thematiq`. Verify: `npm run test:l10n`.
- [ ] 6.2 Documentation: a "Download as design tokens" section in `docs/features/import-export.md`
      with a screenshot, the colour space rules and the round-trip limits. Verify: `npm run build`
      in `docs/`.
- [ ] 6.3 WCAG AA contrast: a colour converted from another colour space reaches the contrast check
      as sRGB hex. Test an `oklch` primary that fails 4.5:1 on white and gets a warning. Verify:
      PHPUnit `CustomTokenSetServiceTest::testConvertedColourIsContrastChecked`.
- [ ] 6.4 Dark mode: a set imported from `oklch` gets a dark variant for that token. Verify: PHPUnit
      `DarkPaletteServiceTest::testConvertedColourIsDerived`.
- [ ] 6.5 Incomplete token sets: exporting a set that declares only a few tokens gives only those
      tokens, and importing it back falls back to the defaults for the rest. Verify: PHPUnit
      `DesignTokensRoundTripTest::testIncompleteSetStaysIncomplete`.
- [ ] 6.6 Accessibility: both buttons are real `<button>` elements with visible text, reachable by
      keyboard. Verify: `npm run test:unit` on `tests/vitest/admin-a11y.spec.js`.
- [ ] 6.7 Security: the endpoint is admin-only, reads only `css/tokens/{id}.css` for an id
      `isValidTokenSet()` accepts (no path traversal), and returns generic errors. Verify: PHPUnit
      `testExportDtcgRejectsTraversalId` with `../appinfo/info`.

## 7. Before the pull request

- [ ] 7.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`.
- [ ] 7.2 Open one exported file in Tokens Studio (or another DTCG tool) and record in the PR body
      whether it loads, and what it shows for the colour objects.
