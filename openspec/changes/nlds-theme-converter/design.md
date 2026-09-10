# Design: NLDS theme to Nextcloud token set converter

Stage 4 of `MAKEOVER-PLAN.md` (stage 2 until the 2026-09-10 reorder). Stage 1 defined *correct* and measured who fails; this change is the
machine that makes them correct, and the admin-facing path that does the same for a theme nobody has
seen before.

## Baseline (measured 2026-09-09, `npm run audit:token-sets`)

| Verdict | Count | Sets |
|---|---|---|
| complete | 7 | amsterdam, conduction, denhaag, rotterdam, utrecht, vng, zwolle |
| incomplete | 39 | the allow-list in `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` |
| not audited | 2 | nextcloud (design system `none`), summer-breeze (own design-system layer) |

Shape of the 39, because it decides the converter's primary input:

| Missing required tokens | Sets | Note |
|---|---|---|
| 26 of 26 | 25 | raw upstream dumps: palette present, semantic layer absent |
| 25 | 1 | westervoort |
| 24 | 3 | dinkelland, noordwijk, tubbergen |
| 22 | 2 | leiden, noaberkracht |
| 21 | 1 | xxllnc — also the only `primaryMismatch` |
| 15 | 1 | conduction-new |
| 10 | 3 | cunningham, lasuite, frankendesk (non-nldesign design systems) |
| 6 | 1 | hoog-contrast |
| 4 | 1 | opencatalogi |
| 0 | 1 | rijkshuisstijl — 1 foreign name only |

30 of the 39 declare no `--nldesign-color-primary` at all. None of them are empty files: the foreign
name counts run from 2 (`groningen`) to 959 (`nijmegen`). **The brand data is already in the
repository under the wrong names.** That is why input D (below) is the input that closes the
allow-list, not input A.

## Decision 1: the mapping table is data, in one file, hashed into the output

`scripts/mapping/nlds-to-nextcloud.json` holds plan Appendix A as an ordered rule list:

```json
{
  "version": 1,
  "converterVersion": "1.0.0",
  "rules": [
    {
      "target": "--nldesign-color-primary",
      "nextcloud": ["--color-primary", "--color-primary-element"],
      "sources": ["--utrecht-button-primary-action-background-color", "--{p}-color-primary"],
      "fallback": { "kind": "manifest", "path": "theming.primary_color" },
      "transform": "copy",
      "guard": { "kind": "contrast", "against": "--nldesign-color-primary-text", "min": 4.5,
                 "onFail": "contrast-adjusted" }
    }
  ],
  "never": [
    { "match": "--utrecht-page-max-inline-size", "action": "skip",
      "reason": "layout-fixed-by-nextcloud" }
  ],
  "reasons": { "layout-fixed-by-nextcloud": "Page width and paddings were not applied. ..." }
}
```

As built: 43 rules covering all 26 required semantic tokens plus the derived radius, `-rgb`, focus
and manifest targets; 26 `never` entries; 18 reason codes. `nextcloud` is documentation — the
Nextcloud variables the target feeds through `overrides.css` — carried in the table so the report and
stage 5's playground can name them without a second lookup table.

`fallback` is always an object with a `kind`: `manifest` (a `token-sets.json` path), `literal` (a
fixed value), `derive` (another target plus a transform), or `ramp` (pick a step from the brand's
neutral ramp by `darkest`, `minContrast` or `nearestLuminance`). `never` entries carry an `action` of
`skip` (not emitted) or `keep` (emitted in section 2, counted separately), which is what keeps
"Nextcloud will not do this" distinct from "this is for NLDS components".

**Why data and not code.** Two runtimes have to agree (decision 2), and a table in two languages
drifts on the first hotfix. The table is also the thing a reviewer argues with — "why did Zwolle's
header end up ice blue" is answered by a diff of one JSON file, not by reading two implementations.
Its SHA-256 is written into every generated file's provenance block, so a set regenerated under an
older table is visible without running anything.

`{p}` expands to the brand prefix (decision 6). `fallback` is deliberately a different key from
`sources`: a fallback is not a token, it is where the converter goes when the theme said nothing, and
it is always reported as `adapted`, never `applied`.

## Decision 2: three runtimes, one authority per path

- **`lib/Service/TokenSetConverterService.php` is authoritative for anything an admin does.** The
  upload and the paste both write a file through `CustomTokenSetService` and both must pass
  `CustomTokenSetValidator` server-side; a browser-side conversion would either duplicate that gate
  or move trust to the client.
- **`js/lib/tokenConverter.js` is authoritative for anything the repository does to itself** —
  regenerating shipped sets through `scripts/convert-nlds-theme.mjs`, and the vitest fixtures.
  `scripts/` in this repo is Node (`generate-tokens.mjs`, `audit-token-sets.mjs`,
  `generate-brand-set.mjs`), and stage 1 already set the precedent of a Node mirror beside a PHP
  service.
- **`tests/Unit/Service/TokenSetConverterParityTest.php` is what keeps them honest.** The vitest run
  writes `tests/Unit/fixtures/converter/<fixture>.expected.json` (css + manifest + report); the
  PHPUnit test converts the same fixture and asserts byte equality on the CSS and structural equality
  on the report. A drift fails both suites, not one.

The browser module is loaded on the settings page anyway (dual-mode, like `tokenTransforms`), which
stage 5's playground needs for live re-conversion. It is not on the trust path.

## Decision 3: nothing is sourced from upstream at convert time — the input arrives from a human

Plan decision 2 is answered: **not sourced and not converted in CI.** No `@conduction/theme`
dependency, no vendored `dist/` copies, no fetch. Consequences, all deliberate:

- The converter's public interface is content, not a location: a string plus a slug. Every caller
  (upload, paste, CLI, playground) hands it bytes it already has.
- `.github/workflows/sync-tokens.yml` keeps doing exactly what it does today. The plan's task
  "update the sync workflow to run the converter" is dropped: the nightly sync's input is the
  `nl-design-system/themes` clone it already makes, and converting that clone is input C, which is
  the same code path as input D over a file already in the tree — worth doing once the paste path is
  proven, not before.
- **Fixtures are local.** `css/tokens/openwoo.css` (172 lines, 57 `--nldesign-*` declarations,
  hand-resolved from `@conduction/theme` 2.1.0) is the reference the OpenWOO conversion is diffed
  against. `@gemeente-rotterdam/design-tokens` and `@nl-design-system-unstable/zwolle-design-tokens`
  are installed dependencies and give one DTCG and one Style Dictionary fixture without adding
  anything.
- **Acceptance is a human paste.** The definition of done for the admin path is: paste the contents
  of a real `design-tokens.css` into the panel, get a set whose header is the brand's header and
  whose primary is the brand's primary, plus a report that lists the layout tokens it refused. The
  automated fixtures exist to keep that working, not to replace it.

## Decision 4: detect the input by content, in a fixed order

The current router branches on the file name (`mapUpload()` reads `.json` / `.tokens.json`). A paste
has no name, so detection moves to the content and the file name becomes a hint only:

1. Trimmed content starts with `{` or `[` → JSON. Parse once. If any leaf has `$value`, it is
   **DTCG (input B)** → `DesignTokensMapper`, which already owns aliases, `$type` dispatch and the
   suffix table. Otherwise it is a **Style Dictionary `tokens.json`** → the same walk with
   `{a.b.c}` alias resolution.
2. Content contains `--` declarations inside at least one selector block:
   - the block selector matches `:root` and the declarations are predominantly `--nldesign-*` →
     **input D, an existing token set** (add-only mode, decision 9);
   - otherwise → **input A, built theme CSS** (`.{prefix}-theme { }`, `.utrecht-theme`, or any
     class-scoped block).
3. Nothing matched → 422 with the four accepted shapes named. An unrecognised paste must never be
   stored as an empty set.

Multiple blocks are merged in source order, later declarations winning, which is what the cascade
would do anyway. Media queries and `@supports` are skipped with `at-rule-not-converted`: the output
is one flat `:root`, enforced by `TokenCssShapeTest`.

## Decision 5: the output is one file in four sections plus provenance

Order is fixed so two runs diff cleanly and so a reader can find the layer they care about:

1. **Brand palette**, verbatim values, renamed to `--{p}-*`.
2. **Component layer**, `--utrecht-*` / `--ams-*` / `--denhaag-*`, `var()` chains resolved to
   literals so the file stands on its own. Gaps filled from the VNG role layer re-pointed at the
   brand ramp (the logic in `scripts/generate-brand-set.mjs` section C, absorbed here).
3. **Semantic layer**, `--nldesign-*`, produced by the table. This is the only section Nextcloud's
   chrome reads.
4. **Provenance comment**: input kind (A/B/C/D), source name and version when the input carried one,
   converter version, mapping-table SHA-256, and the applied / adapted / skipped counts.

Every section is inside the same `:root { }` block with a comment header, because
`TokenCssShapeTest` requires exactly one flat block and no at-rules.

## Decision 6: brand prefix from the slug, palette steps always prefixed, `var()` always resolved

The prefix is the slug (`openwoo`, `nijmegen`, `custom-acme`), not the prefix the source used. A
theme that ships `--openwoo-color-green-33` keeps that name when the slug is `openwoo` and is renamed
when it is not, because the file has to be self-consistent for `utrecht-bridge.css` and for anything
reading `--{slug}-*`.

Raw palette steps found under `--nldesign-*` — the 39 sets' entire content — are renamed to `--{p}-*`
and reported as `adapted` / `palette-reprefixed`. This is what turns `nijmegen`'s 959 foreign names
from a defect into the brand ramp the semantic layer is derived from.

`var(--x, fallback)` chains are resolved to literals at conversion time, up to a depth limit, with a
cycle guard. A chain that leaves the input's own vocabulary is kept verbatim and reported
`unresolved-var`. Reason: a token set is loaded on the login page and in e-mails, where the source
theme's own variables do not exist.

## Decision 7: candidate resolution, transforms, and one contrast guard

First matching source wins, per rule. Transforms are the closed set
`copy`, `darken(fraction)`, `mix(with, weight)`, `rgbTriplet`, `alpha(fraction)`,
`radiusScale(factor, clampPx)`, `onColor(light, dark)` and `literal(value|template)` — the same names
in both runtimes. `darken` and `mix` reuse `js/lib/tokenTransforms.js` `darkenHex()` and the mixing
`CommonThemeTrait` uses, so a derived tint matches what Nextcloud would compute for itself.
`onColor` is Nextcloud's own rule for text on a coloured plate: pick white or black by the
background's luminance, which is how `--nldesign-color-primary-text` and `-header-text` are derived
when the theme names no foreground.

Guards are separate from transforms and run after them. Two kinds:

- `contrast` — the target is darkened in 5% steps until the ratio is met, capped at 10 steps, and
  reported `adapted` / `contrast-adjusted` with the original value in `value.from`. Applied to the
  primary/primary-text pair, the header pair and text-muted on background: the pairs
  `ShippedTokenSetAuditService` already audits, so the converter cannot emit a set that fails the
  audit it is judged by.
- `fontAvailable` — the family is emitted either way; the guard only decides whether the report
  carries `font-not-bundled` (design decision 12).

A rule may also carry a `when` condition, which decides whether the rule runs at all — `sameColor`
(the header separator, emitted only when header and content are the same colour) and `isDark` (the
app-menu icon filter, `none` on a dark header where the hard-coded black made the icons vanish).
A condition is not a guard: a guard fixes a value, a condition decides that a value belongs in the
file at all.

Missing `-rgb` triplets are derived, never required from the source: `rgbTriplet` of the resolved
colour. Missing `-light` / `-light-hover` are mixed from the primary. `-hover` prefers the theme's own
hover token and falls back to `darken(0.10)`, reported `adapted`.

## Decision 8: what is never applied is a rule in the table, not a special case in code

The `never` list carries a matcher, an action (`skip` or `keep`) and a reason code, and the converter
walks it before the rules, so a source token can only be applied if it survived the policy. 13 of the
18 codes are stage 6's admin-facing list and their copy lives in the same JSON (with `l10n/en.json`
keys for the sentence the admin reads):
`layout-fixed-by-nextcloud`, `typography-scale-locked`, `clickable-area-locked`,
`spacing-not-consumed`, `routed-to-core-theming`, `derived-by-nextcloud`, `radius-scale-derived`,
`font-not-bundled`, `contrast-separator-added`, `contrast-adjusted`, `kept-for-nlds-components`,
`external-url-blocked`, `unmapped`.

Two of them are not refusals and must not read like one:

- `routed-to-core-theming` — the page background goes to `token-sets.json`
  `theming.background_color`, so Nextcloud paints the login page and the plain background with it,
  while `--color-main-background` stays Nextcloud-owned so dark mode keeps working.
- `kept-for-nlds-components` — accordion, breadcrumb, calendar, skip-link, spotlight, data-list and
  code tokens stay in section 2 for Conduction's own apps. They are kept, not applied, and are
  counted separately from `skipped` so a report does not read as if a third of the theme was thrown
  away.

`unmapped` is the honest bucket: a source token with no rule and no `never` entry is reported so the
table can grow. It is a report line, never a silent drop.

The five remaining codes describe the conversion's own mechanics rather than a policy about
Nextcloud, and are reported the same way: `palette-reprefixed` (decision 6), `unresolved-var`
(decision 6), `at-rule-not-converted` (decision 4), `kept-existing-value` (decision 9) and
`derived-from-header` (the `when`-conditioned header rules above).

## Decision 9: hand-authored sets are re-run in add-only mode

For input D over one of the 7 complete sets (and any custom set an admin re-converts), a value that
already exists wins and the converter may only add what is missing. Every skipped write is reported
`kept-existing-value`. Rationale: those 7 are the sets that look right, three of them were tuned by
hand against a real instance, and a converter that "improves" them turns a regeneration into a
review of somebody's judgement. The plan states the same rule ("diff must show additions only") and
it is the only way the regeneration of all 46 sets is reviewable in one pass.

## Decision 10: `summer-breeze` gets a semantic layer like every other set

Plan decision 4 is answered: give it the layer. Its colours live in
`css/systems/summer-breeze/`, which is why the audit reports it as not auditable — the audit reads
the set file alone, on purpose, because layering is what hides a missing brand. Rather than widen the
audit to know about design-system layers (which would also re-hide the 39), the set is converted from
its own design-system layer as input A and emits a normal token file. After that all 47 non-stock sets
are judged by one rule, and `nextcloud` stays the single documented exception because stock *is* its
brand.

## Decision 11: one entry point, two ways in, one report shape

`CustomTokenSetController::upload()` accepts `file` **or** `content` (plus optional `sourceName` for
the provenance block) and is unchanged from `readUpload()` onward. Everything else — slug derivation,
conversion, validation, storage, contrast warnings, the audit entry — stays exactly where it is.

The response gains `report`: an array of `{source, target, action, reason, value}` with
`action ∈ {applied, adapted, skipped, kept}`, plus `counts`. The admin panel renders it with the
existing `groupDiagnosticsByReason()` + `buildDiagnosticsFragment()` pair that already groups DTCG
diagnostics, so the upload result, the paste result and the CLI table are the same information in
three places. The report is written next to the set as `css/tokens/<slug>.report.json` — committed for
shipped sets, gitignored for `custom-*` — so stage 5 can show per-component notes without re-running
the conversion.

## Decision 12: the converter feeds the validator, it never replaces it

`CustomTokenSetValidator::validateDeclarations()` stays the last gate before anything is written, and
`isForbiddenValue()` is not touched. Two widenings, both required by the emitted file:

- accepted name prefixes gain `--utrecht-*`, `--ams-*`, `--denhaag-*` (section 2 exists for
  `utrecht-bridge.css`), alongside the existing `--nldesign-*` and `--{slug}-*`;
- `url()` values are allowed only when they resolve to a local app path, which is the existing rule;
  anything with a host is dropped by the converter first with `external-url-blocked`, so the
  validator sees a clean file and the admin still gets told.

Font families are taken verbatim into `--nldesign-font-family`. When the first family is neither
bundled nor uploaded (`FontService`), the report carries `font-not-bundled` and the stack falls
through to the next family — the theme is not rewritten to a font the instance has.

## Risks

- **A regenerated set changes a live instance's appearance.** True for all 39, and it is the bug being
  fixed; the 7 complete sets are protected by decision 9. Roll-back is per set: the previous file is
  one `git checkout` away and the provenance block says which table produced it.
- **`primaryMismatch` resolution can move a manifest colour.** `xxllnc` is the only current case. The
  CSS wins, because the CSS is what paints the app and the audit compares against it.
- **A 959-name palette produces a large section 1.** Accepted: the file is generated, diffed by
  machine, and read by section header.
- **PHP/JS parity is a promise, not a mechanism.** Mitigated by the shared table (decision 1) and the
  parity test (decision 2), which is the same arrangement stage 1 used for the audit and which caught
  drift there.
