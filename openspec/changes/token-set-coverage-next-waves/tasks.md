# Tasks: token-set-coverage-next-waves

Moved here on 9 Oct 2026 (decision 126, Q-thematiq-2) from four built changes, so each of those
keeps only its live check. Each task names where it came from. A task is built in a change of its
own, with a delta spec, when it is picked up.

## 1. Bridge-zero sets (from every-shipped-set-selectable 6.1 and 6.2, honest-token-set-coverage 6.1)

- [ ] 1.1 `denhaag`'s component layer, from `scripts/sources/denhaag/*.css`, already vendored
      under EUPL-1.2 for `generate-denhaag-bridge.mjs`. The one of the eight that needs nothing
      invented.
- [ ] 1.2 Brand files for the other seven bridge-zero sets (`amsterdam`, `epe`, `groningen`,
      `ridderkerk`, `rijkshuisstijl`, `utrecht`, `zwolle`). Each declares 3 to 9 palette steps of
      its own (`epe` 226) and a brand file needs about 43 ramp steps, so each needs a sourced
      palette per organisation rather than a derivation. `rijkshuisstijl` last: it is the default
      theme.

## 2. Vocabulary reach (from every-shipped-set-selectable 6.3, honest-token-set-coverage 6.2 and 6.3)

- [ ] 2.1 The 453 internal tokens no shipped set declares
      (`scripts/mapping/internal-tokens.json`: 364 component, 56 slot, 33 `--cn-*`). Start with
      the 33 `--cn-*`.
- [ ] 2.2 The 45 Nextcloud theme variables no shipped set can reach (header height, navigation and
      sidebar width, container radii, input border widths, clickable areas, base font size and
      line height, status text and hover colours, `--color-mark`, box shadow, scrollbar,
      selection, the Assistant surface).

## 3. Sources and thin coverage (from every-shipped-set-selectable 6.3, honest-token-set-coverage 6.4 and 6.5)

- [ ] 3.1 The 18 sets with no recorded source, including every set written by hand for a real
      organisation. The only drift check (`openspec/specs/upstream-freshness`) is opt-in, applies
      nothing and covers only sets with an upstream.
- [ ] 3.2 The 28 sets between 8 and 27 on the bridge, after wave 1.

## 4. Dead weight (from honest-token-set-coverage 6.6)

- [ ] 4.1 Retire `css/fonts-conduction.css`: no design system links it. Re-source the
      `openspec/parity/capabilities.json` evidence line that cites it and check whether portaliq
      links it first.

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
