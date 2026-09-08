Note: no OpenRegister schemas are involved in this change — the audit is pure filesystem work over
`css/`, `token-sets.json` and `design-systems.json`, and it persists nothing. There is no Seed Data
section and no seed task. There is no lifecycle/aggregation/notification behaviour either, so the
ADR-031 declarative-vs-imperative notification-dialect distinction does not apply.

Task numbering follows `MAKEOVER-PLAN.md` stage 1 (1.1–1.7) so the plan's checkboxes map one-to-one.

## 1. Spec and Design (plan task 1.1, 1.7)

- [x] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md`, and the spec delta on
      `openspec/specs/token-sets/spec.md` (two ADDED requirements — vocabulary completeness and the
      admin-dropdown surface; no MODIFIED requirement, the existing "Token Set CSS Structure"
      requirement stays true as written).
- [x] 1.7 Record the measured baseline table (48 sets, per-set missing/foreign/primary/verdict) in
      `design.md`, alongside the reconciliation against `MAKEOVER-PLAN.md` Appendix B — 41 sets fail,
      not 31, and `summer-breeze` is not auditable. Regeneratable with
      `node scripts/audit-token-sets.mjs --json`.

## 2. Audit Service (plan task 1.2)

- [x] 2.1 Create `lib/Service/TokenSetVocabularyAuditService.php` (SPDX docblock, `@spec` tags
      referencing the two new requirements) with `auditSet()`, `auditAll()`, `warningsFor()`,
      `declaredVocabulary()` and `nldesignConsumingSystems()`. `auditSet()` returns
      `{id, designSystem, auditable, missingRequired[], foreignNldesignNames[], primaryMismatch,
      declaredPrimary, cssPrimary, complete}`.
- [x] 2.2 Declare `REQUIRED_TOKENS` as a public 26-name constant (design.md decision 1) and reuse
      `CssParserService` for declaration parsing, stripping comments first because that parser does
      not (design.md decision 2).
- [x] 2.3 Memoise the vocabulary scan and the consuming-systems scan per app root, so auditing all
      48 sets walks the CSS tree once rather than 48 times.

## 3. PHPUnit Gate (plan task 1.3)

- [x] 3.1 Create `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` holding the 41 measured
      known-incomplete ids plus a `$comment` block stating the shrink-only contract and that the
      array MUST be empty when stage 2 closes.
- [x] 3.2 Create `tests/Unit/TokenSetVocabularyTest.php` (no Nextcloud runtime, mirroring the
      `TokenSetContrastAuditTest.php` static-inventory pattern) with:
      `testEveryShippedSetIsCompleteOrAllowListed` (fails with a per-set list of missing/foreign/
      mismatched names), `testAllowlistHasNoStaleEntries` (a listed set that now passes fails the
      gate, so the list can only shrink), `testAuditDistinguishesCompleteFromIncompleteSets`
      (non-vacuity), `testSetsOfNonConsumingDesignSystemsAreNotAudited`,
      `testNodeMirrorRequiresTheSameTokens` (drift guard against `scripts/audit-token-sets.mjs`), and
      `testRequiredTokensAreThemselvesInTheVocabulary` (the required list can never demand a token
      nothing reads).

## 4. Node CLI (plan task 1.4)

- [x] 4.1 Create `scripts/audit-token-sets.mjs`: dependency-free Node mirror of the same three rules,
      reading the same allow-list fixture. Prints a table (set, design system, missing count, foreign
      count, primary match, verdict) plus a summary; `--verbose` lists every offending token name,
      `--json` emits machine-readable results, `--check` exits non-zero on an unlisted failure or a
      stale allow-list entry.
- [x] 4.2 Register `npm run audit:token-sets` and `npm run audit:token-sets:check` in `package.json`.
- [x] 4.3 Verify the Node and PHP implementations agree: both were run over all 48 sets and match
      field-for-field (the run surfaced and fixed the `[a-z0-9-]` vs `[\w-]` asymmetry, design.md
      decision 4).

## 5. Admin Surface (plan task 1.5)

- [x] 5.1 Add `TokenSetVocabularyAuditService::warningsFor()` returning a single
      `kind: 'incomplete'` entry, and merge it into `TokenSetService::applyWarnings()` after the
      contrast warnings (new constructor dependency; the six existing tests that construct
      `TokenSetService` directly are updated).
- [x] 5.2 Add the hidden-by-default `#nldesign-token-set-completeness-badge` element to
      `templates/settings/admin.php`, next to the design-system badge.
- [x] 5.3 In `js/admin.js`: `incompleteWarningFor()`, `incompleteWarningLines()` and
      `updateCompletenessBadge()` (called from both `change` and initial-paint paths); split the apply
      dialog's banner into `buildContrastWarningHtml()` (now filtering `kind === 'incomplete'` out)
      and `buildIncompleteWarningHtml()`, both emitted by the renamed
      `buildTokenSetWarningsHtml()`; add "Incomplete set" as the top-priority third state in
      `renderCustomSetList()`'s badge.
- [x] 5.4 Extract the 5 new `t('thematiq', ...)` strings into `l10n/en.json`
      (`node tests/l10n/check-l10n.js --write`) — the only l10n file CI gates
      (`code-quality.yml` runs `test:l10n`, NOT `test:l10n:completeness`) — translate the Dutch side
      by hand in `l10n/nl.json`, and rebuild those two browser catalogues.
      The other 35 locale files are deliberately NOT touched: backfilling them writes the English
      source as a placeholder value, which is a 70-file diff carrying no translation. `.prettierignore`
      already records `l10n/` as "written by the translation workflow", so those files belong to that
      workflow, not to a feature commit. `node tests/l10n/check-l10n-completeness.js` therefore
      reports 5 missing keys per locale until the translation workflow next runs — expected, and not
      a CI failure.

## 6. Repository Hygiene (plan task 1.6)

- [x] 6.1 Add `/css/custom-css.css` to `.gitignore` next to `/css/custom-overrides.css`, with the
      reason recorded inline.
- [x] 6.2 Confirm the file is created on demand and its absence is harmless: `CustomCssService::write()`
      writes it atomically (temp file + rename) and `CssInjectionService::injectOverrideStyles()`
      emits the stylesheet only when the feature is enabled AND the file has content — so unlike
      `custom-overrides.css` nothing needs an `ensureExists()` on boot. The stray dump that was in
      this worktree is gone.

## 7. Quality Gates

- [x] 7.1 `php -l` clean on every new/changed PHP file, and `node --check js/admin.js` clean.
- [x] 7.2 `node tests/l10n/check-l10n.js`, `node tests/l10n/check-l10n-completeness.js` and
      `node scripts/build-l10n-js.js --check` all green.
- [x] 7.3 `npm run audit:token-sets:check` green against the committed allow-list.
- [ ] 7.4 Run `composer check:strict` (PHPCS, PHPMD, Psalm, PHPStan) over the new/changed PHP files
      and fix any findings. **Not run: no `composer`/`vendor/` and no PHP CLI on the authoring
      machine** — the PHP was syntax-checked inside the running `nextcloud` container instead.
- [ ] 7.5 Run the full `phpunit` suite (in particular `TokenSetVocabularyTest`, `TokenCssShapeTest`,
      `TokenSetContrastAuditTest`, and the six updated `TokenSetService` tests). **Not run: no
      `vendor/`, so PHPUnit cannot be invoked locally.** The audit rules themselves were verified by
      running the PHP service directly against all 48 sets and diffing the result against the Node
      CLI.
- [ ] 7.6 Add or extend a Playwright spec-coverage test for the "Incomplete set" badge and tooltip,
      or apply a reason-bearing `@e2e exclude` to the backend-only scenarios.
- [x] 7.7 Add the `CHANGELOG.md` "Unreleased" entries (Added: the audit, the npm CLI, the badge;
      Changed: the `css/custom-css.css` gitignore). `appinfo/info.xml` `<version>` is deliberately
      NOT bumped by hand — every commit that has ever touched it is a `chore(release)` from the
      release workflow, which is also what moves the `?v=` cache-buster for the changed `js/admin.js`.
