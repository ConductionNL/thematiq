---
kind: code
---

## Why

The public Zuiddrecht site (portaliq, `feat/zuiddrecht-site-pixel-match`) draws a content page's
title at 44px on a 1.15 line, a lead paragraph at 18px, ink on its notices and a cool grey
(#F4F6F9) band and table header, as the Kop, Home and Contentpagina boards do. The site reads
four of those by name (`--nldesign-website-page-title-size`, `-line-height`,
`--nldesign-website-notice-color`, `--nldesign-color-surface`) and renders without them: the
title keeps heading 2's 32px, the notice text the alert's own ink, and the band and the table
header fall back to the workplace surface #F5F6F8. The lead paragraph reads Utrecht's lead role,
which the bridge pins at 20px for every set.

## What Changes

- **Five names join the website vocabulary**, read by `css/public-bridge.css` so the vocabulary
  audit sees a reader: `--nldesign-website-page-title-size` and
  `--nldesign-website-page-title-line-height` (into `--thematiq-page-title-font-size` and
  `-line-height`, no fallback), `--nldesign-website-lead-font-size` (into
  `--utrecht-paragraph-lead-font-size`, which keeps 20px as its fallback),
  `--nldesign-website-notice-color` (into `--utrecht-alert-color`, no fallback, next to the
  notice ground and border) and `--nldesign-color-surface` (into `--thematiq-surface-color`, no
  fallback).
- **The zuiddrecht set names them**: 2.75rem on 1.15, 18px, #1A1A1A and #F4F6F9. Dark variant
  and reference page regenerated.
- Every pair computed: #1A1A1A on the light blue notice 15.17:1 and on the yellow attention
  strip 15.95:1; on #F4F6F9 the ink 16.08:1, muted text 5.73:1, the link blue 5.21:1 and the
  dark red 7.58:1; in the dark the generated #e5e5e5 on #13202e 13.09:1 and the text, muted text
  and link on the generated surface #141a23 13.6:1, 6.36:1 and 4.88:1.

**Not changed, on purpose:** `--nldesign-website-notice-background-color` and `-border-color`
stay the light blue plain notice of `zuiddrecht-website-type-and-controls` (#EAF0F7, #B9CBE2),
which the signed-in overview board draws next to the yellow "Let op" strip (#FFF4DE, #E8C77D).
One name cannot carry both; the strip needs a name of its own before a set can declare it.

## Capabilities

### Modified Capabilities
- `brand-motif-on-portals`: the website vocabulary grows by the content page title, the lead
  paragraph, the notice text and the site surface.

## Impact
- `css/public-bridge.css`, `css/tokens/zuiddrecht.css`, `css/tokens/dark/zuiddrecht.css`,
  `docs/reference/token-sets/zuiddrecht.md`, `tests/vitest/publicBridgeRoleLayer.spec.js`,
  `tests/vitest/zuiddrechtTokenSet.spec.js`.
- Every other set: unchanged, measured by the control in the bridge test (the lead keeps 20px,
  the four other roles resolve to nothing).
