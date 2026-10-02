## 1. Data for the editor

- [x] 1.0 Bring `TokenRegistry::getInternalTokens()` into the editor payload, with a group per token and a translated heading and Dutch entry per group (12 groups plus Advanced), and verify `npm run test:l10n` passes
- [x] 1.1 Add the group, `advanced`, Nextcloud's light and dark value and the note to the payload, and record its size before and after in the PR body
- [x] 1.2 Pass the count, and verify the editor states it (`admin-token-editor-scale.spec.js`) and the docs state it (`TokenReferenceDocsTest::testTheDocsStateTheEditableCount`)

## 2. Structure

- [x] 2.1 Render the component groups and the Advanced group as disclosure buttons with empty panels, building rows on first open, and verify page load builds no group's rows
- [x] 2.2 Keep unsaved values across close and reopen, and verify an edit survives closing its group
- [x] 2.3 Show the Advanced warning as text above the first field, and verify the group starts collapsed with `aria-expanded="false"`

## 3. Search

- [x] 3.1 Filter by label, CSS name and translated heading, and verify the search scenarios, including the Dutch `datumkiezer` case
- [x] 3.2 Restore the previous open state when the field is cleared

## 4. Rows

- [x] 4.1 Show Nextcloud's value for the current theme, resolving `var()` colours with a probe element, associated through `aria-describedby`
- [x] 4.2 Show the inventory note, associated through `aria-describedby`
- [x] 4.3 Offer the dark field on settable theme rows and internal colour rows, write it into the dark scopes, and fix the writer that dropped a settable colour's own dark value (`CustomOverridesServiceEveryTokenTest`, red before the fix)

## 5. Import and export

- [x] 5.1 Verify with PHPUnit that an internal token and its dark value survive the round trip, and that a file from before this change still reads (`CustomOverridesServiceEveryTokenTest`)

## 6. Documentation and verification

- [x] 6.1 Generate the count into `docs/features/token-editor.md` and `import-export.md` from the registry, and remove the hardcoded per-tab counts
- [x] 6.2 Add Dutch translations for every new string, and verify `npm run test:l10n` passes
- [x] 6.3 Run an axe scan (axe-core 4, WCAG 2.2 AA tags, real Chrome) of the opened editor with the date picker and Advanced groups expanded: no violation in any new element; the 246 contrast findings all sit in the locked base-token rows, which WCAG 1.4.3 exempts as inactive and which predate this change
- [ ] 6.4 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict`, `npm run lint`, `npm run format` and `npm run test:l10n` once before push, and record the exit codes in the PR body

Changed at build:
- The four tabs keep building their rows on load, as before: the playground reads those panels. Only the groups, where the 453 new rows live, build on open.
- The curated component tokens stay in their tabs, where the playground and the locks read them; the component groups hold the internal and Conduction tokens.
- Brand rows keep the dark line #809 gave them.

Reminders, not tasks:
- ADR-004: the panel stays vanilla JavaScript; no new framework.
- ADR-005: the editor never writes a name the registry does not hold; the server checks again on save.
- ADR-009: the user docs and the theme-author pages from changes 2 and 3 link to each other.
- Inherited findings on untouched lines go in one sentence in the PR body.
