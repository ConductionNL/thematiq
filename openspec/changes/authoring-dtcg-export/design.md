# Design: write design tokens in the DTCG format

## Where it fits (development `b4e7568`)

- **Reading DTCG** is one class. `DesignTokensMapper::map()` (`lib/Service/DesignTokensMapper.php:156-185`)
  flattens the tree, resolves aliases and serialises each type. Colours go through
  `serializeColor()` (`:587-597`) and `serializeColorObject()` (`:610-633`). The accepted colour
  spaces are `SRGB_COLOR_SPACES` (`:57`). `componentsToHex()` (`:644-652`) scales each of three
  components by 255. `resolveTarget()` (`:799-812`) maps a dotted path to a `--nldesign-*` name by
  the longest suffix in `MAPPING` (`:107-120`), twelve entries. Every `$` key, `$extensions`
  included, is skipped while walking (`:228-230`).
- **Downloading a set** is one method. `CustomTokenSetController::export()`
  (`lib/Controller/CustomTokenSetController.php:560-572`) returns the served CSS of a custom set as
  `text/css`. It refuses ids it cannot read. Shipped sets have no download. The token editor's
  "Download" (`js/admin.js:2470-2472`, `exportOverrides()` at `:2875`) exports the overrides CSS
  only.
- **Colour consumers read sRGB.** `ContrastService::parseColor()` (`lib/Service/ContrastService.php:242-274`)
  reads `#rgb`, `#rrggbb` and `rgb()`/`rgba()` only. `DarkPaletteService::deriveDarkDeclarations()`
  (`lib/Service/DarkPaletteService.php:289-325`) skips any token `parseColor()` cannot read
  (`:316-321`). Nextcloud core theming takes a hex primary colour.
- **The JS runtime refuses DTCG.** `js/lib/tokenConverter.js:2047-2055` throws a 422 that sends
  DTCG to the server. That stays: the server is the one DTCG authority.
- **The set's own declarations** are read with `CssParserService::parseRootBlock()`, as
  `CustomTokenSetController::mapFromCss()` does (`:429`). `DarkPaletteService::resolveLightDeclarations()`
  (`:259-275`) shows the layering with `defaults.css`, which the export deliberately does not do
  (decision 2).

## Decision 1: one writer, the mirror of the mapper

A new `DesignTokensWriter` turns a declaration map into a DTCG document. It lives beside
`DesignTokensMapper` and shares its knowledge through one new helper, `ColorSpaceConverter`, which
both classes use.

Rejected: writing DTCG in `js/lib/tokenConverter.js` and offering it in the browser. The JS runtime
refuses DTCG on purpose, and two DTCG implementations is the drift `nlds-theme-converter` exists to
prevent.

## Decision 2: export what the set declares, not the defaults under it

The export holds the custom properties of `css/tokens/{id}.css`, nothing more. Layering
`defaults.css` under it would freeze today's defaults into every exported brand. The overrides from
the token editor are not included either: they belong to the instance, not to the set.

Rejected: exporting the resolved set with defaults. It is the right answer for a reference page
(the lane change `catalogue-token-reference-pages`), not for interchange.

## Decision 3: token paths and the thematiq extension

A CSS name becomes a path by its first two segments. `--nldesign-color-primary-hover` becomes
`nldesign` > `color` > `primary-hover`. `--utrecht-button-background-color` becomes `utrecht` >
`button` > `background-color`. The name stays readable in any tool, and the twelve suffixes in
`MAPPING` still match (`nldesign.color.primary-hover` ends with `color.primary-hover`).

Every token also carries `"$extensions": {"nl.conduction.thematiq": {"cssVariable": "--nldesign-color-primary-hover"}}`.
The DTCG format names extensions by reverse domain. On import, `resolveTarget()` first reads that
extension. When it names a custom property the validator accepts, that is the target. Otherwise the
suffix table decides, as today. This is what makes the round trip exact: the suffix table has twelve
entries for eight distinct targets, and the converter's semantic layer alone requires 26 tokens
(`openspec/changes/nlds-theme-converter/proposal.md`).

Rejected: a longer suffix table. It would still miss component and palette tokens, and every new
semantic token would need a second entry.

## Decision 4: typing each value

The writer gives each value a DTCG type by its shape. The name helps only where the shape is
ambiguous.

| CSS value | DTCG type | `$value` |
|---|---|---|
| `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`, `rgb()`, `rgba()`, `hsl()`, `hsla()`, a named colour | `color` | object in `srgb`, with `hex` |
| `oklch()`, `oklab()`, `lab()`, `lch()`, `hwb()`, `color(<space> ...)` | `color` | object in that colour space, with an sRGB `hex` fallback |
| number with `px` or `rem` | `dimension` | `{value, unit}` |
| number with `ms` or `s` | `duration` | `{value, unit}` |
| `cubic-bezier(a, b, c, d)` | `cubicBezier` | `[a, b, c, d]` |
| a font stack, name contains `font-family` | `fontFamily` | array of family names |
| 1 to 1000, name contains `font-weight` | `fontWeight` | number |
| a plain number | `number` | number |
| `var(--x)` where `--x` is in the set | same type as `--x` | alias `{group.path}` |

Anything else has no DTCG type: `em`, `%`, `vh`, gradients, `url()`, keywords, `color-mix()`,
`light-dark()`. Those go into `$extensions["nl.conduction.thematiq"].cssOnly` at the document root
as `{name: value}`. A DTCG tool ignores that block. Thematiq's import reads it back through the
validator.

Task 1.1 checked the table against the DTCG text (v2025.10, read 2 Oct 2026):

- Format Module §8.2 "Dimension": `{value, unit}`, units `px` and `rem` only.
- §8.5 "Duration": `{value, unit}`, units `ms` and `s` only.
- §8.6 "Cubic Bézier": `[P1x, P1y, P2x, P2y]`, both x in 0..1 (a curve outside that is written to `cssOnly`).
- §8.3 "Font family" (string or array), §8.4 "Font weight" (1 to 1000, or a keyword), §8.7 "Number".
- §5.2.3 "Extensions": reverse domain names recommended, hence `nl.conduction.thematiq`.
- §5.2.4 "Deprecated": `true`, `false` or a string.
- Color Module §4.1 "Format": `colorSpace` and `components` required, `alpha` and `hex` optional;
  §4.2 "Supported color spaces": srgb, srgb-linear, hsl, hwb, lab, lch, oklab, oklch, display-p3,
  a98-rgb, prophoto-rgb, rec2020, xyz-d65, xyz-d50 (`ColorSpaceConverter::SPACES`), with hsl and hwb
  components 0..100 and oklab/oklch lightness 0..1.

Rejected: writing a token without `$type` for untyped values. The document would be invalid DTCG,
and the mapper refuses such tokens with `missing-type` by its own spec.

## Decision 5: colours outside sRGB

On **export**, a CSS Color 4 function keeps its colour space. `oklch(0.5 0.1 250)` is written as
`{"colorSpace": "oklch", "components": [0.5, 0.1, 250], "hex": "#32669a"}`. The `hex` is the sRGB
conversion, clipped when out of gamut, as the DTCG fallback.

On **import**, a colour in any DTCG colour space is converted to sRGB and stored as hex:

- in gamut: the converted value;
- out of gamut, with a `hex` in the document: that `hex`, reported `adapted` with
  `out-of-gamut-hex-fallback`;
- out of gamut, no `hex`: each channel clipped to 0..1, reported `adapted` with
  `out-of-gamut-clipped` and the original value.

The stored value is sRGB because every consumer reads sRGB: `ContrastService::parseColor()`, the
dark palette, and Nextcloud core theming. Storing `oklch()` in the set would silently switch off
contrast warnings and dark derivation for that token (`DarkPaletteService.php:316-321`).

Rejected: teaching every consumer CSS Color 4 now. It is the better end state and a far larger
change. The report keeps the loss visible until then.

`srgb-linear` components get the sRGB transfer function before scaling. `alpha` below 1 is kept as
an 8-digit hex value. `ContrastService` learning 8-digit hex is owned by the change
`authoring-token-value-types`. Until it lands, a translucent colour shows as "not evaluated", which
is honest.

## Decision 6: endpoint and buttons

`GET /apps/thematiq/settings/tokensets/{id}/dtcg` with `#[AuthorizedAdminSetting(Admin::class)]`.
It accepts any id `TokenSetService::isValidTokenSet()` accepts, and 404s otherwise. It returns
`application/json` as `{id}.tokens.json` with `Content-Disposition: attachment`. The document root
carries `$description` with the set name and `$extensions["nl.conduction.thematiq"]` with the set
id, the app version and the `cssOnly` block.

The panel gets two buttons. "Download as design tokens" next to the token set dropdown exports the
selected set. The same action sits next to "Download" in each custom set row.

No audit entry: a download changes no configuration.

## Risks

- **Colour maths drift between PHP and the reference.** Conversions follow the CSS Color 4 sample
  code. Tests pin one value per colour space, each channel within 1 of the reference.
- **Other tools read extensions differently.** Tokens Studio reads `$extensions` it knows and keeps
  the rest. A tool that drops unknown extensions loses the CSS names, and the import falls back to
  the suffix table. The round-trip guarantee holds only for thematiq to thematiq.
- **Palette and component names on import.** They pass through the converter's naming rules, so a
  set exported as `amsterdam` and imported as "Test" renames palette steps to `--test-*`. The
  `--nldesign-*` layer is exact.

## Out of scope

- Dark values in the export. How a token carries a dark value is decided in
  `authoring-token-value-types`. The export follows that change.
- Bulk conversion between formats (row `aut-dtcg-bulk-convert`).
- A public, non-admin DTCG feed.
- Exporting several brands as one Tokens Studio file (see `authoring-multi-brand-token-source`).
