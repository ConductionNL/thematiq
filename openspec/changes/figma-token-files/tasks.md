# Tasks: figma-token-files

- [x] 1 `FigmaTokenWriter`: the `figma`, `tokens-studio` and `tokens-studio-legacy` formats from
      the W3C document and the set's declarations.
- [x] 2 `DtcgExportController::export()`: the `format` parameter, 400 for an unknown one, the file
      name per format.
- [x] 3 `TokensStudioSets`: the token sets of a single file merged in `tokenSetOrder`, a switched
      off set left out.
- [x] 4 `FigmaTokenReader`: both spellings, the Tokens Studio type names, the CSS name from the
      path, reference cycles and sums reported.
- [x] 5 `TokenSetConverterService::convert()`: the reader first, its findings in the report.
- [x] 6 Admin page: the format list next to the download button, a line about Figma at the upload;
      strings in English and Dutch.
- [x] 7 `FigmaTokenFilesRoundTripTest`: every shipped set in each format; fixtures in both
      spellings. `FigmaTokenReaderTest` and `DtcgExportControllerTest` for the small cases.
- [x] 8 `docs/features/figma-tokens.md`, and a copy for the plugin lane.
