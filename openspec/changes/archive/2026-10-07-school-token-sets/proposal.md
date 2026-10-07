---
kind: code
---

## Why

Zuiddrecht, the demo municipality, has four schools in the same demo world: Basisschool De
Wilgenboom, Vaartveld College, Esdoornveen (an mbo college) and the Warmtepompacademie. Each has
a finished design (a house style for the website and a workplace layer on Cunningham). A demo of
learniq or portaliq for a school should wear that school's design, not a municipality's and not
the generic `example-*` sets, the way a municipal demo wears `zuiddrecht`.

Most of each design is a token set built exactly as `zuiddrecht` is. Three things in the designs
had no place to land yet: a motif that is not three flat bands, website corners that differ from
the workplace's, and a heading face that differs from the text face on the website.

## What Changes

- **Four token sets**, `wilgenboom`, `vaartveld`, `esdoornveen` and `warmtepompacademie`, each
  with `css/tokens/<set>.css`, a generated dark variant, `css/token-overrides/<set>.css`, four
  logos (colour, white, emblem, grey emblem), a `token-sets.json` entry with a `layout` block and
  a generated reference page. The colours, faces and radii are the designs'.
- **Seven typefaces bundled** (SIL OFL 1.1, Fontsource 5.3.0, latin, woff2): Lexend 400 to 700,
  Red Hat Display 600 to 800, Red Hat Text 400 to 700, Barlow 400 to 700, Barlow Semi Condensed
  600 and 700, IBM Plex Sans 500 (joining 400, 600 and 700) and IBM Plex Mono 400 and 500.
- **The brand stripe may draw an image.** `--nldesign-brand-stripe-image`, when a set names it,
  replaces the three bands in the same box. The schools draw their motifs with it: the hanging
  twigs, the canal and its bank, the slanted cut and the temperature line.
- **Website corners.** `--nldesign-website-border-radius` (buttons and fields) and
  `--nldesign-website-border-radius-large` (cards, dialogs, messages), read only by
  `css/public-bridge.css`, which a portal links and an instance page never does. The workplace
  keeps 8px and 12px.
- **Website headings.** The public bridge hands `--nldesign-component-heading-font-family`, the
  token Nextcloud's headings already read, to the website's heading roles.

**Out of scope:** the motifs in the website header and footer. The designs place them in
portaliq's header and footer, and portaliq draws no motif today, not even Zuiddrecht's three
bands; that is portaliq's work. Den Haag's mijn-omgeving components keep reading
`--nldesign-border-radius` (generated from `scripts/mapping/denhaag-component-tokens.json`), so
their corners follow the workplace scale.

## Capabilities

### New Capabilities
- `school-token-sets`: what each school set declares, the contrast every text pair reaches, the
  motif in the brand stripe and the website corners and heading face.

## Impact

- New files: four token files, four dark variants, four overrides files, sixteen logos, four
  reference pages, nineteen font files per font directory.
- Changed: `css/brand-stripe.css` (one `var()` around the gradient), `css/public-bridge.css`
  (seven radius roles, four heading roles), `scripts/build-fonts.js` (the families table; IBM
  Plex Mono moves from the notice-only table into it), `REUSE.toml`.
- No set changes: every new token is undeclared in every existing set, the bridge roles resolve
  to the guaranteed-invalid value when a set names no website radius (a consumer's own fallback
  then applies), and the heading roles fall back to the text face. `example-basisschool`,
  `example-voortgezet`, `example-college` and `example-opleider` are untouched.
- No migration. Additive, so a minor version.
