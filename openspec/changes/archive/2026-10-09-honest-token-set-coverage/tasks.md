Note: no OpenRegister schemas are involved. The audit is filesystem work over `css/`,
`token-sets.json` and `design-systems.json` and persists nothing, so there is no Seed Data
section and no seed task. There is no lifecycle, aggregation or notification behaviour either,
so ADR-031's declarative-vs-imperative notification distinction does not apply.

## 1. Spec and design

- [x] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md` and the spec delta on
      `openspec/specs/token-sets/spec.md` (two ADDED requirements — instance coverage, and the
      typeface surface in the admin dialog).
- [x] 1.2 Record the measured baseline in `design.md` D8, per set and per dimension, with the
      command that regenerates it. Reconcile it against the research note and name every
      place the two disagree (bridge 8 not 14, fonts 33 not 26, and six of the seven
      `unevaluated` sets being a missing background rather than a `var()`).

## 2. Contrast: every set gets a verdict

- [x] 2.1 `CssParserService::resolveVarChain()`: follow a `var()` value through the cascade a
      browser reads, four hops, reporting the token it stopped on rather than guessing.
      Unit tests for a literal, a two-hop chain, a fallback taken only when the token is
      absent, an unknown reference and a cycle.
- [x] 2.2 `ShippedTokenSetAuditService::resolveDeclarations()`: resolve `var()` chains, and add
      the `#ffffff` page terminus so a set that names no background is measured instead of
      reported `unevaluated` forever. `portalCascade()` deliberately does NOT resolve — the
      Den Haag pair tests read the raw bridge reference to prove a set is judged through the
      bridge.
- [x] 2.3 `css/tokens/vng.css` declares `--nldesign-color-background: #ffffff`, its own
      `--utrecht-document-background-color`. No brand colour changed, no threshold moved,
      `theming.background_color` #0277BD untouched.
- [x] 2.4 Regenerate the contrast report, the dark variants and the token reference. 57 pass,
      0 fail, 0 unevaluated (was 49 / 1 / 7).
- [x] 2.5 Move the "a sub-AA set is surfaced as fail" control onto a probe, since no shipped
      set fails any more, and prove the probe fails when its colours are made to pass.

## 3. Fonts: serve what we may, refuse what we may not

- [x] 3.1 Rewrite `scripts/build-fonts.js` as one `FAMILIES` table generating
      `css/systems/nldesign/fonts.css`, `css/fonts.css` and both licence notices, with
      `--check` (`npm run test:fonts`).
- [x] 3.2 Ship Open Sans, Lato, Roboto, Source Sans Pro, IBM Plex Sans and Clear Sans (woff2,
      latin, `@fontsource` 5.3.0), and declare Figtree's existing bytes in the linked layer.
- [x] 3.3 Record the licences: `LICENSES/Apache-2.0.txt`, an `APACHE-2.0.txt` beside the
      binaries for Intel Clear Sans, REUSE.toml annotations per family, and generated `OFL.txt`
      notices that name every bundled OFL family.
- [x] 3.4 `tests/vitest/fontLicences.spec.js` derives its families from the generator's tables
      instead of a hand-typed list, and asserts no font binary is unclaimed. This is what
      found Source Sans 3 missing from both notices.
- [x] 3.5 Refuse the 10 undistributable families, and record the position per set as a `font`
      block in `token-sets.json` (family, selfHosted, licence, licenceHolder, action, note).
      Check mechanically that no libre release exists (`npm view @fontsource/<family>`).
- [x] 3.6 `TokenSetFontAuditService` + its PHPUnit gate, wired into
      `TokenSetService::applyWarnings()` on the existing `warnings` channel.
- [x] 3.7 The admin banner (`buildFontWarningHtml`), with the six source strings in
      `l10n/en.json`, Dutch translations in `l10n/nl.json` and the other catalogues backfilled.

## 4. The instrument

- [x] 4.1 Stage 2 in `scripts/audit-token-sets.mjs`: bridge, font, logo and contrast per
      selectable set, printed as one table, with the three vocabulary rules unchanged.
- [x] 4.2 `ShippedTokenSetAuditService::renderVerdictsJson()` +
      `docs/reference/contrast-report.json`, so the Node table reads the PHP engine's verdict
      rather than reimplementing WCAG. Staleness checked alongside the markdown.
- [x] 4.3 `tests/Unit/fixtures/token-set-coverage-allowlist.json`: dimension to set id to
      reason, with a reason per set derived from that set's own facts.
- [x] 4.4 `tests/vitest/tokenSetCoverage.spec.js`: fails on an unlisted failure, a stale entry
      and a reasonless entry, each proven by a probe; and compares the Node and PHP
      `SYSTEM_FAMILIES` lists so the CLI and the admin UI cannot disagree.
- [x] 4.5 `docs/reference/token-set-coverage.md`, generated
      (`npm run audit:token-sets:report`), with `npm run test:token-set-coverage` as its
      staleness gate.
- [x] 4.6 Wire it in: the two new legs in `code-quality.yml`'s `frontend-checks`, and the
      report regeneration plus the two vitest specs in `scripts/token-set-gate.sh`.

## 5. Verification

- [x] 5.1 `bash scripts/token-set-gate.sh` — exit 0 (214 PHPUnit tests, 170 vitest).
- [x] 5.2 `npm run format`, `npm run lint`, `npm run stylelint`, `npm run test:l10n`,
      `npm run check:l10n-js`, `npm run test:fonts`, `npm run audit:token-sets:check`,
      `npm run test:token-set-coverage` — all exit 0.
- [x] 5.3 `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, and the full vitest suite.
- [x] 5.4 Prove each new gate can fail, by moving the real data: a hand edit to a generated
      font stylesheet, a deleted woff2, a REUSE holder renamed, a deleted allow-list entry, a
      probe set whose colours pass.
- [x] 5.5 Live check on a running instance, owed to the coordinator: apply `vng` and confirm
      the admin dialog shows the Avenir upload banner; apply `leiden` and confirm Open Sans
      loads (`document.fonts.check("1em 'Open Sans'")`); confirm `vng`'s login background is
      still #0277BD. Lanes do not touch a running instance.
      Live 2026-10-09 on the throwaway NC 34 instance (:8098): `vng` shows the "Typeface needs an upload" banner for Avenir in the apply dialog; after applying, the login page background is rgb(2, 119, 189) = #0277BD. `leiden`: `document.fonts.check("1em 'Open Sans'")` is true, Open Sans 400 and 700 loaded, body font "Open Sans". Shots ~/memcap-work/build-all/thematiq/live-pass/every-honest-vng-dialog.png, honest-vng-login.png, every-honest-leiden-files.png.

## 6. Next waves, measured and deliberately not in this change

- [x] 6.1 The 8 sets at bridge zero (`amsterdam`, `denhaag`, `epe`, `groningen`, `ridderkerk`,
      `rijkshuisstijl`, `utrecht`, `zwolle`): one `scripts/brands/<id>.json` each, which
      `generate-brand-set.mjs` turns into the component layer. Eight brand files exist for 56
      sets. Each is allow-listed with its upstream named, so the ratchet records the progress.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 1.1 and 1.2 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.2 The 453 internal tokens (`scripts/mapping/internal-tokens.json`: 364 component, 56
      slot, 33 `--cn-*`) that **no** shipped set declares. Start with the 33 `--cn-*`, which
      every fleet app renders.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 2.1 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.3 The 45 Nextcloud theme variables no shipped set can reach: header height, nav and
      sidebar width, container radii, input border widths, clickable areas, base font size and
      line height, status TEXT and HOVER colours, `--color-mark`, box-shadow, scrollbar,
      selection, and the whole Assistant surface.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 2.2 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.4 The 18 sets with no recorded source, including every set authored by hand for a real
      organisation (`vng`, `denhaag`, `amsterdam`, `utrecht`, `rijkshuisstijl`, `opencatalogi`
      and all five `example-*`). The only drift check
      (`openspec/specs/upstream-freshness`) is a daily job, opt-in, off by default, that
      applies nothing and covers only sets with an upstream.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 3.1 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.5 The 28 sets between 8 and 27 on the bridge: thin component coverage, deliberately
      above this change's bar so wave 6.1 is not diluted.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 3.2 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
- [x] 6.6 `css/fonts-conduction.css` is linked by no design system, so its Figtree and IBM
      Plex Mono faces reach an instance through nothing; `openspec/parity/capabilities.json`
      cites it as evidence that fonts are self-hosted. Figtree is now served from the linked
      layer, so the file is dead weight. Retiring it means re-sourcing that evidence line and
      checking whether portaliq links it.
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 4.1 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
