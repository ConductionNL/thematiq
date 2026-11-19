# Tasks: token-set-coverage-next-waves

Moved here on 9 Oct 2026 (decision 126, Q-thematiq-2) from four built changes, so each of those
keeps only its live check. Each task names where it came from. A task is built in a change of its
own, with a delta spec, when it is picked up.

## 1. Bridge-zero sets (from every-shipped-set-selectable 6.1 and 6.2, honest-token-set-coverage 6.1)

- [x] 1.1 `denhaag`'s component layer, from `scripts/sources/denhaag/*.css`, already vendored
      under EUPL-1.2 for `generate-denhaag-bridge.mjs`. The one of the eight that needs nothing
      invented.
      Already built when this plan was written down: #1089 (change `denhaag-component-tokens`,
      archived) generates `css/tokens/denhaag.css` from `scripts/brands/denhaag.json` and
      `scripts/brands/denhaag.components.json`, and `docs/reference/token-set-coverage.md` measures
      denhaag at 81 of 87 on the bridge (read 12 Oct). Seven sets are left at 0 of 87, see 1.2.
- [ ] 1.2 Brand files for the other seven bridge-zero sets (`amsterdam`, `epe`, `groningen`,
      `ridderkerk`, `rijkshuisstijl`, `utrecht`, `zwolle`). Each declares 3 to 9 palette steps of
      its own (`epe` 226) and a brand file needs about 43 ramp steps, so each needs a sourced
      palette per organisation rather than a derivation. `rijkshuisstijl` last: it is the default
      theme.
      Measured 19 Nov 2026, not built. Each package below was verified on the npm registry and
      unpacked at the version the set pins (latest in brackets). The count is the distinct literal
      colours in its CSS output, against the 43 ramp steps a brand file fills:
      `amsterdam` @amsterdam/design-system-tokens 4.3.0 (4.4.0) 20, `utrecht` @utrecht/design-tokens
      7.0.0 (8.1.0) 59, `groningen` @nl-design-system-unstable/groningen-design-tokens 5.1.0 (7.0.0)
      59, `epe` @nl-design-system-unstable/epe-design-tokens 1.0.3 (1.0.3) 28, `ridderkerk`
      @nl-design-system-unstable/ridderkerk-design-tokens 1.0.0 (1.0.0) 5, `zwolle`
      @nl-design-system-unstable/zwolle-design-tokens 1.0.0 (1.0.0) 10, `rijkshuisstijl`
      @rijkshuisstijl-community/design-tokens 18.0.1 (18.0.1) 253. Only rijkshuisstijl publishes
      numbered scales (lintblauw, donkerblauw, rood and others) of the depth a brand file needs, and it
      goes last (the default theme).
      For the other six, filling the remaining steps means choosing or deriving colours the
      organisation does not publish, which this task rules out. Open: a per-set decision on how
      to fill those steps, or a brand file shape that leaves a step to Nextcloud rather than to the
      role layer's own colours.

## 2. Vocabulary reach (from every-shipped-set-selectable 6.3, honest-token-set-coverage 6.2 and 6.3)

- [x] 2.1 The 453 internal tokens no shipped set declares
      (`scripts/mapping/internal-tokens.json`: 364 component, 56 slot, 33 `--cn-*`). Start with
      the 33 `--cn-*`.
      The 33 measured 12 Oct in the change `conduction-tokens-recorded-reach`: 6 already follow the
      set through a Nextcloud variable, 19 hold a literal size and 8 are read with a fallback at
      the place of use, so no set gained a declaration. Three fixed greens wait on Q-thematiq-9.
      Still open: the 364 component and 56 slot tokens of Nextcloud, in the same record shape.
      Built 19 Nov as the change `nextcloud-tokens-recorded-reach`: the 420 Nextcloud tokens have a
      recorded reach in `scripts/mapping/nextcloud-token-reach.json` (39 follow, 252 literal, 56
      use-site, 73 unmapped). Fixed colours count as literal and a token that reads only other
      internal tokens inherits their reach (Ruben, 19 Nov).
- [ ] 2.2 The 45 Nextcloud theme variables no shipped set can reach (header height, navigation and
      sidebar width, container radii, input border widths, clickable areas, base font size and
      line height, status text and hover colours, `--color-mark`, box shadow, scrollbar,
      selection, the Assistant surface).
      Measured 15 Oct on 64 sets: all 45 are settable in the admin editor and no set declares the
      token for any of them. Decision 244 splits it:
  - [x] 2.2.1 `--color-info-rgb` maps to `--nldesign-color-info-rgb` in
        `css/systems/nldesign/overrides.css` (63 sets carry it). `tests/vitest/infoRgbMapping.spec.js`
        checks, for every nldesign set in light and both dark scopes, that the triple names the same
        colour as `--color-info` and that body text keeps 4.5:1 on a 20% wash of it.
  - [x] 2.2.2 `--color-mark` from `--utrecht-mark-background-color` (7 sets). Not mapped: Nextcloud
        paints `mark` with that background under the inherited body text, and that pair passes
        4.5:1 in both modes for hoeksche-waard only. Waits on Q-thematiq-12.
        Built 19 Nov (Q-thematiq-12, Ruben: map it only where the contrast passes in both modes):
        `css/token-overrides/hoeksche-waard.css` sets `--nldesign-nc-color-mark` from the set's mark
        background in the light and both dark scopes (13.43:1 and 5.09:1); the other ten sets that now
        declare a mark background keep Nextcloud's tint, each with its measured ratios in
        `docs/reference/token-set-mark-colour.md`. `tests/vitest/markColourMapping.spec.js` holds it.
  - [x] 2.2.3 The other 43 closed as measured, not built: 39 have no source token in any set, and
        the layout four (`--border-width-input`, `--border-radius-container-large`,
        `--default-font-size`, `--default-line-height`) stay with Nextcloud, because a set's body
        size or radius would move Nextcloud's own layout. They stay settable in the admin editor.

## 3. Sources and thin coverage (from every-shipped-set-selectable 6.3, honest-token-set-coverage 6.4 and 6.5)

- [ ] 3.1 The 18 sets with no recorded source, including every set written by hand for a real
      organisation. The only drift check (`openspec/specs/upstream-freshness`) is opt-in, applies
      nothing and covers only sets with an upstream.
      Measured 19 Nov 2026, not built. Since the list was written, `opencatalogi` and the five
      `example-*` sets have a recorded source (generated by `scripts/generate-brand-set.mjs`, the
      source named in each header), and `rijkshuisstijl` is converted from
      @rijkshuisstijl-community/design-tokens 18.0.1. Still hand-authored with no version-pinned
      source: `amsterdam` (header names only the GitHub repo), `utrecht` (the same) and `vng`
      (converted by hand from tilburg-woo-ui `_tokens-vng.scss`). Checked against the packages
      their licence entries name: 6 of amsterdam's 18 colours (#b30000, #cccccc, #d4e4f2, #e6e6e6,
      #e8f0f8, #f5f5f5) are not in @amsterdam/design-system-tokens 4.3.0 or 4.4.0, and 17 of
      utrecht's 20, its primary #24578F among them, are not in @utrecht/design-tokens 7.0.0 or
      8.1.0. Recording those packages as the source would claim a provenance the colours do not
      have. Each set needs re-deriving from its package first, which changes how it looks.
- [ ] 3.2 The 28 sets between 8 and 27 on the bridge, after wave 1.
      Measured 19 Nov 2026 from `docs/reference/token-set-coverage.md`, not built: 25 sets sit
      between 8 and 27 of 87 now (bodegraven-reeuwijk, borne, buren, conduction-new, demodam,
      drechterland, duiven, duo, enkhuizen, haarlem, haarlemmermeer, hoorn, horstaandemaas,
      leidschendam-voorburg, noordoostpolder, nora, provincie-zuid-holland, purmerend,
      riddeliemers, stedebroec, tilburg, venray, vught, westervoort, zevenaar). Raising them takes
      the same brand-file route as 1.2, so this waits on 1.2's open question.

## 4. Dead weight (from honest-token-set-coverage 6.6)

- [x] 4.1 Retire `css/fonts-conduction.css`: no design system links it. Re-source the
      `openspec/parity/capabilities.json` evidence line that cites it and check whether portaliq
      links it first.
      Built 12 Oct as the change `retire-fonts-conduction-stylesheet`: portaliq does not link it,
      the file is gone, the three citations name the stylesheets that serve the faces, and
      `tests/vitest/fontStylesheetsAreLinked.spec.js` keeps every font stylesheet linked.

## 5. Playground handover (from component-playground 8.2 and 9.1)

- [ ] 5.1 Export a token set from the component playground that the vocabulary audit rates
      complete: the reference the converter's output is diffed against.
- [ ] 5.2 Author the OpenWOO reference values (design work; the playground is the tool, not the
      set), commit them as `css/tokens/openwoo.css`, and diff the converter's output for the OpenWOO
      theme against it. Moved here from nlds-theme-converter 9.7 (Q-thematiq-6, decision 137): that
      change closed on the Utrecht paste, because the reference file was never committed and
      `@conduction/theme` 2.1.0 ships OpenWOO only as Style Dictionary sources.

## 6. Shell geometry baselines (from lasuite-shell-geometry 5.2)

- [ ] 6.1 Record screenshot baselines for `tests/e2e/spec-coverage/lasuite-shell-geometry.spec.ts`
      per supported Nextcloud major.

## 7. Generated sets whose buttons wear the hover step (from example-gemeente-theme 4.2)

- [x] 7.1 `example-basisschool`, `example-college`, `example-opleider` and `example-voortgezet` map the
      role layer's link blue `#034591` to brand-400, their hover step, so `--tilburg-interaction-color`
      and with it the Utrecht primary and secondary buttons (and, where the semantic link is the
      primary, the links) wear the hover colour. example-gemeente was fixed on 10 Oct with an
      `upstreamOverrides` entry (370d0b2b). Apply the same per set, keeping example-basisschool's
      deliberately darker link, and extend the `publicBridgeRoleLayer.spec.js` block.
      Built 12 Oct: each of the four brand files names its primary as `--tilburg-interaction-color`;
      example-basisschool also points `--utrecht-link-color` at `--nldesign-color-link` (#8A2D0E).
      Sets, dark variants and reference pages regenerated; 17 new assertions in
      `tests/vitest/publicBridgeRoleLayer.spec.js`, 7 of them red before the change.
- [ ] 7.2 Look at each of the four on a portal site: primary and secondary buttons in the primary
      at rest, the hover step on hover, example-basisschool links in #8A2D0E (live pass, decision 139).
