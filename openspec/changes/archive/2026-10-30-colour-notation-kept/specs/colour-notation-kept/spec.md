# Spec delta: colour notation kept (colour-notation-kept)

## ADDED Requirements

### Requirement: A colour keeps the text it was written in

A download MUST carry a colour's original text in the thematiq extension when it is not its hex. An
upload MUST use thematiq's or the OpenProject plugin's copy of that text instead of the colour object
when the text is an sRGB notation (a name, hex, `rgb()`, `hsl()` or `hwb()`) that parses to the same
colour as the object, and MUST use the object otherwise.

#### Scenario: Downloaded and uploaded again
@e2e exclude a comparison of two parsed documents with no page; proven by tests/Unit/Service/ColourNotationTest.php (testEveryNotationComesBack, testThePluginsTextIsReadToo)
- GIVEN a set with `--nldesign-color-a: rgb(54, 105, 165)`
- WHEN it is downloaded and uploaded again
- THEN the value MUST be `rgb(54, 105, 165)`

#### Scenario: Changed in Figma
@e2e exclude the same; proven by tests/Unit/Service/ColourNotationTest.php (testAChangedColourIsNotOverwrittenByItsOldText)
- GIVEN that file with the colour object changed to `#000000` and the old text left in place
- WHEN it is uploaded
- THEN the value MUST be `#000000`
