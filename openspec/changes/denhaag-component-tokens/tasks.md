# Tasks: denhaag-component-tokens

Tier: V1. Kind: code. Programme: portal-design (2026-10-02). Tick a box when the work is
merged to `development`.

Depends on, outside this repo: the portaliq lane's change that links `css/public-bridge.css`
in `templates/site.php` (planned in portaliq `portal-theme-blocks-and-contributed-pages`
task 2) and `site-mijn-omgeving-components`, which adds the Den Haag CSS to portaliq.
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

- [ ] 3.1 `ShippedTokenSetAuditService`: resolve the generated section under each set and
      check the pairs of design D3 through `ContrastService`.
  - unit: PHPUnit with a fixture set that fails the current step pair, and one that passes
- [ ] 3.2 Regenerate `docs/reference/contrast-report.md`. A set that fails gets a set-level
      override in its brand or token file, never a change to the shared mapping. List every
      such override in the PR.

## 4. Validation

- [ ] 4.1 `openspec validate denhaag-component-tokens --strict`, `npm run lint`,
      `npm run test:unit`, `composer check:strict` once before push.
- [ ] 4.2 Live, after the portaliq link lands: `/site` with the `denhaag` set shows a case
      card, process steps and a badge in Den Haag's colours, and with `example-gemeente` in
      `#12506B`.
