Note: no OpenRegister schemas are involved. This is filesystem and appconfig work over
`css/tokens/`, `token-sets.json` and `design-systems.json`, and it persists nothing new, so
there is no Seed Data section and no seed task. No lifecycle, aggregation or notification
behaviour, so ADR-031's declarative-vs-imperative distinction does not apply.

## 1. Spec and design

- [x] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md`, and the spec delta on
      `openspec/specs/token-sets/spec.md` (one MODIFIED requirement — "Only Fully Functional
      Brands Are Selectable" — and one amended coverage requirement).
- [x] 1.2 Resolve the `SELECTABLE_SHIPPED_SETS` docblock's contradiction against the measured
      state: it claimed `cunningham` "IS LISTED WHILE STILL FAILING THAT AUDIT" and that "its
      id therefore STAYS in" the allow-list. Measured: `cunningham` declares all 26 required
      tokens, the audit reports it complete, and the fixture reads `"sets": []`. Both halves
      were false, so the paragraph is gone with the constant it documented.

## 2. The bridge bar was wrong, so correct it before building on it

- [x] 2.1 Resolve every fallback in `css/systems/nldesign/utrecht-bridge.css`: of 84
      declarations, 42 fall back to a `var(--nldesign-*)` token the set declares, 38 to a
      non-colour literal, 3 to a colour literal and 1 to another `var()`.
- [x] 2.2 Resolve the real cascade (defaults → set → bridge) per set as a control, rather than
      resting on reading the CSS: `amsterdam` and `rijkshuisstijl` are both bridge-zero and
      differ on 32 of 84 component tokens, with buttons at `#004699` and `#154273`.
- [x] 2.3 Demote `bridge` from a bar to reported-only (`BARRED_DIMENSIONS`), keep it measured
      and published, and correct the legend in `docs/reference/token-set-coverage.md` and the
      wrong sentences in the preceding change's `proposal.md` and `design.md`.
- [x] 2.4 Make the demotion impossible to do silently: a `$reported` reason of at least 20
      characters is required for every non-gated dimension, a gated dimension must not carry
      one, and an allow-list entry under a non-gated dimension fails. The fixture's `$comment`
      records the 42 → 34 movement and its cause.
- [x] 2.5 Prove each half can fail, with probes in `tests/vitest/tokenSetCoverage.spec.js`:
      an empty `$reported` is reported reasonless, a `$reported` entry for a gated dimension is
      reported stale.

## 3. Delete the constant

- [x] 3.1 Delete `TokenSetService::SELECTABLE_SHIPPED_SETS` and rewrite
      `getSelectableTokenSets()` around two measured conditions: a `token-sets.json` entry, and
      no `incomplete` warning. The three never-filtered ids survive both.
- [x] 3.2 Read the vocabulary verdict off the catalogue entry's own `warnings` rather than
      re-auditing: `getAvailableTokenSets()` already ran the audit for every set through
      `applyWarnings()`, so a second call would double the work per admin page render and make
      the dropdown and the "Incomplete set" banner two answers to one question.
- [x] 3.3 Update the two comments that named the constant
      (`SettingsController::getAvailableTokenSets()`, `Admin::getForm()`).

## 4. The gate against silent re-narrowing

- [x] 4.1 `TokenSetServiceSelectableTest::testEveryNamedShippedSetTheAuditPassesIsOfferedOnTheRealCatalogue`,
      run against the real `css/tokens/` and `token-sets.json`, asserting the picker equals
      every named set the audit passes, that at least one shipped file has no manifest entry and
      none such is offered, and that the count is above 50.
- [x] 4.2 Rebuild the synthetic catalogue in that class so both conditions are exercised: a
      named nldesign set declaring only a primary (audit reports incomplete) and a token file
      with no manifest entry. Without them the filter could stop filtering and every assertion
      would still pass.
- [x] 4.3 Point the active-set and group-mapping survival tests at those two sets, because a
      rule asserted on a set that is offered anyway proves nothing.
- [x] 4.4 PROVE 4.1 fails: reinstate a two-set narrowing and record what it printed.
- [x] 4.5 Update the two e2e specs that read the deleted constant out of the PHP source, and
      point their survival-rule targets at a shipped file with no manifest entry.

## 5. Verification

- [x] 5.1 `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` — once, before push.
- [x] 5.2 `bash scripts/token-set-gate.sh`.
- [x] 5.3 The full vitest suite, `npm run format`, `lint`, `stylelint`, `test:l10n`,
      `check:l10n-js`, `check:manifest`, `test:fonts`, `audit:token-sets:check`,
      `test:token-set-coverage`.
- [x] 5.4 `openspec validate every-shipped-set-selectable --strict`.
- [ ] 5.5 Live check, owed to the coordinator: the admin dropdown offers 58 sets; applying
      `leiden` and `vng` works; `conduction` is absent from the dropdown and present in the
      public catalogue. Lanes do not touch a running instance.

## 6. Next waves

- [x] 6.1 `denhaag`'s component layer, from `scripts/sources/denhaag/*.css` — already vendored
      under EUPL-1.2 for `generate-denhaag-bridge.mjs`, so the mapping comes from its own
      published source. The one of the eight that can be done without inventing anything.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 1.1 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.2 The other seven bridge-zero sets. Each declares 3 to 9 palette steps of its own
      (`epe` 226), and a brand file needs roughly 43 ramp steps, so this needs a sourced
      palette per municipality rather than a derivation. `rijkshuisstijl` is the highest-risk
      of them: it is the app's default theme, so regenerating it changes what every
      unconfigured instance looks like.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 1.2 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.3 The 453 internal tokens no set declares, the 45 unreachable theme variables, and the
      18 sets with no recorded source — carried over from the preceding change's section 6.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 2.1, 2.2 and 3.1 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.4 Check the contrast report's `primary/text` pair against the ink token an administrator
      expects: the coordinator measured `#333333` on `#ffffff` at 12.63 while the report reads
      11.98 for `vng`, so the pair is not the one they expected. Both pass, so it is a labelling
      question rather than a defect, but the report should say which tokens it compared.
      Done 2026-10-09 (build/openspecs-1): the report already names the tokens it compared: docs/reference/contrast-report.json `pairs` maps `textRatio` to `--nldesign-color-primary-text` on `--nldesign-color-primary` and `uiRatio` to `--nldesign-color-primary` on `--nldesign-color-background`. vng's 11.98 is white on #003865, not body text on white, so the 12.63 measured on #333333/#ffffff is a different pair. No defect.
