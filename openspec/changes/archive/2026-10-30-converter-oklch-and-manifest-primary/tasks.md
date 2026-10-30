# Tasks: converter-oklch-and-manifest-primary

- [x] 1 `parseColorAlpha()` in js/lib/tokenConverter.js reads `oklch()` and `oklab()`.
- [x] 2 `ColourLiteralParser::parse()` reads them the same way, through `ColorSpaceConverter`.
- [x] 3 Parity fixture `oklch`; `TokenSetConverterParityTest` covers it.
- [x] 4 `convert-nlds-theme.mjs`: `withPrimary()` and `--primary`.
- [x] 5 Tests: tests/vitest/converterOklchAndPrimary.spec.js, tests/Unit/Service/ColourLiteralParserOklchTest.php.
- [x] 6 Docs: docs/features/token-sets.md, the converter section.
