---
kind: code
---

# The converter reads oklch() and a theme without a primary colour

## Why

Two published themes could not go through the converter (Q-thematiq-10, coordinator ruling 15 Oct 2026).
Rijkshuisstijl 18 writes every colour as `oklch()`. The converter passed those through, so the set's
theming primary became an `oklch()` string that Nextcloud, ContrastService and the dark-variant
generator cannot read. Zwolle's theme names no primary-action colour, so its converted set had no
primary, header or link colour. The nightly sync already solves the second case for buren and venray by
handing in the committed manifest primary; the command line did not.

## What changes

- Both runtimes (`js/lib/tokenConverter.js` and `ColourLiteralParser`) read `oklch()` and `oklab()` and
  write them as hex, or `rgba()` when translucent, with the maths and constants of
  `ColorSpaceConverter`. A colour outside sRGB is clipped per channel. The report keeps the original.
- `npm run convert:theme` gives a CSS theme without any primary token the committed manifest's
  `theming.primary_color` as `--<slug>-color-primary`, through the sync's own `withManifestPrimary()`.
  `--primary <hex>` overrides it.

## Out of scope

- Contrast repair stays off on the command line: a failing pair is reported, not adjusted.
- Relative colour syntax (`oklch(from ...)`), `color-mix()` and `calc()` inside a colour stay as written.
