## 1. Registry

- [ ] 1.1 Generate an `internal` section in `scripts/mapping/component-tokens.json` from every non-excluded `component`, `slot` and `conduction` inventory entry, with owner, selectors and type, and verify its count equals the inventory's settable count for those classes
- [ ] 1.2 Read the internal section into `TokenRegistry::getComponentTokens()`, and verify `isEditable('--nldesign-nc-dp-hover-color')` is true and `isEditable` for a runtime entry is false
- [ ] 1.3 Add a translated heading per owner component, and verify `npm run test:l10n` passes with Dutch entries for every heading

## 2. Bridge for slots

- [ ] 2.1 Generate `css/internal-bridge.css` for `slot` entries and read-only `--cn-*` names, and verify the drift check fails on a stale file
- [ ] 2.2 Inject it directly after `component-scopes.css`, and verify the stylesheet manifest test lists the order from the spec

## 3. Scopes for declared variables

- [ ] 3.1 Write the internal scopes writer: set declarations plus saved overrides, looked up in the inventory, written to `css/generated/internal-scopes-{set}.css` with the specificity bump, and verify a PHPUnit test with one set token and one override produces exactly two rules
- [ ] 3.2 Call the writer on set apply, on override save, and from a repair step on install and upgrade, and verify each path with a unit test
- [ ] 3.3 Inject the file for the resolved set, including per-group resolution, and verify a test where two groups get two different files
- [ ] 3.4 Write an empty file when nothing is set, and verify the "Nothing set means an empty file" scenario

## 4. Proof in the browser

- [ ] 4.1 Record computed values of every internal inventory entry on Files, Text (with a code block), Calendar (date picker), Viewer (audio) and one Conduction dashboard, in light and dark, before and after, and verify the two recordings are identical
- [ ] 4.2 Add Playwright tests for "A read-only slot is reached", "A variable the component declares itself is reached" and "A Conduction dashboard tile is reached", and verify each fails before the layer lands
- [ ] 4.3 Set ten internal tokens across five components on 8080 and screenshot each, and verify every one changes what its component shows

## 5. Documentation and verification

- [ ] 5.1 Add a theme-author page listing internal tokens per component, generated from the registry, and verify the docs site builds
- [ ] 5.2 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict`, `npm run lint`, `npm run format` and `npm run test:l10n` once before push, and record the exit codes in the PR body

Reminders, not tasks:
- ADR-005: the writer only emits names the inventory knows and values that pass `CustomTokenSetValidator::validateDeclarations()`; nothing from an upload reaches the file unchecked.
- ADR-010: hardcoded colours become settable, so the editor must show the stock value; that lands in change 4.
- Inherited findings on untouched lines go in one sentence in the PR body.
