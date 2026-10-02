## 1. Data for the editor

- [ ] 1.0 Bring `TokenRegistry::getInternalTokens()` into the editor payload (change 3 kept the 453 internal tokens out of `getTokens()` until this editor can list them), with a translated heading and Dutch entry per owner component (moved here from change 3 task 1.3), and verify `npm run test:l10n` passes
- [ ] 1.1 Add owner, translated group heading, `advanced`, per-theme stock values and note to the registry payload in initial state, and verify its size before and after, recording both in the PR body
- [ ] 1.2 Pass the registry count in initial state, and verify a PHPUnit test that it equals `count(TokenRegistry::getTokens())`

## 2. Structure

- [ ] 2.1 Render component groups and the Advanced group as disclosure buttons with empty panels, building rows on first open, and verify a vitest case that page load builds only the open tab's rows
- [ ] 2.2 Keep unsaved values in `tokenEditorState` across close and reopen, and verify a vitest case that an edit survives closing its group
- [ ] 2.3 Show the Advanced warning as text above the first field, and verify the two Advanced scenarios in Playwright

## 3. Search

- [ ] 3.1 Build the flat search index and filter by label, CSS name and heading, and verify the three search scenarios in Playwright, including the Dutch `datumkiezer` case
- [ ] 3.2 Restore the previous open state when the field is cleared, and verify it in a vitest case

## 4. Rows

- [ ] 4.1 Show the stock value for the current theme, resolving `var()` expressions with a probe element, and verify "Stock value of a hardcoded colour" in Playwright
- [ ] 4.2 Show the inventory note, associated through `aria-describedby`, and verify "A note is shown"
- [ ] 4.3 Add the optional dark field to settable theme rows and internal colour rows, writing the token into the dark scopes through `CustomOverridesService`, and verify the three dark-value scenarios in Playwright

## 5. Import and export

- [ ] 5.1 Export and import every registry token and its dark value, and verify the three round-trip scenarios with PHPUnit, including an overrides file from before this change

## 6. Documentation and verification

- [ ] 6.1 Generate the token count in `docs/features/token-editor.md` and `import-export.md` from the registry, and verify no docs page states a hardcoded count
- [ ] 6.2 Add Dutch translations for every new string, and verify `npm run test:l10n` passes
- [ ] 6.3 Run an axe scan of the opened editor with one component group and the Advanced group expanded, and verify zero WCAG 2.2 AA violations
- [ ] 6.4 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict`, `npm run lint`, `npm run format` and `npm run test:l10n` once before push, and record the exit codes in the PR body

Reminders, not tasks:
- ADR-004: the panel stays vanilla JavaScript; no new framework.
- ADR-005: the editor never writes a name the registry does not hold; the server checks again on save.
- ADR-009: the user docs and the theme-author pages from changes 2 and 3 link to each other.
- Inherited findings on untouched lines go in one sentence in the PR body.
