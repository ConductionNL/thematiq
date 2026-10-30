# figma-token-files Specification

## Purpose
Token sets go to Figma and come back. Created by change `figma-token-files` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: Any token set can be downloaded for Figma; A file from Figma becomes a custom token set; A round trip keeps every token the format carries; A file from the OpenProject plugin uploads into thematiq.

## Requirements

### Requirement: Any token set can be downloaded for Figma

The download of a token set MUST take a `format` of `dtcg`, `figma`, `tokens-studio` or
`tokens-studio-legacy`, MUST give the W3C document without one, and MUST refuse any other with 400.
`figma` MUST be the W3C document with every colour as `#rrggbb` or `rgba()` text. `tokens-studio`
MUST be a Tokens Studio single file: the set under its id, one theme that has it `enabled`, and
`$metadata.tokenSetOrder`. `tokens-studio-legacy` MUST be that file with `value` and `type`. A
token MUST have the same path in all four.

#### Scenario: An administrator downloads a set for Tokens Studio
@e2e exclude the file's contents are what matters and a browser only adds a save dialog; proven by tests/Unit/Controller/DtcgExportControllerTest.php (testTheTokensStudioFormatsWrapTheSet, testTheFigmaFormatWritesColoursAsText)
- GIVEN the shipped set `amsterdam`
- WHEN the download is asked with `format=tokens-studio`
- THEN the file MUST hold `amsterdam.nldesign.color.primary` with a text `$value` and `$type` `color`
- AND the file name MUST be `amsterdam.tokens-studio.json`

#### Scenario: An unknown format
@e2e exclude a status code on an address no page links to; proven by tests/Unit/Controller/DtcgExportControllerTest.php (testAnUnknownFormatIsRefused)
- WHEN the download is asked with `format=sketch`
- THEN the answer MUST be 400

### Requirement: A file from Figma becomes a custom token set

An upload with `$themes` or `$metadata` MUST have its token sets merged in `tokenSetOrder`, later
sets winning, before it is mapped; with exactly one theme, a set that theme has not switched on
MUST be left out. The `value` and `type` spelling MUST be read as `$value` and `$type`. In a file
whose tokens sit under `nldesign`, a token's CSS name MUST be its path joined by dashes. A token in
a reference cycle, a value that is a sum over references and a switched off token set MUST be left
out and named in the upload result with a reason. Every value MUST still pass the token set
validator. Any other upload MUST reach the converter unchanged.

#### Scenario: The same file in both spellings
@e2e exclude a comparison of two parsed files, with no page of its own; proven by tests/Unit/Service/FigmaTokenFilesRoundTripTest.php (testBothSpellingsGiveTheSameSet, testWhatIsLeftOutIsReported)
- GIVEN a Tokens Studio file with three token sets, one switched off, and a reference cycle
- WHEN it is uploaded in the `$value` spelling and in the `value` spelling
- THEN both MUST give the same token set
- AND the result MUST name the switched off set and both tokens of the cycle

#### Scenario: A file that is not from Figma
@e2e exclude byte equality of a string handed between two classes; proven by tests/Unit/Service/FigmaTokenReaderTest.php (testOtherContentIsNotTouched)
- GIVEN a CSS token set, a W3C document and a thematiq download with its extensions
- WHEN each is uploaded
- THEN the converter MUST receive it byte for byte

### Requirement: A round trip keeps every token the format carries

A shipped set that is downloaded in a Figma format and uploaded again MUST give every
`--nldesign-*` token the same value, except a token whose CSS name is also the start of another
name in a Tokens Studio file.

#### Scenario: Every shipped set, every format
@e2e exclude 186 file comparisons with no page; proven by tests/Unit/Service/FigmaTokenFilesRoundTripTest.php (testShippedSetSurvivesTheRoundTrip, testColoursSurviveAToolThatDropsTheExtensions)
- GIVEN each shipped set in the `nldesign` vocabulary
- WHEN it is downloaded as `figma`, `tokens-studio` and `tokens-studio-legacy` and uploaded again
- THEN every token the format carries MUST have the value it had

### Requirement: A file from the OpenProject plugin uploads into thematiq

A file the OpenProject plugin downloads, as W3C design tokens or as a Tokens Studio single file in
either spelling, MUST upload into thematiq with every shell role both products know at the plugin's
value. `--thematiq-shell-text` and `--thematiq-shell-menu` MUST keep their names, and the `text`
role MUST read `--thematiq-shell-text` before it mixes ink and muted. A Tokens Studio file with a
light and a dark theme MUST be read as one house style: the token sets only the dark theme switches
on MUST be left out and named, and the file MUST NOT be offered as two brands.

#### Scenario: Real plugin files
@e2e exclude a comparison of parsed files with no page; proven by tests/Unit/Service/FigmaTokenFilesRoundTripTest.php (testAPluginDownloadKeepsEveryRole) and tests/Unit/Service/FigmaTokenReaderTest.php (testALightAndDarkFileIsOneHouseStyle)
- GIVEN the three files the plugin downloads for `cunningham` and for `zuiddrecht`
- WHEN each is uploaded
- THEN each of the eighteen roles MUST have the value the plugin exported
- AND the dark token set MUST be named as left out
