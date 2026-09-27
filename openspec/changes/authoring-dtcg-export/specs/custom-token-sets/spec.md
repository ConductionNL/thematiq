# Spec delta: custom token sets (authoring-dtcg-export)

The DTCG import changes in two places: every DTCG colour space is accepted and converted to sRGB,
and thematiq's own extension is read instead of ignored. The requirement title stays as it is in
`openspec/specs/custom-token-sets/spec.md`, because a modified requirement is matched by its title.
The em-dashes of the old text are replaced by colons and commas.

## MODIFIED Requirements

### Requirement: W3C Design Tokens JSON Import

The upload control MUST also accept a JSON file in the W3C DTCG Design Tokens Format Module
v2025.10. The importer MUST implement the format's core semantics, not a lookalike subset:

- **`$type` resolution with group inheritance**: a token's type is its own `$type`, else the
  `$type` of the nearest ancestor group declaring one. Tokens whose resolved type is absent MUST
  be skipped with reason `missing-type` (never guessed from the value shape).
- **Typed `$value` handling** for at least: `color` (legacy string literals `#rrggbb`/`rgb()`
  AND the v2025.10 object form in every colour space the DTCG colour module lists; see the colour
  rules below), `dimension` (`{value, unit}` object serialized as `<value><unit>`; legacy string
  form accepted), `fontFamily` (string or array serialized as a quoted CSS font stack),
  `fontWeight` (number, or v2025.10 weight keyword normalized to its numeric value), and
  **composite `typography`** (each sub-property, `fontFamily`, `fontSize`, `fontWeight`,
  `lineHeight`, mapped individually where a corresponding `--nldesign-*` target exists, unmapped
  sub-properties counted as skipped).
- **Colour objects**: a colour in `srgb` MUST use its `hex` when present, else its components. A
  colour in `srgb-linear` MUST apply the sRGB transfer function to its components before scaling
  to 0..255. A colour in any other colour space MUST be converted to sRGB with the CSS Color 4
  conversions. A converted colour outside the sRGB gamut MUST use the document's `hex` when
  present and be reported `adapted` with reason `out-of-gamut-hex-fallback`, else each channel
  MUST be clipped to 0..1 and reported `adapted` with reason `out-of-gamut-clipped` and the
  original value. An `alpha` below 1 MUST be kept as an 8-digit hex value `#rrggbbaa`. A colour
  space the colour module does not list MUST be skipped with reason `unsupported-color-space`.
- **`$extensions`** MUST be ignored without error and without affecting mapping
  (passthrough-ignore), with one exception: a token whose
  `$extensions["nl.conduction.thematiq"].cssVariable` names a custom property MUST be mapped to
  that name before the suffix table is tried, and the document root's
  `$extensions["nl.conduction.thematiq"].cssOnly` map MUST be read as declarations. Both still pass
  the validator. **`$deprecated`** (boolean `true` or string form) on an imported token MUST
  surface a warning naming the token path (and the string message when given) while still
  importing the value.
- Recognized tokens MUST be mapped to `--nldesign-*` variables via the published mapping table;
  unmapped tokens MUST be skipped and counted with reason `unmapped-path`. The mapped result
  MUST pass through the same whitelist, serialization, and storage pipeline as CSS uploads
  (`CustomTokenSetValidator`; atomic write to `css/tokens/custom-*.css`). DTCG hardening
  changes what the mapper understands, never where or how sets are stored.

#### Scenario: DTCG color tokens map onto the nldesign vocabulary

@e2e exclude mapping logic, PHPUnit on the mapper with DTCG fixtures
- GIVEN a `huisstijl.tokens.json` containing `{ "color": { "primary": { "$type": "color", "$value": "#154273" }, "on-primary": { "$type": "color", "$value": "#ffffff" } } }`
- WHEN the admin uploads it with display name "Eigen huisstijl"
- THEN `css/tokens/custom-eigen-huisstijl.css` MUST contain `--nldesign-color-primary: #154273` and `--nldesign-color-primary-text: #ffffff`
- AND the response MUST report `imported: 2, skipped: 0`

#### Scenario: Group-level $type is inherited by descendant tokens

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a document `{ "color": { "$type": "color", "primary": { "$value": "#154273" } } }`
  where the token itself declares no `$type`
- WHEN the document is imported
- THEN `color.primary` MUST resolve type `color` from its group
- AND MUST be imported as `--nldesign-color-primary: #154273`

#### Scenario: v2025.10 object color and dimension values serialize to CSS

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.primary` token whose `$value` is the object form with an sRGB color space and
  hex fallback for `#154273`, and a `dimension.border-radius` token with
  `$value: { "value": 8, "unit": "px" }`
- WHEN the document is imported
- THEN the emitted declarations MUST be `--nldesign-color-primary: #154273` and
  `--nldesign-border-radius: 8px`
- AND a color token in a colour space the DTCG colour module does not list MUST be skipped with
  reason `unsupported-color-space` and its path listed

#### Scenario: An oklch brand colour is converted to sRGB

@e2e exclude mapping logic, PHPUnit on the mapper and ColorSpaceConverter
- GIVEN an administrator on Settings > Administration > Theming
- AND a `color.primary` token `{ "colorSpace": "oklch", "components": [0.5, 0.1, 250] }`
- WHEN the administrator uploads the document
- THEN `--nldesign-color-primary` MUST be `#32669a`, each channel within 1 of that value
- AND the token MUST count as imported, not skipped

#### Scenario: A display-p3 colour outside sRGB is clipped and reported

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.primary` token `{ "colorSpace": "display-p3", "components": [1, 0, 0] }` with no `hex`
- WHEN the document is imported
- THEN `--nldesign-color-primary` MUST be `#ff0000`
- AND the report MUST list the token as `adapted` with reason `out-of-gamut-clipped` and the original value

#### Scenario: A linear sRGB grey keeps its lightness

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.background` token `{ "colorSpace": "srgb-linear", "components": [0.5, 0.5, 0.5] }` with no `hex`
- WHEN the document is imported
- THEN `--nldesign-color-background` MUST be `#bcbcbc`
- AND it MUST NOT be `#808080`

#### Scenario: A translucent colour keeps its alpha

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `color.primary-light` token `{ "colorSpace": "srgb", "components": [0.0824, 0.2588, 0.451], "alpha": 0.5 }`
- WHEN the document is imported
- THEN `--nldesign-color-primary-light` MUST be `#15427380`

#### Scenario: The thematiq extension names the target

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a token at `nldesign.color.error` carrying
  `"$extensions": { "nl.conduction.thematiq": { "cssVariable": "--nldesign-color-error" } }`
- WHEN the document is imported
- THEN `--nldesign-color-error` MUST receive the token's value
- AND the token MUST NOT be skipped as `unmapped-path`

#### Scenario: Composite typography token maps sub-values individually

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a `typography.font-family`-adjacent composite token of `$type: "typography"` whose
  `$value.fontFamily` is `["Fira Sans", "sans-serif"]`
- WHEN the document is imported
- THEN `--nldesign-font-family` MUST be emitted as the serialized font stack
- AND composite sub-properties without an `--nldesign-*` target MUST be counted as skipped, each
  with its sub-path

#### Scenario: Unmapped DTCG tokens degrade to skipped counts

@e2e exclude mapping tolerance, PHPUnit on the mapper
- GIVEN a DTCG file containing a recognized `color.primary` token and an unrecognized
  `shadow.elevation-1` token
- WHEN the admin uploads it
- THEN the upload MUST succeed with `imported: 1, skipped: 1`
- AND the skipped token path MUST be listed in the response with reason `unmapped-path`

#### Scenario: Deprecated token imports with a surfaced warning

@e2e exclude mapping logic, PHPUnit on the mapper
- GIVEN a mapped token carrying `"$deprecated": "Use color.brand.primary instead"`
- WHEN the document is imported
- THEN the token MUST still be imported
- AND the response `warnings` MUST contain the token path and the deprecation message
- AND the admin panel MUST display the warning after upload

#### Scenario: Malformed JSON is rejected

@e2e exclude parse guard, PHPUnit on the controller
- GIVEN a `.json` upload that is not valid JSON
- WHEN the admin uploads it
- THEN the upload MUST be rejected with HTTP 422 and a localized parse error
- AND no file MUST be written
