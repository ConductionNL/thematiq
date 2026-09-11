---
kind: code
---

## Why

Selecting `zwolle`, `tubbergen` or `haarlem` in the admin dropdown produces a Nextcloud that looks
like Rijkshuisstijl, not like Zwolle, Tubbergen or Haarlem. This is the "the examples do not look
correct" symptom, and it is not a styling opinion — it is a mechanical fact about the shipped files.

`css/systems/nldesign/theme.css`, `overrides.css` and `element-overrides.css` consume a fixed
`--nldesign-*` vocabulary (`--nldesign-color-primary`, `-primary-text`, `-header-background`,
`--nldesign-font-family`, `--nldesign-border-radius`, ...). The 17 hand-authored sets define it. The
other 31 are raw dumps from `nl-design-system/themes`: palette steps such as
`--nldesign-color-blue-40` plus `--nldesign-typography-*` and `--nldesign-space-*` names **nothing
in the app reads**. For those sets the cascade falls straight through to
`css/systems/nldesign/defaults.css`, whose values are Rijkshuisstijl's — so every one of them
renders as Rijkshuisstijl with, at best, a different header.

Nothing detects this today. `tests/Unit/TokenCssShapeTest.php` checks the file's *shape* (one flat
`:root` block, no at-rules) and `tests/Unit/TokenSetContrastAuditTest.php` checks the *contrast* of
the colours a set does define — but a set that defines nothing at all passes both: it is
structurally perfect and its (inherited Rijkshuisstijl) colours are WCAG-compliant. This change
makes "correct" a mechanical statement, enforced by a test, before any file is regenerated. It is
the first step of the theming makeover, and the NLDS→Nextcloud converter has no definition of done
without it.

## What Changes

- Add `lib/Service/TokenSetVocabularyAuditService.php`: given an app root and a set id, it returns
  `{missingRequired[], foreignNldesignNames[], primaryMismatch}` plus the resolved primary values and
  a `complete` verdict. Three rules, no colour judgement:
  1. **`missingRequired`** — the 26 required semantic tokens the set file itself does not declare,
     evaluated against the set file **alone** (layering it over `defaults.css` is exactly what hides
     the defect).
  2. **`foreignNldesignNames`** — `--nldesign-*` names the set declares that no CSS layer in the app
     declares a default for or reads. Raw palette steps belong under the brand prefix
     (`--zwolle-color-blue-40`), never under `--nldesign-`.
  3. **`primaryMismatch`** — `--nldesign-color-primary` disagrees with `token-sets.json`'s
     `theming.primary_color` after hex normalisation. One value, one source of truth.
- Add `tests/Unit/TokenSetVocabularyTest.php`: runs the audit over every `css/tokens/*.css` and fails
  with a per-set list of what is missing/foreign/mismatched. Ships with an explicit allow-list of the
  sets that fail today so CI stays green; the gate also fails on an allow-list entry that has started
  passing, so the list can only shrink.
- Add `tests/Unit/fixtures/token-set-vocabulary-allowlist.json`, read by **both** the PHPUnit gate and
  the Node CLI so they can never disagree about what is known-broken. It must be an empty array when
  stage 2 closes.
- Add `scripts/audit-token-sets.mjs` + `npm run audit:token-sets` (and `audit:token-sets:check`): a
  dependency-free Node mirror of the same three rules that prints a per-set table (set, design
  system, missing count, foreign count, primary match, verdict), so a token-set author can run the
  audit without a PHP runtime. A test asserts the two required-token lists have not drifted.
- Surface the verdict in the admin UI as a third badge state, "Incomplete set", next to the existing
  design-system and WCAG badges, with a tooltip listing exactly what is missing — carried on the
  existing `warnings` channel from `TokenSetService::applyWarnings()`, so the apply dialog raises it
  too. `TokenSetService` gains one constructor dependency.
- Gitignore `css/custom-css.css` next to `custom-overrides.css`: it is admin-generated runtime data
  written by `CustomCssService::write()` on demand, not app source.

## Capabilities

### New Capabilities
- None. This change adds requirements to the existing `token-sets` capability rather than a new one:
  the subject is what a shipped token set MUST contain, which is that spec's "Token Set CSS Structure"
  requirement, and the audit has no user-facing surface of its own beyond one badge on the existing
  dropdown.

### Modified Capabilities
- `token-sets`: two added requirements — "Shipped Token Set Vocabulary Completeness" (the mechanical
  definition of correct, the three rules, the not-auditable cases, and the allow-list contract) and
  "Incomplete Sets Are Surfaced In The Admin Dropdown" (the badge, the tooltip, and the reuse of the
  `warnings` channel).

## Impact

- **Measured baseline diverges from the planning estimate (31 sets) — 41 sets fail, and
  `summer-breeze` is not auditable at all.** Appendix B counted the sets that define *none* of the
  vocabulary; the plan's own stage-1 definition is stricter than that, and 11 further sets
  (`conduction-new`, `cunningham`, `frankendesk`, `hoog-contrast`, `lasuite`, `leiden`,
  `noaberkracht`, `opencatalogi`, `rijkshuisstijl`, `rotterdam`, `xxllnc`) fail it on partial gaps.
  See design.md "Baseline" for the full table and the per-set reasons. The allow-list therefore
  starts at 41 entries, not 31; the planning estimate ("exactly the 31 sets") is superseded by the
  measurement, and the converter's scope is correspondingly larger.

  The 41 above is the figure measured when this change was written. The fixture now holds 39, and
  the audit reports 7 complete where this measurement found 5: 41 + 5 and 39 + 7 are both 46, the
  audited total, so exactly two sets moved from incomplete to complete while the branch went on.
  The list is shrink-only and the gate fails on a listed set that has started passing, so that is
  the mechanism working rather than drift. The fixture is the authority; a number written into
  prose is a snapshot, which is why the test's own docblock no longer quotes one.
- **Behavioural change for shipped sets**: the apply dialog and the dropdown now raise a
  non-blocking warning for 41 of the 48 shipped sets. That is the point ("no silent drops"), but it
  is visible to every admin from this release on; stage 6 refines the copy and grouping.
- **Code**: `lib/Service/TokenSetVocabularyAuditService.php` (new),
  `lib/Service/TokenSetService.php` (one new constructor dependency, `applyWarnings()` merges the
  vocabulary warnings after the contrast ones), `js/admin.js` (badge, tooltip, banner; the apply
  dialog's `buildContrastWarningHtml()` becomes `buildTokenSetWarningsHtml()` over two banner types),
  `templates/settings/admin.php` (one badge element), `scripts/audit-token-sets.mjs` (new),
  `package.json` (two scripts), `.gitignore` (one entry), `l10n/*` (5 new keys, Dutch translated),
  `tests/Unit/TokenSetVocabularyTest.php` + fixture (new), and the six existing tests that construct
  `TokenSetService` directly.
- **No token set file is modified by this change.** Fixing the 41 sets is stage 2's job; stage 1 only
  makes the failure visible and mechanical.
- **No OpenRegister schemas, no lifecycle/aggregation/notification behaviour** — the audit is pure
  filesystem work over `css/`, `token-sets.json` and `design-systems.json`. No Seed Data section
  applies and the ADR-031 declarative-vs-imperative distinction does not.
- **Dependencies**: none beyond the existing `CssParserService`.
