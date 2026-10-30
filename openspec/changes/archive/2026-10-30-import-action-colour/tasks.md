# Tasks: import-action-colour

- [x] 1 `scripts/mapping/nlds-to-nextcloud.json`: three rules, no fallback from the primary.
- [x] 2 `DesignTokensMapper::MAPPING`: four paths.
- [x] 3 `TokenSetConverterServiceTest`: a theme without an action colour gets none; a theme with
      one gets it, its hover and a readable text; a DTCG document brings it by path.
- [x] 4 `DesignTokensMapperTest`: the paths.
- [x] 5 The three converter fixtures regenerated: the table hash in their header.
- [x] 6 `docs/reference/tokens.md` and `docs/features/custom-token-sets.md`.
- [x] 7 `composer check:strict`, once, before the push.
