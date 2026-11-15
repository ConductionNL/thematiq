# Design: converter-oklch-and-manifest-primary

The JavaScript side copies the three matrices of `ColorSpaceConverter` (OKLab to LMS, LMS to XYZ D65,
XYZ to linear sRGB) instead of loading another library, so the two runtimes cannot round differently.
The PHP side calls `ColorSpaceConverter::toSrgb()` itself. Percentages follow CSS Color 4: L 100% is 1,
chroma, a and b 100% is 0.4, and `none` is 0.

A parity fixture (`tests/Unit/fixtures/converter/oklch.input.css`) holds both runtimes to the same
file, byte for byte, through the existing `TokenSetConverterParityTest`.

The manifest primary is applied before conversion, as the sync does, and only to a `.css` input. A set
that has a primary-action token or its own `--<slug>-color-primary` is not touched.
