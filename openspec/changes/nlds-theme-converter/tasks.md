# Tasks: NLDS theme to Nextcloud token set converter

Section numbers in brackets were the planning task numbers.
Tick a box when the work is merged to `development`, not when it is started.

## State on 2026-09-10

Both runtimes and the CLI are in and verified against each other; the tests, the admin
surface and the regeneration are not.

**Parity is measured, not assumed.** The PHP service and `js/lib/tokenConverter.js` were
run over the same two fixtures — a built theme CSS with `var()` chains, inline `data:`
logo URIs and policy-skipped tokens, and a second one whose vendor prefix this app has
never seen — and the emitted CSS is **byte-identical**, provenance hash included, with
structurally equal reports (64 and 53 entries). That is the check §5.4 will automate; it
has been performed by hand, not wired into a suite.

Three carve-outs in the JS runtime, all deliberate: DTCG (input B) is refused with a 422
pointing at the server, because `DesignTokensMapper` owns those semantics and a second
parser is exactly the drift this change exists to prevent (input C, Style Dictionary, IS
implemented, which is why §3.4 stays open rather than ticked); `--write` does not call the
dark-variant generator, which needs PHP; and `generate-brand-set.mjs` is not absorbed, so
the nightly sync still emits the raw vocabulary.

Landed: the mapping table (§2.1–2.3, plus two reason codes the fallbacks needed —
`derived-from-brand` and `nextcloud-default-used` — and one rule reordering, because
`-text-muted`, `-border-dark` and `-header-border-bottom` all measure against
`--nldesign-color-nav-background` and a guard can only see targets already resolved),
`TokenSetConverterService` (§5.1–5.2), the converter ahead of the validator in
`upload()` with `content`/`sourceName` accepted (§6.2), and the widened validator
vocabulary (§7.1).

Verified by running the service inside the app container against a built theme CSS with
`var()` chains, palette steps, policy-skipped tokens, an external `url()` and a dangling
reference: input A detected from content, chains resolved to literals, palette moved off
`--nldesign-`, 26/26 required tokens emitted, `--nldesign-color-primary` equal to the
manifest `primary_color`, the three audited contrast pairs at 4.64:1 / 11.63:1 / 17.40:1,
one flat `:root` with no at-rules, and every source token in the report with a reason code
that exists in the table.

Not done, and each one is why stage 4 cannot close: §2.4, §3.4 (DTCG in JS) and §3.9
(vitest), §4.2–4.4 (dark variant on `--write`, absorbing `generate-brand-set.mjs`, the
npm scripts), §5.3–5.4 and §7.2 (the tests), §6.1/6.3/6.4/6.5 (the paste box, the report
rendering, the no-reload dropdown append, the l10n), §8 (regenerating the 39 sets and
emptying the allow-list) and §9 (the gates). PHPUnit and vitest could not be run at all
on the authoring machine: there is no `vendor/` and no `composer`, so §9.2 stands open
even for the code that is in.

## 1. Spec and Design (plan 4.1 - 4.4)

- [x] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md`, and the spec deltas on
      `token-set-converter` (new), `custom-token-sets` and `token-sets`.
- [x] 1.2 Record the measured baseline (39 incomplete / 7 complete / 2 not audited, and the
      per-set missing-token shape) in `design.md`, so the before/after is auditable.
- [x] 1.3 Answer plan decision 2 (no upstream sourcing; the input arrives from a human) and
      decision 4 (`summer-breeze` gets a semantic layer) in `design.md` decisions 3 and 10.
- [x] 1.4 Deduplication check: confirm nothing already does this. `DesignTokensMapper` owns DTCG
      and is reused, not replaced (design decision 4); `scripts/generate-brand-set.mjs` owns the
      ramp/role-layer fill and is absorbed (decision 5); `js/lib/tokenTransforms.js` owns
      `darkenHex()`/`groupDiagnosticsByReason()` and is reused (decisions 7 and 11);
      `CssParserService` owns declaration parsing on the PHP side.

## 2. Mapping table (plan 4.3)

- [x] 2.1 Create `scripts/mapping/nlds-to-nextcloud.json`: `version`, `converterVersion`,
      `componentPrefixes`, `rules[]` (`target`, `nextcloud`, `sources[]`, `fallback`, `transform`,
      `guard`, `when`, `reason`), `never[]` (`match`, `action`, `reason`) and `reasons{}`,
      transcribing plan Appendix A in full. 43 rules, 26 policy entries, 18 reason codes.
- [x] 2.2 Cover all 26 required semantic tokens from `TokenSetVocabularyAuditService::REQUIRED_TOKENS`
      with a rule, plus the derived `-rgb`, `-light`, `-light-hover`, `-hover`, focus, radius and
      `manifest:` targets. Verified: no required token is without a rule.
- [x] 2.3 Add the 13 stage 6 reason codes plus the 5 conversion-mechanics codes with their
      one-sentence copy. Verified: no code without copy, no copy without a producer.
- [ ] 2.4 Add the shared SHA-256 helper both runtimes use for the provenance block, and the test
      asserting every code the converter can emit exists in the table (and vice versa) as a
      permanent gate rather than a one-off check.

## 3. Browser/Node module (plan 4.3)

- [x] 3.1 Create `js/lib/tokenConverter.js`, dual-mode exactly like `js/lib/tokenTransforms.js`
      (`module.exports` under Node, `window.NldesignTokenConverter` in the browser, no
      `import`/`export`).
- [x] 3.2 Input detection in the fixed order of design decision 4, with a 422-equivalent error object
      for unrecognised content.
- [x] 3.3 CSS parsing for input A and D: selector blocks, declaration split, `var()` chain resolution
      with depth limit and cycle guard, at-rules skipped as `at-rule-not-converted`.
- [ ] 3.4 Style Dictionary / DTCG walk for input B and C, delegating the DTCG semantics that
      `DesignTokensMapper` already defines (alias `{a.b.c}`, `$type` dispatch, suffix table).
- [x] 3.5 Rule engine: first-matching-source, the closed transform set
      (`copy`, `darken`, `mix`, `rgbTriplet`, `alpha`, `radiusScale`), the single `contrast` guard.
- [x] 3.6 Emit the four-section `:root` file plus the provenance comment (design decision 5) and the
      `token-sets.json` manifest entry.
- [x] 3.7 Report builder: `{source, target, action, reason, value}` with
      `action in {applied, adapted, skipped, kept}` plus `counts`.
- [x] 3.8 Add-only mode for input D over an existing set (design decision 9), reporting
      `kept-existing-value` per untouched declaration.
- [ ] 3.9 `tests/vitest/tokenConverter.spec.js` with local fixtures only: `css/tokens/openwoo.css`
      (input D), the installed Rotterdam and Zwolle token packages (inputs A/B), a raw dump set such
      as `nijmegen` (the 959-foreign-name case), and a malformed paste. The suite writes
      `tests/Unit/fixtures/converter/<fixture>.expected.json` for the parity test.

## 4. CLI (plan 4.3)

- [x] 4.1 Create `scripts/convert-nlds-theme.mjs` over the module:
      `node scripts/convert-nlds-theme.mjs <input> --slug zwolle --name "Gemeente Zwolle"
      [--write] [--report report.json]`, printing the grouped report table.
- [ ] 4.2 `--write` updates `css/tokens/<slug>.css`, the `token-sets.json` entry and
      `css/tokens/<slug>.report.json`, then calls the dark-variant generator.
- [ ] 4.3 Absorb `scripts/generate-brand-set.mjs` (keep its ramp and role-layer logic, drop the
      separate entry point) and leave `scripts/generate-tokens.mjs` untouched for now
      (design decision 3).
- [ ] 4.4 Register `npm run convert:theme` and `npm run convert:theme:check` (the latter converts
      and fails when the on-disk file would change).

## 5. PHP runtime (plan 4.3)

- [x] 5.1 Create `lib/Service/TokenSetConverterService.php` (SPDX docblock, `@spec` tags, PHPCS
      `//end` markers) returning `{css, manifestEntry, report}` from raw content plus a slug, loading
      the same mapping JSON.
- [x] 5.2 Reuse `CssParserService` for parsing and `DesignTokensMapper` for the DTCG branch; no
      second DTCG parser.
- [ ] 5.3 `tests/Unit/Service/TokenSetConverterServiceTest.php`: per-input unit tests, the contrast
      guard, the `never` policy, `unresolved-var`, and the unrecognised-input error.
- [ ] 5.4 `tests/Unit/Service/TokenSetConverterParityTest.php`: same fixtures as 3.9, byte-equal CSS
      and structurally equal report against the vitest expectations.

## 6. Admin surface (plan 4.5)

- [ ] 6.1 `templates/settings/admin.php`: a labelled textarea plus Convert button in the
      "Custom token sets" section, next to the existing file picker; the picker keeps its
      `accept=".css,.json,.tokens.json"` and gains no new states.
- [x] 6.2 `CustomTokenSetController::upload()` accepts `content` and optional `sourceName` beside
      `file`, detects the input by content (not by extension), and returns `report` and `counts` in
      the response.
- [ ] 6.3 `js/admin.js`: submit the pasted content, render the report through
      `buildDiagnosticsFragment()` grouped by reason, and keep the existing "added and selectable"
      confirmation.
- [ ] 6.4 Append the new set to the dropdown client-side from the response, without a page reload.
- [ ] 6.5 Extract the new `t('thematiq', ...)` strings into `l10n/en.json` and translate to Dutch;
      run `npm run test:l10n:write` and `npm run test:l10n:completeness:write`.

## 7. Validator (plan 4.3, security)

- [x] 7.1 Widen the accepted vocabulary to `--utrecht-*`, `--ams-*`, `--denhaag-*` beside
      `--nldesign-*` and `--{slug}-*`; `isForbiddenValue()` unchanged.
- [ ] 7.2 Tests for the widened surface: a component-prefix declaration is accepted, a semicolon or
      comment marker in its value is still rejected, and an external `url()` never reaches the
      validator (dropped by the converter as `external-url-blocked`).

## 8. Regeneration and closing stage 1 (plan 4.5)

- [ ] 8.1 Regenerate the 39 incomplete sets with input D from their own files (design decision 6),
      one commit per batch with the report summary in the message.
- [ ] 8.2 Convert `summer-breeze` from its design-system layer (design decision 10).
- [ ] 8.3 Re-run the 7 complete sets in add-only mode; the diff MUST show additions only.
- [ ] 8.4 Regenerate `css/tokens/dark/*.css` with `php scripts/generate-dark-variants.php --force`.
- [ ] 8.5 Update the 39 `token-sets.json` entries (primary, background, provenance) and commit the
      per-set `css/tokens/<slug>.report.json` files; gitignore `css/tokens/custom-*.report.json`.
- [ ] 8.6 Empty `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` to `[]` and confirm
      `npm run audit:token-sets:check` and `TokenSetVocabularyTest` are green with 46 audited sets.

## 9. Quality gates

- [ ] 9.1 `php -l` clean on every new/changed PHP file; `node --check` clean on
      `js/lib/tokenConverter.js`, `js/admin.js` and `scripts/convert-nlds-theme.mjs`.
- [ ] 9.2 `npm run test:unit` (vitest) and the full `phpunit` suite green, in particular
      `TokenSetVocabularyTest`, `TokenCssShapeTest`, `TokenSetContrastAuditTest` and the two new
      converter tests.
- [ ] 9.3 `composer check:strict` (PHPCS, PHPMD, Psalm, PHPStan) over the new/changed PHP files.
- [ ] 9.4 `npm run audit:token-sets:check` green against the emptied allow-list.
- [ ] 9.5 Playwright spec-coverage for the paste path and the report block.
- [ ] 9.6 `CHANGELOG.md` "Unreleased" entries: the converter, the paste surface, the report, the 40
      regenerated sets, and the emptied allow-list.
- [ ] 9.7 Manual acceptance (design decision 3): paste a real `design-tokens.css` into the panel and
      confirm the header, primary and report match the theme — including the OpenWOO case, whose
      output is diffed against `css/tokens/openwoo.css`.
- [ ] 9.8 Run the hydra gates via WSL on the branch and record the coverage line.
