## Why

Proof run 2 (08 Oct) against the school boards:
- The notice strip over the home ("Let op", "Open dag", "Herfstvakantie") had no tint on any
  school. Portaliq's banner reads the attention roles (`--nldesign-website-attention-*`, carried by
  the bridge as `--thematiq-attention-*`), and no school set named them.
- The strip and the meldingen had a 2px line; the boards draw a hairline.
- Esdoornveen's and the academy's hero search label is semibold 17px on the boards.
- Esdoornveen's hero photo has a slanted bottom-right corner (plan deviation D-3).

## What Changes

The four school token sets name, from their boards:
- `--nldesign-website-attention-background-color` and `-border-color`, with the text in the set's
  ink: wilgenboom `#FDF3D7`/`#ECD391`, vaartveld `#FFF4DE`/`#E8C77D`, esdoornveen
  `#F0EAF7`/`#D2C2E6`. The academy has no strip on its board.
- `--nldesign-website-notice-border-width: 1px` (all four).
- `--nldesign-website-hero-search-label-font-weight: 600` and `-font-size: 1.0625rem`
  (esdoornveen, academy), read by portaliq #1402.
- `--nldesign-hero-image-clip-path` with the board's polygon (esdoornveen), read by portaliq's
  hero aside photo.

`tests/vitest/publicBridgeRoleLayer.spec.js` expects the schools' 1px line and the new values.

## Impact

Only the four school sets. Verified on :8092: the strip is tinted with a 1px line on wilgenboom,
vaartveld and esdoornveen; the hero label is semibold on esdoornveen.
