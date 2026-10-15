# Spec delta: token-set-converter (converter-oklch-and-manifest-primary)

## ADDED Requirements

### Requirement: An oklch() or oklab() colour becomes hex

Both converter runtimes MUST write a declaration whose whole value is one `oklch()` or `oklab()` colour
as `#rrggbb`, or `rgba(r, g, b, a)` when it is translucent, clipped to sRGB per channel, and MUST record
the original value in the report. Both runtimes MUST write the same value for the same input.

#### Scenario: Rijkshuisstijl writes its primary as oklch()
@e2e exclude a conversion of a file with no page; proven by tests/vitest/converterOklchAndPrimary.spec.js (gives the set and its manifest a hex primary) and tests/Unit/Service/TokenSetConverterParityTest.php (fixture oklch)
- GIVEN a theme with `--utrecht-button-primary-action-background-color: oklch(38.5% 0.1 250)`
- WHEN it is converted
- THEN `--nldesign-color-primary` and `theming.primary_color` MUST be `#0c4576`
- AND the report MUST carry `colour-normalised` with the original value

### Requirement: A theme without a primary colour takes the set's own

`npm run convert:theme` MUST give a CSS theme that declares neither
`--utrecht-button-primary-action-background-color` nor `--<slug>-color-primary` the primary colour of
the committed `token-sets.json` entry, or the `--primary` value when given, as `--<slug>-color-primary`.
A theme that declares either MUST be converted as written.

#### Scenario: Zwolle
@e2e exclude a command line conversion with no page; proven by tests/vitest/converterOklchAndPrimary.spec.js (a theme without a primary colour takes the set's own)
- GIVEN Zwolle's theme, which has no primary-action colour
- WHEN it is converted with `--slug zwolle`
- THEN the set MUST carry a primary, header and link colour from the committed primary
