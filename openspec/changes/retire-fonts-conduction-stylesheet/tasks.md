# Tasks: retire-fonts-conduction-stylesheet

## 1. Test first

- [x] 1.1 `tests/vitest/fontStylesheetsAreLinked.spec.js`: every stylesheet under `css/` (token sets
      aside) that declares an `@font-face` is in a design system's list, is `css/fonts.css`, or is
      the opt-in Marianne layer. Red before the change on `css/fonts-conduction.css`.
- [x] 1.2 The same file checks that Figtree and IBM Plex Mono 400 are still declared, with their
      files present, by `css/systems/nldesign/fonts.css`, and that Inter is declared by
      `css/systems/lasuite/fonts.css`.

## 2. Remove and re-source

- [x] 2.1 Remove `css/fonts-conduction.css`.
- [x] 2.2 `css/tokens/frankendesk.css`: the two comments name `css/systems/lasuite/fonts.css`.
- [x] 2.3 `lib/Service/TokenSetFontAuditService.php`: the docblock names only `css/fonts.css`.
- [x] 2.4 `openspec/parity/capabilities.json`: the self-hosting evidence cites
      `css/systems/nldesign/fonts.css`.
- [x] 2.5 `scripts/build-fonts.js`: the IBM Plex Mono note and the licence table comment speak of
      the file in the past tense; both font layers regenerated, `npm run test:fonts` reports no drift.

## 3. Checks

- [x] 3.1 portaliq does not link the file (its development branch and the organisation's default
      branches searched on 12 Oct 2026).
- [x] 3.2 Touched tests, `test:fonts`, prettier and `php -l` on the touched files.
