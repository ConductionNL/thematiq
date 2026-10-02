# Spec delta: token set DTCG export (authoring-dtcg-export)

An administrator can download any token set as a W3C Design Tokens (DTCG) document, carry it to
another tool, and import it back into thematiq without losing a value.

## ADDED Requirements

### Requirement: Any token set can be downloaded as a DTCG document

`GET /apps/thematiq/settings/tokensets/{id}/dtcg` MUST return the named set as a DTCG v2025.10
document for every id `TokenSetService::isValidTokenSet()` accepts, shipped or custom. The response
MUST be `application/json` with `Content-Disposition: attachment; filename="{id}.tokens.json"`. An
unknown id MUST return 404. The endpoint MUST be admin-only and MUST write no audit entry. The
settings panel MUST offer "Download as design tokens" next to the token set dropdown, for the
selected set, and in each custom set row.

#### Scenario: An administrator downloads a shipped set
- GIVEN an administrator on Settings > Administration > Theming
- AND "Gemeente Amsterdam" is selected in the token set dropdown
- WHEN the administrator clicks "Download as design tokens"
- THEN the browser MUST download `amsterdam.tokens.json`
- AND the file MUST parse as JSON with at least one token carrying `$type` and `$value`

#### Scenario: An administrator downloads a custom set from its row
- GIVEN the custom set "Gemeente Voorbeeld" exists
- WHEN the administrator clicks "Download as design tokens" in its row
- THEN the browser MUST download `custom-gemeente-voorbeeld.tokens.json`

#### Scenario: An unknown set is refused
@e2e exclude API error path, covered by the Newman collection
- GIVEN no set with the id `does-not-exist`
- WHEN an administrator requests `GET /apps/thematiq/settings/tokensets/does-not-exist/dtcg`
- THEN the response MUST be 404 with a generic message

#### Scenario: A non-administrator cannot download
@e2e exclude Auth posture, covered by the Newman collection
- GIVEN a logged-in user who is not an administrator
- WHEN they request `GET /apps/thematiq/settings/tokensets/amsterdam/dtcg`
- THEN the response MUST be 403

### Requirement: The export holds what the set declares

The document MUST hold one token per custom property declared in `css/tokens/{id}.css`, and
nothing from `defaults.css` or from the token editor overrides. A token's path MUST be the CSS
name's first segment, then its second segment, then the rest of the name joined with dashes. Every
token MUST carry `$extensions["nl.conduction.thematiq"].cssVariable` with its CSS name. The root
MUST carry `$description` with the set name, and `$extensions["nl.conduction.thematiq"]` with the
set id and the app version.

#### Scenario: A semantic token gets a readable path and its CSS name
@e2e exclude Document shape, covered by PHPUnit on DesignTokensWriter
- GIVEN a set declaring `--nldesign-color-primary-hover: #0f3059`
- WHEN it is exported
- THEN the token MUST sit at `nldesign` > `color` > `primary-hover`
- AND its `cssVariable` extension MUST be `--nldesign-color-primary-hover`

#### Scenario: Defaults and overrides are left out
@e2e exclude Document shape, covered by PHPUnit on DesignTokensWriter
- GIVEN a set that does not declare `--nldesign-border-radius`
- AND the token editor overrides `--color-primary-element` on this instance
- WHEN the set is exported
- THEN the document MUST NOT contain a token for either name

### Requirement: Each value gets a DTCG type by its shape

The writer MUST type each value by its shape: colours as `color`, `px` and `rem` as `dimension`,
`ms` and `s` as `duration`, `cubic-bezier()` as `cubicBezier`, a font stack in a `font-family`
token as `fontFamily`, 1 to 1000 in a `font-weight` token as `fontWeight`, and a plain number as
`number`. A `var(--x)` whose target is in the set MUST become a DTCG alias to the target's path. A
value with no DTCG type MUST NOT become a token. It MUST be written to the root map
`$extensions["nl.conduction.thematiq"].cssOnly` as `{name: value}`.

#### Scenario: A duration and an easing become motion tokens
@e2e exclude Typing rules, covered by PHPUnit on DesignTokensWriter
- GIVEN a set declaring `--nldesign-animation-quick: 100ms` (as `css/tokens/vng.css:187` does) and
  `--nldesign-animation-easing: cubic-bezier(0.2, 0, 0, 1)`
- WHEN it is exported
- THEN the first MUST be `$type: "duration"` with `$value: { "value": 100, "unit": "ms" }`
- AND the second MUST be `$type: "cubicBezier"` with `$value: [0.2, 0, 0, 1]`

#### Scenario: An alias stays an alias
@e2e exclude Typing rules, covered by PHPUnit on DesignTokensWriter
- GIVEN a set declaring `--nldesign-color-link: var(--nldesign-color-primary)` and `--nldesign-color-primary: #154273`
- WHEN it is exported
- THEN the link token's `$value` MUST be `{nldesign.color.primary}`

#### Scenario: A gradient is kept outside the tokens
@e2e exclude Typing rules, covered by PHPUnit on DesignTokensWriter
- GIVEN a set declaring `--nldesign-header-background: linear-gradient(90deg, #154273, #01689b)`
- WHEN it is exported
- THEN no token MUST exist for it
- AND the root `cssOnly` map MUST hold that name with that exact value

### Requirement: Colours are written as colour objects with a colour space

A colour MUST be written as a DTCG colour object with `colorSpace`, `components`, and an sRGB `hex`
fallback. `alpha` MUST be present when it is below 1. A hex, `rgb()`, `hsl()` or named colour MUST
be written in `srgb`. A colour in `oklch()`, `oklab()`, `lab()`, `lch()`, `hwb()` or
`color(<space> ...)` MUST keep its own colour space, and its `hex` MUST be the clipped sRGB
conversion. Every colour object written MUST be accepted by the importer.

#### Scenario: A hex brand colour becomes an sRGB object
@e2e exclude Colour serialisation, covered by PHPUnit on DesignTokensWriter
- GIVEN a set declaring `--nldesign-color-primary: #154273`
- WHEN it is exported
- THEN the token's `$value` MUST be `{ "colorSpace": "srgb", "components": [0.0824, 0.2588, 0.451], "hex": "#154273" }`, components rounded to four decimals
- AND it MUST have no `alpha` key

#### Scenario: An oklch colour keeps its colour space
@e2e exclude Colour serialisation, covered by PHPUnit on DesignTokensWriter and ColorSpaceConverter
- GIVEN a set declaring `--nldesign-color-primary: oklch(0.5 0.1 250)`
- WHEN it is exported
- THEN the token's `colorSpace` MUST be `oklch` with components `[0.5, 0.1, 250]`
- AND its `hex` MUST be `#32669a`, each channel within 1 of that value

#### Scenario: A translucent colour carries its alpha
@e2e exclude Colour serialisation, covered by PHPUnit on DesignTokensWriter
- GIVEN a set declaring `--nldesign-color-primary-light: #15427380`
- WHEN it is exported
- THEN the token's `alpha` MUST be `0.502`, rounded to three decimals
- AND its `hex` MUST be `#154273`

### Requirement: A thematiq round trip is exact

Importing an exported set into thematiq under a new name MUST give every `--nldesign-*` token of
the original the same value, compared after lowercasing hex and expanding 3-digit hex. Every
`cssOnly` entry with an `--nldesign-*` name MUST come back unchanged. Brand palette and component
names MAY be renamed by the converter's naming rules.

#### Scenario: An administrator carries a set out and back in
- GIVEN an administrator downloads "Gemeente Amsterdam" as design tokens
- WHEN the administrator uploads that file as a new custom set named "Amsterdam kopie"
- THEN every `--nldesign-*` declaration of `css/tokens/custom-amsterdam-kopie.css` MUST equal the one in `css/tokens/amsterdam.css`
- AND the upload report MUST list no `--nldesign-*` token as skipped

#### Scenario: Every shipped set survives the round trip
@e2e exclude Fleet check over every shipped file, covered by PHPUnit DesignTokensRoundTripTest
- GIVEN every set in `token-sets.json`
- WHEN each is exported and imported again in the test
- THEN each `--nldesign-*` value MUST match its original
