---
kind: code
---

## Why

Four built changes carried follow-up work they called out of scope themselves:
`every-shipped-set-selectable` (section 6), `honest-token-set-coverage` (section 6, "Next waves,
measured and deliberately not in this change"), `component-playground` (8.2 and 9.1) and
`lasuite-shell-geometry` (5.2). Left there, those four changes can never archive, and the work
is spread over four task lists that each describe a part of it.

This change is the one place that work now lives (decision 126, Q-thematiq-2, 9 Oct 2026). It
holds the plan only. Nothing here is built yet, and each wave below gets its own specification
when it is picked up.

## What Changes

- **Brand files for the 8 bridge-zero sets** (`amsterdam`, `denhaag`, `epe`, `groningen`,
  `ridderkerk`, `rijkshuisstijl`, `utrecht`, `zwolle`): one `scripts/brands/<id>.json` each,
  turned into a component layer by `scripts/generate-brand-set.mjs`. `denhaag` goes first,
  because its source is already vendored under `scripts/sources/denhaag/`. `rijkshuisstijl` goes
  last: it is the default theme, so regenerating it changes every unconfigured instance.
- **The 453 internal tokens no shipped set declares**
  (`scripts/mapping/internal-tokens.json`: 364 component, 56 slot, 33 `--cn-*`), starting with
  the 33 `--cn-*` tokens every fleet app renders.
- **The 45 Nextcloud theme variables no shipped set can reach**: header height, navigation and
  sidebar width, container radii, input border widths, clickable areas, base font size and line
  height, status text and hover colours, `--color-mark`, box shadow, scrollbar, selection and the
  Assistant surface.
- **The 18 sets with no recorded source**, including every set written by hand for a real
  organisation (`vng`, `denhaag`, `amsterdam`, `utrecht`, `rijkshuisstijl`, `opencatalogi` and the
  five `example-*` sets), which no drift check covers today.
- **The 28 sets between 8 and 27 on the bridge**: thin component coverage, after the bridge-zero
  wave.
- **Retire `css/fonts-conduction.css`**: no design system links it. Re-source the
  `openspec/parity/capabilities.json` evidence line that cites it, and check whether portaliq
  links it, before deleting.
- **The playground's handover to the converter**: export a token set from the component
  playground that the vocabulary audit rates complete, and author the OpenWOO reference values
  the converter's output is compared with (design work).
- **Screenshot baselines for the La Suite shell geometry**, one per supported Nextcloud major.

## Impact

- No code in this change. Each wave becomes its own change with a delta spec when it is built.
- The four source changes keep only their live checks open.
