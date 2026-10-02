## 1. Registry

- [x] 1.1 Record a status for every `component`, `slot` and `conduction` entry (453 settable, 47 excluded with a reason), generate `scripts/mapping/internal-tokens.json` from it, and verify its token list equals the settable list (inventory guard)
- [x] 1.2 Read the map into `TokenRegistry::getInternalTokens()`, and verify `isEditable('--nldesign-nc-dp-hover-color')` is true and a runtime variable has no token (`InternalScopesServiceTest`)
- [ ] 1.3 Moved to `token-editor-at-scale`: translated headings per owner component belong with the editor that first shows them

## 2. Rules for set tokens

- [x] 2.1 Build the internal scopes at render time from the set's files, its dark variant and the overrides, with the specificity bump, and verify one set token and one override produce exactly two rules (`InternalScopesServiceTest`)
- [x] 2.2 Emit them inline after the component scopes, including for a set on no design system, and verify the manifest order (`CssInjectionServiceTest::testASetWithAnInternalTokenCarriesTheInternalScopesAfterTheComponentScopes`)
- [x] 2.3 Verify nothing set gives no rule, an override save shows on the next build, and a dark-only token is scoped to dark (`InternalScopesServiceTest`)
- [x] 2.4 Per-group resolution: the rules are a function of the resolved set, which `inject()` already resolves per group; no separate code path

## 3. Proof in the browser

- [x] 3.1 Verify in Chrome that every selector the map carries is valid, and that a declared variable, a read-only slot, a `--cn-*` tile, a `:root` variable and a dark-only token are reached, with the component's own declaration injected after thematiq's (`npm run test:internal-scopes`); a control run without the specificity bump fails the declared-variable checks
- [ ] 3.2 Set ten internal tokens across five components on a live instance and screenshot each. Deferred to the chain's final live check on 8080, which needs the instance upgraded to this release

## 4. Documentation and verification

- [x] 4.1 Generate `docs/reference/internal-tokens.md` from the map, add the "Component variables" section to the feature page, and verify the docs site builds
- [ ] 4.2 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict`, `npm run lint`, `npm run format` and `npm run test:l10n` once before push, and record the exit codes in the PR body

Reminders, not tasks:
- ADR-005: the rules carry only names the map knows and `var()` references; no value from a set or an upload reaches them.
- ADR-010: hardcoded colours become settable, so the editor must show the stock value; that lands in change 4 (the map records `stock`).
- Inherited findings on untouched lines go in one sentence in the PR body.
