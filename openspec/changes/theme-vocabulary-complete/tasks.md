## 1. Tokens and status

- [x] 1.1 Add an `--nldesign-*` token name for each of the 45 theme variables to `variable-status.json` with status `settable`, moving each `overrides.css` reason into its `note`, and verify the inventory guard reports 111 of 111 theme entries mapped or settable, minus `icon`
- [x] 1.2 Flag the 13 structural variables `advanced: true`, and verify a unit test lists exactly those 13
- [x] 1.3 Register the 45 in `TokenRegistry` and the four `*Tokens.php` files with tab, type and `advanced`, and verify `TokenRegistry::isEditable('--color-mark')` and `isEditable('--header-height')` both return true
- [x] 1.4 Keep `--icon-*` and `runtime` entries unregistered, and verify a PHPUnit test that a POST setting `--icon-download-dark` returns 400
- [x] 1.5 Make `CustomOverridesService` store an override of a settable variable as its `--nldesign-*` token, with no derived dark value, and verify a PHPUnit test on the written file plus the "A light-only admin value leaves dark mode alone" scenario in Playwright

## 2. Stylesheet

- [x] 2.1 Extend `scripts/generate-component-scopes.mjs` with the theme section from design.md, writing `css/theme-scopes.css`, and verify `npm run test:component-scopes` fails on a stale file and passes after regeneration
- [x] 2.2 Inject `theme-scopes.css` between the design-system layers and `component-scopes.css` in `CssInjectionService`, and verify the stylesheet manifest test lists it in that position
- [x] 2.3 Leave the 45 tokens out of every `defaults.css`, and verify a unit test that none of them is declared there
- [x] 2.4 Add the same capture and re-scope to the lasuite bridge, and verify the lasuite bridge-coverage test accounts for the 45
- [x] 2.5 Add the zero-specificity dark reset for the 45 tokens, after checking on 8080 which element carries the dark theme attribute on the login, workspace and public share pages, and verify "A set gives a light value only" and "A set gives a light and a dark value" in Playwright

## 3. Contrast audit

- [x] 3.1 Add the selection pair and the highlight pair to the contrast audit, and verify PHPUnit cases for the three scenarios in the spec, including the failing `#222222` highlight

## 4. Proof in the browser

- [x] 4.1 Before the stylesheet lands, record the computed value of every `theme` entry, in light and dark, and save it as a fixture (done in real Chrome by `tests/css/check-theme-scopes-cascade.mjs`, from the inventory's per-theme values and the real stylesheets; the live 8080 pass follows the merge of the chain)
- [x] 4.2 After it lands, record the same values with the same set, and verify the two recordings are identical (111 variables x 2 schemes, 0 differ)
- [x] 4.3 Add browser checks for "Unset under the dark theme", "A set gives the search highlight a colour", "A set gives a light value only" and a settable variable inside an open modal, and verify each fails before 2.2 and passes after (the cascade check fails 6 of its 7 set checks with theme-scopes.css left out)

## 5. Documentation and verification

- [x] 5.1 Regenerate `mappings.md` and update `docs/features/token-editor.md` and `import-export.md` to the registry's real count, and verify no docs page still says 53
- [x] 5.2 Add a theme-author page listing the 45 new tokens with their notes and the two audited pairs, and verify the docs site builds
- [ ] 5.3 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict`, `npm run lint`, `npm run format` and `npm run test:l10n` once before push, and record the exit codes in the PR body

Reminders, not tasks:
- ADR-005: the editor still refuses unregistered names; nothing new is writable beyond the 45.
- ADR-010: the two contrast pairs are this change's WCAG deliverable.
- New editor labels go through `t('thematiq', ...)` with Dutch translations.
- Inherited findings on untouched lines go in one sentence in the PR body.
