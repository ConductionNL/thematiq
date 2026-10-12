# Tasks: example-gemeente-theme

Tier: V1. Kind: config. Programme: portal-design (2026-10-02). Tick a box when the work is
merged to `development`.

## 0. Decision for Ruben

- [x] 0.1 Font: bundle Source Sans 3 as the mockups name it (design D3), or keep Fira Sans
      like the school sets. The tasks below assume Source Sans 3.
      Decided 2026-10-09 (Ruben, decision 126, Q-thematiq-4): keep Source Sans 3, as shipped.

## 1. The set

- [x] 1.1 `scripts/brands/example-gemeente.json` with the palette of design D1, the semantic
      layer of D2 and the radii of D2. Every open ramp step gets a value and a `from`.
  - check: `node scripts/generate-brand-set.mjs example-gemeente` twice, `git diff --exit-code`
- [x] 1.1a NEW in the generator: refuse a `ramp` entry without `from` and name it. The
      generator does not check this today; the school sets pass because every entry has one.
  - unit: a vitest feeds a brand file with one `from` removed and expects the refusal
- [x] 1.2 Generate `css/tokens/example-gemeente.css` and
      `php scripts/generate-dark-variants.php` for `css/tokens/dark/example-gemeente.css`.
- [x] 1.3 `token-sets.json` entry with `primary_color` `#12506B`, `background_color`
      `#FFFFFF` and the logo path.
  - check: `npm run check:manifest`, `npm run audit:token-sets:check`
- [x] 1.4 `img/logos/example-gemeente.svg` (design D4). No dark-surface twin: like the four
      school logos it draws on its own white plate, so it stays readable on a dark header.

## 2. The font

- [x] 2.1 Bundle Source Sans 3 latin 400, 600, 700 woff2 in `css/fonts/`, add the
      `@font-face` block, record OFL 1.1 in `REUSE.toml` and `LICENSES/`.
  - check: `reuse lint` exit 0

## 3. Evidence and docs

- [x] 3.1 Regenerate `docs/reference/contrast-report.md` and the token reference page.
  - unit: the existing contrast audit PHPUnit asserts a verdict for the new set
- [x] 3.2 README token set list and CHANGELOG entry.

## 4. Validation

- [x] 4.1 `openspec validate example-gemeente-theme --strict`, `npm run lint`,
      `composer check:strict` once before push.
- [x] 4.2 Live: apply the set on a test instance and open portaliq `/site` with a dossiq
      contribution; the header, buttons and links wear `#12506B`.
      Live 2026-10-10 on the throwaway thematiq-live2 (:8093, NC 34; thematiq build/openspecs-live2, openregister 2.1.38, portaliq 0.2.10, dossiq 0.4.50, learniq 0.3.13 unstable development builds; `portaliq:example-site:install zuiddrecht` + `portaliq:example-resident:install zuiddrecht`, `learniq:example-set:load mbo` and `training`): the menu band, the header tools, the case and content links wear #12506B. Found live: the primary button wore #0B3648, the hover step, because the set's own role layer fed the Utrecht button and link roles from `--tilburg-interaction-color`, which the generator mapped to brand-400. Fixed test-first (370d0b2b, `publicBridgeRoleLayer.spec.js` "example-gemeente draws its buttons and links in its primary"); rerun: "Naar mijn account" on #12506B.
