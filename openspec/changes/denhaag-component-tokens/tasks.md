# Tasks: denhaag-component-tokens

Tier: V1. Kind: code. Programme: portal-design (2026-10-02). Tick a box when the work is
merged to `development`.

Depends on, outside this repo: portaliq `site-links-the-theme-bridge`, which links
`css/public-bridge.css` in `templates/site.php`, and `site-mijn-omgeving-components`, which adds
the Den Haag CSS to portaliq (both in portaliq#1110, not merged).
Nothing here waits on them; the components only show the result once both land.

## 1. The mapping

- [x] 1.1 `scripts/mapping/denhaag-component-tokens.json`: every property read by the pinned
      packages (design D0), colour properties mapped per design D2, geometry copied from
      `@gemeente-denhaag/design-tokens-components` at a pinned version.
  - unit: a vitest reads the pinned package CSS and asserts every `var(--denhaag-…)` and
    `var(--nl-data-badge-…)` name is in the mapping
- [x] 1.2 `scripts/generate-denhaag-bridge.mjs` with `--check`; `npm run generate:denhaag-bridge`
      and `npm run test:denhaag-bridge` in `package.json`.

## 2. The bridge

- [x] 2.1 Generate the delimited section into `css/public-bridge.css`.
  - unit: a vitest resolves the bridge over `denhaag`, `rotterdam` and `example-basisschool`
    and asserts the scenarios of the spec (case card colours, rotterdam keeps its own, the
    current step wears the primary)
- [x] 2.2 Replace the stale "46 sets, exactly one" sentence in the bridge header and update
      `docs/features/public-portals-as-consumers.md` with the measured numbers and the
      portaliq change that links the bridge.
  - unit: the coverage test of requirement "The bridge documents the real coverage"

## 3. The guardrail

- [x] 3.1 `ShippedTokenSetAuditService`: resolve the generated section under each set and
      check the pairs of design D3 through `ContrastService`.
  - unit: PHPUnit with a fixture set that fails the current step pair, and one that passes
- [x] 3.2 Every shipped set passes all 13 pairs (51 of 51; 6 before). Ruben said "fix them"
      on 2026-10-02. Fixed at the source, in this order:
      (a, b) the bridge derives status text from each set's own hue, darkened with black:
      `--thematiq-status-warning-text` (warning at 50%), `-success-text` (70%),
      `-error-text` (85%). Badge text, the action row's warning date, the warning and error
      step headings and the checked-step tick read them. Fills are unchanged. 45 failing sets
      became 5.
      (c) current-step text chosen for contrast: opencatalogi `#003865` on `#e8f4fb` 10.70:1
      (was `#1791d4`, 3.11:1), checked tick `#006e32` 5.60:1 (was `#00811f`, 4.41:1), both
      through `scripts/brands/opencatalogi.json`; noaberkracht black on `#4376fc` 5.24:1 (was
      white, 4.01:1) and its active navigation link `#2e5ed9`, its own primary-hover, 5.65:1.
      (d) vng's not-checked step `gray-600` `#5b6e8a` 5.20:1 (was `gray-400`, 2.52:1);
      cunningham's Den Haag muted text `#686b70` 5.35:1 (its muted `#74777c` is 4.49:1).
      westervoort's hsl() warning is now measured (it was unevaluated).
      No brand palette value changed. The pairs stay out of the pass/fail verdict, as the spec
      says; `DenhaagContrastPairsTest::testEveryShippedSetPassesEveryDenhaagPair` guards them.

## 4. Validation

- [x] 4.1 `openspec validate denhaag-component-tokens --strict`, `npm run lint`,
      `npm run test:unit`, `composer check:strict` once before push.
- [ ] 4.2 Live, after the portaliq link lands: `/site` with the `denhaag` set shows a case
      card, process steps and a badge in Den Haag's colours, and with `example-gemeente` in
      `#12506B`.
