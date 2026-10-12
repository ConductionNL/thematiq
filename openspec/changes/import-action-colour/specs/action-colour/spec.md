# Spec delta: action colour (import-action-colour)

## ADDED Requirements

### Requirement: An imported theme may bring an action colour

The converter MUST write `--nldesign-color-action` when a theme declares `--<prefix>-color-action`,
`--nldesign-color-action-hover` when it declares `--<prefix>-color-action-hover`, and
`--nldesign-color-action-text` when it declares `--<prefix>-color-action-text`. A design tokens
(DTCG) document MUST bring them by the paths `color.action`, `color.action-hover` and
`color.action-text` (or `color.on-action`). A theme that names an action colour and no text MUST get
white or black as its action text, by the rule that gives the text on the primary. A theme that
names no action colour MUST get none of the three tokens: the Utrecht primary-action button colour
MUST stay the source of the primary and MUST NOT become an action colour.

#### Scenario: A theme with a green main button
@e2e exclude Converter rule, covered by PHPUnit (TokenSetConverterServiceTest)
- GIVEN a theme that declares `--demo-color-primary: #1a67a3` and `--demo-color-action: #116329`
- WHEN it is converted
- THEN the set MUST declare `--nldesign-color-action: #116329`
- AND `--nldesign-color-action-text` MUST be `#ffffff`
- AND `--nldesign-color-primary` MUST stay `#1a67a3`

#### Scenario: A theme without an action colour
@e2e exclude Converter rule, covered by PHPUnit (TokenSetConverterServiceTest) and the converter fixtures
- GIVEN a theme that declares only the Utrecht primary-action button colours
- WHEN it is converted
- THEN the set MUST NOT declare an action token

#### Scenario: A design tokens file
@e2e exclude Mapper rule, covered by PHPUnit (DesignTokensMapperTest, TokenSetConverterServiceTest)
- GIVEN a DTCG document with `color.action` and `color.on-action`
- WHEN it is imported
- THEN the set MUST declare `--nldesign-color-action` and `--nldesign-color-action-text`
