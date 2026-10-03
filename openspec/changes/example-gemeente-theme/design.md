# Design: example-gemeente-theme

Read at thematiq `development` `df5321f6` on 2026-10-02. Mockups of record:
`~/memcap-work/portal-design/canvas/project/portal.css` and the six `Dossiq*.dc.html`
artboards (design canvas https://claude.ai/artifact/3Jy3r5e5f9v9ktCLxisNG6).

## D1. The palette, and where every value comes from

The mockups declare five brand values on `.t-gemeente` and a shared page layer on `.p-page`.
Every value below is quoted from `portal.css`; nothing is invented.

| Brand source key | Value | Mockup source |
| --- | --- | --- |
| `brand-100` | `#E7F1F5` | `.t-gemeente --brand-tint` |
| `brand-300` | `#12506B` | `.t-gemeente --brand` |
| `brand-400` | `#0B3648` | `.t-gemeente --brand-dark` |
| `accent-300` | `#8F4A00` | `.t-gemeente --accent` |
| `gray-50` | `#F5F7F8` | `.t-gemeente --soft` |
| `gray-300` | `#D9D4CE` | `.p-page --line` |
| `gray-600` | `#55514C` | `.p-page --muted` |
| `black-txt` | `#1B1A18` | `.p-page --ink` |
| `white` | `#FFFFFF` | `.p-page --ground`, `.t-gemeente --brand-ink` |
| `green-100` / `green-300` | `#E6F3EB` / `#17603A` | `.p-page --ok-bg` / `--ok` |
| `orange-100` / `orange-300` | `#FCF1D8` / `#7A4800` | `.p-page --warn-bg` / `--warn` |
| `red-100` / `red-300` | `#FCEBE9` / `#9C231C` | `.p-page --bad-bg` / `--bad` |
| `info` | `#1D4F91` on `#E8F0FB` | `.p-page --info` / `--info-bg` |

The school sets carry a full ramp: gray 50 to 950, brand 100 to 500, accent 100 to 400, and
green, red and orange 100 to 400. The mockups do not give every step. The steps they leave
open are `brand-200`, `brand-500`, `accent-100`, `accent-200`, `accent-400`, the 200 and 400
steps of green, red and orange, and gray 100, 200, 400, 500, 700, 800, 900 and 950.

Those steps are chosen when the set is built, under one rule the school sets already follow
(their `_comment_source`): every step a component can put text on, or under white text,
reaches 4.5:1 against white. That is the 300 and 400 steps of each family and gray-500 and
darker. gray-400 is the control border and reaches 3:1. The build records each chosen step's
ratio in the brand file's `ramp[].from`, as the school sets do. This keeps the spec free of
made-up hex values and still makes the build checkable.

Contrast of the quoted values, computed with the WCAG relative-luminance formula on
2026-10-02:

| Pair | Ratio |
| --- | --- |
| white on `#12506B` (primary text on primary) | 8.80:1 |
| white on `#0B3648` (hover) | 12.84:1 |
| `#12506B` on white (link) | 8.80:1 |
| `#8F4A00` on white (accent) | 6.67:1 |
| `#0B3648` on `#E7F1F5` (selected item) | 11.19:1 |
| `#55514C` on white (muted text) | 7.87:1 |
| `#55514C` on `#F5F7F8` | 7.33:1 |
| `#17603A` on `#E6F3EB` (success) | 6.64:1 |
| `#7A4800` on `#FCF1D8` (warning) | 6.79:1 |
| `#9C231C` on `#FCEBE9` (error) | 6.81:1 |
| `#1D4F91` on `#E8F0FB` (info) | 7.09:1 |
| `#D9D4CE` on white (line) | 1.47:1 |

The line colour is decorative only (dividers). It is not the control border; gray-400 is.

## D2. The semantic layer

Section D of the generated file, the `--nldesign-*` layer, maps as the school sets map it:
`primary` `#12506B`, `primary-hover` `#0B3648`, `primary-text` `#FFFFFF`, `primary-light`
`#E7F1F5`, `text` `#1B1A18`, `text-muted` `#55514C`, `link` `#12506B`, `link-hover` `#0B3648`,
`border` `#D9D4CE`, `header-background` `#FFFFFF` (the mockup header is white with a brand
navigation bar under it), `nav-background` `#12506B`, `error` `#9C231C`, `warning` `#7A4800`,
`success` `#17603A`, `info` `#1D4F91`. The `-rgb` twins follow from those. Border radius
follows the mockups: buttons and choice rows 10px, cards and lists 14px, inputs 8px, badges
999px.

## D3. The font

The mockups set `'Source Sans 3', 'Source Sans Pro', Verdana, sans-serif`. Thematiq bundles
Fira Sans, Figtree, IBM Plex Mono and Marianne (`css/fonts/`), not Source Sans 3. A set that
names a font the app does not ship falls through to Verdana on every machine without it,
which is not what Ruben approved.

Source Sans 3 is published by Adobe under the SIL Open Font License 1.1, the licence Fira
Sans already ships under here. The build bundles the latin subset in weights 400, 600 and 700
as woff2, adds an `@font-face` block beside Fira Sans in `css/fonts.css`, and records the
licence in `REUSE.toml` and `LICENSES/`.

Alternative considered: use Fira Sans, as the school sets do. Rejected for now because the
mockups were approved with Source Sans 3. This is an open decision for Ruben (see tasks).

## D4. The logo

`img/logos/example-gemeente.svg`, 240 by 64, in the style of the school logos: a rounded
square in `#12506B` holding the mockups' house glyph in white (paths `M4 21V9l8-5 8 5v12`,
`M9 21v-6h6v6`, `M2 21h20`), then "Gemeente" and "Esdoornstad" set in two lines. It carries
`role="img"`, an `aria-label` ending in "(example)" and a comment that it is fictional. The
dark-surface logo follows the pattern of thematiq#787.

## Risks

- **A demo mistaken for a real municipality.** The build checks "Esdoornstad" against the CBS
  list of municipalities before it ships. The set name carries "(EXAMPLE)" and the
  description says "Not a real organisation", as the school sets do.
- **A font file adds weight.** The three woff2 files load only when the set is active. The
  build reports their size in the PR.
