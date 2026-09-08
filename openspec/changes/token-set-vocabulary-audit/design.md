## Context

Three things already guard the shipped token sets, and none of them can see the defect this change
targets:

- `tests/Unit/TokenCssShapeTest.php` guards the file's **shape**: exactly one flat `:root { }` block,
  no at-rules, no other selector. A raw upstream dump satisfies it perfectly.
- `tests/Unit/TokenSetContrastAuditTest.php` (via `ShippedTokenSetAuditService`) guards the
  **contrast** of the resolved colours. It resolves `css/tokens/{id}.css` **layered over**
  `css/systems/nldesign/defaults.css`, because a contrast ratio must be computed on the value that
  actually renders. A set that declares nothing therefore audits the Rijkshuisstijl defaults, which
  are WCAG-compliant — so it passes.
- `tests/validate-manifest.js` guards `token-sets.json`'s **structure**, not its agreement with the
  CSS.

The missing guard is the one about **vocabulary**: does the set declare the names the design
system's own stylesheets read? `css/systems/nldesign/theme.css`, `overrides.css` and
`element-overrides.css` read a fixed `--nldesign-*` set. When a token set declares none of it, the
cascade falls through to `defaults.css` and Zwolle renders as Rijkshuisstijl. That is invisible to
every existing gate, which is why the makeover plan puts this first.

## Goals / Non-Goals

**Goals:**
- Turn "the examples are correct" into a mechanical, per-set, per-token statement.
- Record the baseline so stage 2's progress is auditable rather than claimed.
- Give a token-set author a one-command local answer (`npm run audit:token-sets`) with no PHP
  runtime, no vendor install, no PHPUnit.
- Tell the admin, at the point of selection, that a set is incomplete and what is missing.
- Keep CI green today while making it impossible for the known-broken list to grow, or to silently
  stop shrinking.

**Non-Goals:**
- Fixing any token set. Not one `css/tokens/*.css` file is modified by this change; regeneration is
  stage 2.
- Re-auditing contrast. `ShippedTokenSetAuditService` owns that and is not touched.
- Judging values. The audit asks whether a token is declared, never whether the declared colour is
  the right one for the brand.
- Blocking an admin from applying an incomplete set. The warning is non-blocking, exactly like the
  existing contrast warning.

## Decisions

### 1. The required-token list is a fixed 26-name constant, not derived

`TokenSetVocabularyAuditService::REQUIRED_TOKENS` is the plan's stage-1 list, spelled out:

```
--nldesign-color-primary, -primary-text, -primary-hover, -primary-light, -primary-light-hover,
--nldesign-color-header-background, -header-text, -nav-background,
--nldesign-color-text, -text-muted, -border, -border-dark, -link, -link-hover,
--nldesign-color-error(+ -rgb), -warning(+ -rgb), -success(+ -rgb), -info(+ -rgb),
--nldesign-font-family, --nldesign-border-radius, -small, -large
```

It could have been derived from "every name `theme.css` reads" (118 names). It is not, for two
reasons: a derived list would silently grow whenever someone adds a `var()` to a theme layer,
turning an unrelated edit into 48 test failures; and many of those 118 names have a genuinely
sensible Rijkshuisstijl default that a brand has no opinion about (`--nldesign-animation-quick`,
`--nldesign-component-table-cell-padding-block`). The 26 are the ones where inheriting the default
means inheriting *someone else's brand*. A separate test
(`testRequiredTokensAreThemselvesInTheVocabulary`) asserts all 26 are names the app actually reads,
so the constant can never demand a token nothing consumes.

`--nldesign-color-primary-rgb` is deliberately **not** required: unlike the status colours, no layer
in the app reads it, so requiring it would fail that same subset test.

### 2. `missingRequired` is evaluated against the set file alone — the opposite of the contrast audit

`ShippedTokenSetAuditService::resolveDeclarations()` layers `defaults.css` under the set on purpose.
This service must not, and therefore deliberately does not reuse it: layering is the mechanism that
hides the defect. `stage 1`'s question is "what does this file itself say?", so the audit parses only
`css/tokens/{id}.css`. Both services share `CssParserService` so that "what counts as a declaration"
still has exactly one definition.

Comments are stripped **before** `parseDeclarations()` is called, because that parser does not strip
them — without this a commented-out `/* --nldesign-color-primary: red; */` would count as a
declaration and hide a missing token.

### 3. The vocabulary is wider than the two files the plan names

The plan says a set must declare no `--nldesign-*` name "outside the vocabulary declared in
`css/systems/nldesign/defaults.css` and `utrecht-bridge.css`". Measured against exactly those two
files, `amsterdam` — one of the 17 good sets — has 2 foreign names, because `theme.css` and
`element-overrides.css` read names those two files never mention (`--nldesign-logo-url`,
`--nldesign-color-background`, `--nldesign-color-nav-text`, `--nldesign-color-footer-*`, ...), and
hand-authored sets legitimately set them.

So `declaredVocabulary()` scans **every `.css` file under `css/` except `css/tokens/`** and collects
every `--nldesign-*` name, declared or referenced. A name is in the vocabulary iff *something can
consume it*; anything else is dead weight, which is precisely what the rule is looking for. The two
runtime-generated admin-data files (`custom-overrides.css`, `custom-css.css`) are excluded by name so
a value an admin typed into the theme editor can never widen the accepted vocabulary.

Under this vocabulary `rijkshuisstijl` has exactly one foreign name,
`--nldesign-color-logo-text` — a genuinely dead token nothing has ever read. The rule found a real
defect in the default set on its first run, which is the evidence that the widening did not
declaw it.

### 4. Names are matched with `[A-Za-z0-9_-]+`, not `[a-z0-9-]+`

`CssParserService::parseDeclarations()` matches `--[\w-]+`, so it parses camelCase names. An earlier
lowercase-only vocabulary scan combined with that parser produced an asymmetry: `nijmegen`'s three
upstream `--nldesign-tokenSetOrder-*` metadata tokens were reported as foreign by the PHP service and
not by the Node CLI. Both now use the same character class, and the finding is kept — those three
names are real dead weight, an upstream generator artefact leaking into a shipped file.

### 5. Sets whose design system reads no `--nldesign-*` name are "not auditable", not "failing"

`design-systems.json` lists each system's stylesheets. `nldesignConsumingSystems()` marks a system as
consuming when at least one of its own stylesheets references any `--nldesign-*` name. That yields:

| Design system | Consumes the vocabulary | Why |
|---|---|---|
| `nldesign` | yes | `theme.css`/`overrides.css`/`element-overrides.css` |
| `high-contrast` | yes | its `theme.css` reads 39 of them |
| `lasuite`, `cunningham` | yes | `systems/lasuite/bridge.css` reads 54 |
| `summer-breeze` | **no** | its `theme.css` and `element-overrides.css` reference none |
| `none` | **no** | loads no stylesheet at all |

`nextcloud` (`none`) and `summer-breeze` are therefore reported `auditable: false, complete: true`
with empty finding arrays, rather than as 26-token failures. This is why the measured baseline
excludes `summer-breeze` even though Appendix B lists it — its set file legitimately declares no
`--nldesign-*` token, because nothing in its stack would read one. (Plan decision 4 already flagged
`summer-breeze` as a special case; this is the mechanical form of that.)

`lasuite`, `cunningham` and `hoog-contrast` get no such exemption: their bridges *do* read the
vocabulary, so their gaps (status colours and links, mostly) are real fall-throughs to Rijkshuisstijl.

### 6. `primaryMismatch` never double-counts a missing primary

A set with no `--nldesign-color-primary`, or one whose value is not a hex literal, already appears in
`missingRequired`. `primaryMismatch` is therefore true only when **both** values normalise to a hex
and they differ — one defect, one finding. Normalisation is lowercase + 3-to-6-digit expansion, and a
manifest entry without `theming.primary_color` (`conduction`) is not a mismatch, it is simply
unconstrained.

Exactly one set fails this rule today: `xxllnc` declares `--nldesign-color-primary: #000000` while
`token-sets.json` says `#333333`.

### 7. One allow-list file, read by both gates

`tests/Unit/fixtures/token-set-vocabulary-allowlist.json` holds `{ "$comment": [...], "sets": [...] }`.
Both `tests/Unit/TokenSetVocabularyTest.php` and `scripts/audit-token-sets.mjs --check` read it. Two
copies of the list — one per runtime — would drift, and the whole point of the fixture is to be the
single record of what is known-broken.

The gate is bidirectional on purpose:

- a set that is incomplete and **not** listed fails (a new broken set cannot be added);
- a listed set that has started **passing** fails (stage 2 progress must be recorded, not hidden).

So the list can only shrink, and it must be `[]` when stage 2 closes.

### 8. The Node CLI is a mirror, and the mirroring is guarded

`npm run audit:token-sets` exists because a token-set author fixing `zwolle.css` should not need
`composer install` to see whether the fix worked. It re-implements the three rules rather than
shelling out to PHP, which means the two lists can drift —
`testNodeMirrorRequiresTheSameTokens()` parses `REQUIRED_TOKENS` straight out of the `.mjs` and
asserts equality with the PHP constant. Both implementations were run against all 48 sets during
this change and agree field-for-field on every one.

The CLI exits 0 by default (it is an informational audit; the PHPUnit test is the gate) and
non-zero under `--check`, which applies the same bidirectional allow-list logic.

### 9. The warning rides the existing `warnings` channel

`TokenSetService::applyWarnings()` already carries contrast findings to the admin UI through
`IInitialState`. The vocabulary finding is appended to the same array as a single entry with
`kind: 'incomplete'`, carrying `missing[]`, `foreign[]`, `primaryMismatch`, `declaredPrimary` and
`cssPrimary`. Contrast entries have no `kind`, so `admin.js` separates the two by that field alone:
`buildContrastWarningHtml()` filters `kind === 'incomplete'` out, `buildIncompleteWarningHtml()`
renders it, and `buildTokenSetWarningsHtml()` (the renamed apply-dialog entry point) emits both.

The dropdown badge is a third, hidden-by-default element next to the design-system badge, so a clean
catalogue stays visually quiet. In the custom-set list "Incomplete set" outranks "Contrast warning":
a set that never defines the vocabulary is broken in a way no contrast ratio can reveal.

**Cost**: `applyWarnings()` runs for every discovered set, so `getAvailableTokenSets()` now performs
one CSS-tree walk (29 files, memoised per service instance) plus one token-file read per set on top
of the contrast audit's existing two-file-per-set reads. Same order of magnitude as what was already
there, on an admin page and a catalogue endpoint. Not cached across requests in this change; stage 6
reworks this surface and is the right place for it.

## Baseline (measured 2026-09-07, `npm run audit:token-sets`)

48 shipped sets: **46 audited, 5 complete, 41 incomplete, 2 not auditable**.

Complete: `amsterdam`, `conduction`, `denhaag`, `utrecht`, `vng`.
Not auditable: `nextcloud` (`none`), `summer-breeze` (`summer-breeze`).

The 41 incomplete ids are the initial contents of the allow-list. 30 of them are Appendix B entries;
the 11 that Appendix B does not list are `conduction-new`, `cunningham`, `frankendesk`,
`hoog-contrast`, `lasuite`, `leiden`, `noaberkracht`, `opencatalogi`, `rijkshuisstijl`, `rotterdam`,
`xxllnc`. Appendix B's 31st entry, `summer-breeze`, is not auditable (decision 5).

<!-- Regenerate with: node scripts/audit-token-sets.mjs --json -->

| Set | Design system | Missing required | Foreign names | Primary | Verdict | In Appendix B |
|-----|---------------|-----------------:|--------------:|---------|---------|---------------|
| `amsterdam` | nldesign | 0 | 0 | ok | complete | — |
| `bodegraven-reeuwijk` | nldesign | 26 | 118 | (no CSS value) | incomplete | yes |
| `borne` | nldesign | 26 | 74 | (no CSS value) | incomplete | yes |
| `buren` | nldesign | 26 | 117 | (no CSS value) | incomplete | yes |
| `conduction` | nldesign | 0 | 0 | (unset) | complete | — |
| `conduction-new` | nldesign | 15 | 189 | (no CSS value) | incomplete | — |
| `cunningham` | cunningham | 10 | 0 | ok | incomplete | — |
| `demodam` | nldesign | 26 | 52 | (no CSS value) | incomplete | yes |
| `denhaag` | nldesign | 0 | 0 | ok | complete | — |
| `dinkelland` | nldesign | 24 | 183 | (no CSS value) | incomplete | yes |
| `drechterland` | nldesign | 26 | 93 | (no CSS value) | incomplete | yes |
| `duiven` | nldesign | 26 | 24 | (no CSS value) | incomplete | yes |
| `duo` | nldesign | 26 | 85 | (no CSS value) | incomplete | yes |
| `enkhuizen` | nldesign | 26 | 97 | (no CSS value) | incomplete | yes |
| `epe` | nldesign | 26 | 186 | (no CSS value) | incomplete | yes |
| `frankendesk` | lasuite | 10 | 81 | ok | incomplete | — |
| `groningen` | nldesign | 26 | 2 | (no CSS value) | incomplete | yes |
| `haarlem` | nldesign | 26 | 120 | (no CSS value) | incomplete | yes |
| `haarlemmermeer` | nldesign | 26 | 117 | (no CSS value) | incomplete | yes |
| `hoog-contrast` | high-contrast | 6 | 0 | ok | incomplete | — |
| `hoorn` | nldesign | 26 | 85 | (no CSS value) | incomplete | yes |
| `horstaandemaas` | nldesign | 26 | 98 | (no CSS value) | incomplete | yes |
| `lasuite` | lasuite | 10 | 0 | ok | incomplete | — |
| `leiden` | nldesign | 22 | 163 | ok | incomplete | — |
| `leidschendam-voorburg` | nldesign | 26 | 91 | (no CSS value) | incomplete | yes |
| `nextcloud` | none | — | — | — | not auditable | — |
| `nijmegen` | nldesign | 26 | 959 | (no CSS value) | incomplete | yes |
| `noaberkracht` | nldesign | 22 | 159 | ok | incomplete | — |
| `noordoostpolder` | nldesign | 26 | 128 | (no CSS value) | incomplete | yes |
| `noordwijk` | nldesign | 24 | 168 | (no CSS value) | incomplete | yes |
| `opencatalogi` | nldesign | 4 | 28 | ok | incomplete | — |
| `provincie-zuid-holland` | nldesign | 26 | 83 | (no CSS value) | incomplete | yes |
| `riddeliemers` | nldesign | 26 | 60 | (no CSS value) | incomplete | yes |
| `ridderkerk` | nldesign | 26 | 9 | (no CSS value) | incomplete | yes |
| `rijkshuisstijl` | nldesign | 0 | 1 | ok | incomplete | — |
| `rotterdam` | nldesign | 4 | 28 | ok | incomplete | — |
| `stedebroec` | nldesign | 26 | 94 | (no CSS value) | incomplete | yes |
| `summer-breeze` | summer-breeze | — | — | — | not auditable | yes |
| `tilburg` | nldesign | 26 | 119 | (no CSS value) | incomplete | yes |
| `tubbergen` | nldesign | 24 | 184 | (no CSS value) | incomplete | yes |
| `utrecht` | nldesign | 0 | 0 | ok | complete | — |
| `venray` | nldesign | 26 | 105 | (no CSS value) | incomplete | yes |
| `vng` | nldesign | 0 | 0 | ok | complete | — |
| `vught` | nldesign | 26 | 110 | (no CSS value) | incomplete | yes |
| `westervoort` | nldesign | 25 | 33 | (no CSS value) | incomplete | yes |
| `xxllnc` | nldesign | 21 | 167 | mismatch | incomplete | — |
| `zevenaar` | nldesign | 26 | 25 | (no CSS value) | incomplete | yes |
| `zwolle` | nldesign | 26 | 94 | (no CSS value) | incomplete | yes |

Reading the table:

- **26 missing + a high foreign count** is the raw-upstream-dump signature: the set declares a full
  brand palette under the `--nldesign-` prefix and none of the semantic vocabulary. `nijmegen` (956
  foreign names) is the extreme case; `groningen` (2 foreign, 18 lines) and `ridderkerk` (9 foreign)
  are the near-empty ones the plan already flagged as needing a hand-authored brand file.
- **A small missing count with a high foreign count** (`conduction-new` 15/189, `leiden` 22/163,
  `xxllnc` 21/167, `noaberkracht` 22/159) is the woo-website shape: real semantic values plus a large
  `--nldesign-footer-*`/`-hero-*`/`-card-*` vocabulary that only `css/public-bridge.css` partially
  reads.
- **A small missing count with zero foreign names** (`cunningham` 10/0, `lasuite` 10/0,
  `hoog-contrast` 6/0) is a clean set with a genuine gap: all of them omit `--nldesign-color-link`,
  `-link-hover` and the four status `-rgb` triplets, so those fall through to Rijkshuisstijl.
- **`rijkshuisstijl` 0/1** is the one-name case from decision 3.

## Risks / Trade-offs

- **The allow-list is 41 entries, not the plan's 31.** Stage 2's scope is larger than
  `MAKEOVER-PLAN.md` assumed. The alternative — narrowing the required list until exactly Appendix B
  failed — would have meant dropping links, status colours, `-rgb` triplets, `font-family` and every
  `border-radius` from the definition, which is most of what makes a brand look like itself. The
  measurement stands and the plan's Appendix B line is corrected here.
- **Every admin now sees a warning on 41 of 48 sets.** Intended ("no silent drops"), but noisy until
  stage 2 lands. The badge is hidden for complete sets and the warning is non-blocking, so nothing is
  prevented; stage 6 owns the copy and grouping.
- **The vocabulary is derived from the CSS tree, so deleting a `var()` reference can create a foreign
  name.** That is the intended direction of the rule (a name nothing reads is dead weight), but it
  means an unrelated cleanup in `theme.css` can add allow-list pressure. The per-set failure message
  names the exact token, so the cause is never a mystery.
- **The Node mirror can drift in the rules, not just the token list.** The token list is guarded by a
  test; the rule bodies are not. They were verified equal across all 48 sets during this change. If
  they diverge later, the PHPUnit gate is authoritative and the CLI is the convenience.

## Migration Plan

None. No data, no config key, no CSS file changes; the audit is additive and read-only. The one
user-visible change (the badge/warning) appears on the next admin-page load.

## Open Questions

- Does `--nldesign-color-logo-text` (rijkshuisstijl's single foreign name) want a consumer in
  `theme.css`, or should the declaration be deleted? Deleting it is the smaller change and takes
  `rijkshuisstijl` to complete; stage 2 can do either.
- `summer-breeze` is not auditable under decision 5, which means its set file is unguarded by
  anything. Should the `summer-breeze` system get its own required vocabulary in a later stage, or
  should the set be folded into the nldesign stack?
