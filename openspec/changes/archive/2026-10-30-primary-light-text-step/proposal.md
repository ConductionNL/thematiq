# Proposal: primary-light-text-step

## Why

Nextcloud draws text on the light primary band (and its hover) in `--color-primary-element-light-text` and
`--color-primary-light-text`. The nldesign system mapped both to the brand primary, which misses 4.5:1 on that band or
its hover in 24 shipped sets. Under Zuiddrecht a tertiary button's light band is the hover (#DCE6F2), where #3669A5 is
4.47:1; the board draws that text in the deep primary #234A78.

## What changes

- New token `--nldesign-color-primary-light-text`, defaulting to the primary. Nextcloud's two light-text variables map
  to it.
- The converter's contrast repair holds it at 4.5:1 against `--nldesign-color-primary-light` and
  `--nldesign-color-primary-light-hover`. A pair may name `from`: an undeclared foreground is measured as that token and
  written only when repaired, so a set whose primary passes declares nothing and keeps its primary.
- The 23 failing shipped sets declare the repaired, deeper shade of their own hue. Zuiddrecht, a hand-authored set,
  declares the board's deep primary #234A78 (7.88:1 and 7.17:1).
- The dark palette holds the step at 4.5:1 too; the dark variants are regenerated.
