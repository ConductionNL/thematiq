# dark-colours-from-a-file Specification

## Purpose
Dark colours from a file. Created by change `dark-colours-from-a-file` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: Dark colours from a file are kept.

## Requirements

### Requirement: Dark colours from a file are kept

An uploaded file's dark values MUST be read from the OpenProject plugin's set file `dark` block, from
the plugin's W3C extension `dark`, and from the dark token set of a light and dark Tokens Studio file.
Each MUST be validated like a light value, and a refused value MUST refuse the upload. The values
MUST be kept for the uploaded set, MUST win over the derived dark values in every generation of its
dark variant, and MUST be removed with the set.

#### Scenario: A set file with a dark block
@e2e exclude the stored dark variant is a file, not a page; proven by tests/Unit/Controller/CustomTokenSetUploadWritesTest.php (testDarkValuesFromAFileAreKept, testAForbiddenDarkValueIsRefused) and tests/Unit/Service/DarkPaletteServiceTest.php (testDarkValuesFromAFileWin)
- GIVEN the plugin's set file for cunningham
- WHEN it is uploaded
- THEN the set's dark variant MUST hold the file's dark primary

#### Scenario: Every format the plugin writes
@e2e exclude a comparison of parsed files; proven by tests/Unit/Service/FigmaTokenFilesRoundTripTest.php (testAPluginDownloadKeepsEveryRole) and tests/Unit/Service/OpenProjectSetFileTest.php (testAPluginSetFileKeepsEveryRole)
- GIVEN the plugin's W3C, Tokens Studio and legacy Tokens Studio downloads
- WHEN each is uploaded
- THEN the dark values MUST be the plugin's dark colours, each under its CSS name
