---
kind: config
depends_on: []
---

# Proposal: example-gemeente-theme

Part of the portal-design programme (2026-10-02). Ruben approved twelve mockups for the
learniq and dossiq sites on portaliq `/site`. The six dossiq mockups wear a fictional
municipality, "Gemeente Esdoornstad". Ruben decided: dossiq gets a new "(EXAMPLE) gemeente"
theme. This change specifies that token set.

## Why

A demo of dossiq on the portal site has no municipality to wear. The four example sets
(`example-basisschool`, `example-voortgezet`, `example-college`, `example-opleider`) are all
schools. Dossiq renders inside the school portal "wilgenboom" today, in brick orange, which is
wrong for a municipal case portal.

A real municipality's set is no answer. A demo in Den Haag's colours reads as Den Haag's
portal. The example sets exist so a demo never borrows a real brand.

## What changes

- A fifth example set, `example-gemeente`, named "(EXAMPLE) Gemeente", in the same shape as the
  four school sets: a brand source file `scripts/brands/example-gemeente.json`, the generated
  `css/tokens/example-gemeente.css` and `css/tokens/dark/example-gemeente.css`, a manifest entry
  in `token-sets.json`, a logo `img/logos/example-gemeente.svg` and a generated reference page.
- The palette comes from the approved mockups: the `.t-gemeente` class and the shared page
  colours in `portal.css` (design D1). Deep sea blue `#12506B`, a dark step `#0B3648`, a pale
  tint `#E7F1F5`, a brown accent `#8F4A00` and a soft grey surface `#F5F7F8`.
- The font is Source Sans 3, as the mockups name it. Thematiq does not bundle it today, so this
  change bundles it under the SIL Open Font License (design D3). That is a new requirement.
- The logo is a fictional mark: the mockups' house glyph on a rounded brand square, with the
  words "Gemeente Esdoornstad". It names no real municipality.

## What this change does not do

- It does not feed the Den Haag component tokens. `denhaag-component-tokens` does that for
  every set, this one included.
- It does not link anything into portaliq. A portal picks the set by id, as it picks the
  school sets today.
- It does not add a gemeente persona or demo data. Those belong to dossiq and hydra.

## Capabilities

- Modified: `token-sets` (one more example set, and the font it needs).

## Impact

`scripts/brands/example-gemeente.json` (new), `css/tokens/example-gemeente.css` and
`css/tokens/dark/example-gemeente.css` (generated), `token-sets.json` (one entry),
`img/logos/example-gemeente.svg` (new), `css/fonts/source-sans-3-latin-*.woff2` and its
`@font-face` block (new), `docs/reference/token-sets/example-gemeente.md` (generated),
`docs/reference/contrast-report.md` (one row), `README.md` and `CHANGELOG.md`.
