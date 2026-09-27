# Write design tokens in the DTCG format

## Why

A government house style lives in more than one tool. The design team keeps it in Tokens Studio or
another DTCG tool, a supplier builds a portal with Style Dictionary, and the Nextcloud instance runs
it through thematiq. Those tools exchange tokens in the W3C Design Tokens Community Group (DTCG)
format. Thematiq reads that format and cannot write it.

Two halves are missing:

1. **Writing DTCG.** The only download of a set is its served CSS.
   `CustomTokenSetController::export()` (`lib/Controller/CustomTokenSetController.php:561-572`)
   returns `text/css`, and only for custom sets. A shipped set cannot be downloaded at all from the
   panel. A designer who changes a colour in thematiq's token editor has no way to carry it back.
2. **Colour spaces other than sRGB.** `DesignTokensMapper` accepts only `srgb` and `srgb-linear`
   (`lib/Service/DesignTokensMapper.php:57`). Every other colour space is skipped as
   `unsupported-color-space` (`:611-614`). A brand colour defined in `oklch` or `display-p3` is lost.

Reading the sRGB half also has two faults that a writer would expose on the first round trip:

- `srgb-linear` components are written as if they were gamma-encoded sRGB.
  `componentsToHex()` (`:644-652`) multiplies each component by 255 without the sRGB transfer
  function. A linear grey of 0.5 becomes `#808080`. The correct sRGB value is `#bcbcbc`.
- The colour object's `alpha` is never read. `componentsToHex()` writes three channels only, so a
  translucent brand overlay imports as an opaque colour.

Matrix evidence (quoted from `rowblock.py aut-dtcg-colour-objects`):

### Row `aut-dtcg-colour-objects` (thematiq matrix, area sync)

- Capability: Read and write tokens in the stable DTCG specification, including colour values
  stored as objects with an explicit colour space.
- Own rating: partial; built.state `built`. Built evidence: lib/Service/DesignTokensMapper.php:605-630
  serializeColorObject() reads DTCG v2025.10 colour objects (hex or components) in the sRGB family
  and reports other colour spaces as unsupported-color-space (48-56); writing DTCG is not supported
  (aut-dtcg-bulk-convert)
- Demand: featureRequest at https://github.com/tokens-studio/figma-plugin/issues/3236
  (interchange with other government token tools depends on the stable DTCG colour format)
- Tokens Studio (Figma plugin and platform) rated `partial`: tokens-studio/figma-plugin@2.12.1
  packages/tokens-studio-for-figma/src/utils/color/gradientTokenToCss.ts:7-15 reads
  {colorSpace, components} colour objects only inside gradients; colour tokens are strings; related
  board post https://feedback.tokens.studio/p/dtcg-format-update-on-color-tokens-and-support-for-color
  (12 votes, Discussion)
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, Microsoft 365 organisational
  branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `no`, openDesk
  theming `no`, Liferay DXP (style books, themes, client extensions) `no`

The row is partial. Reading DTCG colour objects in the sRGB family is built. Writing DTCG and every
other colour space are missing.

## What changes

- An administrator can download any token set, shipped or custom, as a DTCG document. The button
  sits next to the token set dropdown and next to each custom set.
- Every custom property in the set becomes a token. Colours are written as DTCG colour objects
  with a colour space, components, alpha when below 1, and an sRGB `hex` fallback.
- A colour written in a CSS Color 4 function (`oklch()`, `lab()`, `color(display-p3 ...)`) keeps
  its own colour space in the export.
- Values that have no DTCG type (a gradient, `em`, a keyword) are kept in a thematiq extension at
  the document root. The document stays valid DTCG, and a thematiq round trip loses nothing.
- Every token carries its CSS name in the `nl.conduction.thematiq` extension. On import, thematiq
  maps a token with that extension straight to its CSS name, so an exported set imports back to the
  same `--nldesign-*` values.
- Import accepts every colour space in the DTCG colour module. A colour outside sRGB is converted
  to sRGB, because contrast checks, dark variants and Nextcloud core theming all read sRGB. A colour
  outside the sRGB gamut uses the document's `hex` fallback, or is clipped and reported as adapted.
- Import applies the sRGB transfer function to `srgb-linear` components and keeps `alpha` as an
  8-digit hex value.

## Capabilities

### New capabilities

- `token-set-dtcg-export`: writing a token set as a DTCG document, the value typing rules, the
  thematiq extension and the round-trip guarantee.

### Modified capabilities

- `custom-token-sets` (`openspec/specs/custom-token-sets/spec.md`): the requirement "W3C Design
  Tokens JSON Import" changes in two places. Every DTCG colour space is accepted and converted to
  sRGB. The `nl.conduction.thematiq` extension is read instead of ignored. The rest of the
  requirement is unchanged.

## Impact

- **Code**: new `lib/Service/DesignTokensWriter.php`; new `lib/Service/ColorSpaceConverter.php`
  (CSS Color 4 conversions to and from sRGB, gamut check), used by the writer and the mapper;
  `lib/Service/DesignTokensMapper.php` (`serializeColorObject()`, `componentsToHex()`, the extension
  lookup in `resolveTarget()`); `lib/Controller/CustomTokenSetController.php` (new `exportDtcg()`);
  `appinfo/routes.php`; `templates/settings/admin.php` and `js/admin.js` (two download buttons);
  `l10n/en.json`, `l10n/nl.json`; `docs/features/import-export.md`.
- **Endpoint**: new `GET /apps/thematiq/settings/tokensets/{id}/dtcg`, admin-only. The existing
  CSS export stays as it is.
- **Sibling repos**: none. `js/lib/tokenConverter.js` keeps refusing DTCG input and pointing at the
  server (`:2047-2055`), which stays the one DTCG authority.
- **Related row**: `aut-dtcg-bulk-convert` (the evidence names it) is not part of this change.

## Rows

- `aut-dtcg-colour-objects`, from the thematiq matrix (`openspec/parity/capabilities.json`).
