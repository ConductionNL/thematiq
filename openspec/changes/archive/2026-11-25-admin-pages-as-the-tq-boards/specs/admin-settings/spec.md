# admin-settings

## ADDED Requirements

### Requirement: The admin page is drawn as TqTheming (REQ-ASET-101)
The Theming administration page MUST show, in this order, the page header, the Theme card, the
thematiq heading with the environment chip, the Active token set card, the Token sets table and the
Layout, Website: lettering and Dark variant cards, with the structure, labels, spacing, sizes,
colours and radii of the TqTheming board. Only demo data (names, counts, dates) MAY differ.

#### Scenario: The page against its board
@e2e exclude Measured with compdiff, the layout check of the screens repo
- GIVEN an administrator on `/settings/admin/theming`
- WHEN compdiff measures the page against the TqTheming board
- THEN every board component MUST pair with a live component
- AND no paired component MUST carry a flag

### Requirement: Each confirmation is the dialog its board draws (REQ-ASET-102)
Applying a set, updating the Nextcloud theme, going back to Nextcloud, saving as a new theme, saving
or uploading overrides, editing base tokens, choosing brands on import, the result of a set upload,
adding or deprecating a token and restoring a version MUST each open a modal dialog drawn as its
board (TqTokensetToepassen, TqThemingBijwerken, TqTerugNaarNextcloud, TqNieuwThema,
TqOverridesOpslaan, TqOverridesUploaden, TqBasistokens, TqMerkenKiezen, TqSetUploaden,
TqTokenToevoegen, TqTokenUitfaseren, TqVersieHerstellen). Each dialog MUST be a file of its own,
close on Escape and give focus back to the control that opened it.

#### Scenario: Applying another set
@e2e exclude Measured with compdiff against TqTokensetToepassen; the e2e test is task 4.2
- GIVEN an administrator on the Theming page
- WHEN they choose another set and apply it
- THEN the dialog of TqTokensetToepassen MUST open
- AND compdiff MUST pair every component of that board with no flag

### Requirement: Each tool is the page or panel its board draws (REQ-ASET-103)
The tools that the page now lists one under the other MUST each be reachable from the Theming page
and drawn as their board: TqTokenSet, TqComponenten, TqEigenComponent, TqBeweging, TqLayout,
TqDonker, TqEigenSets, TqEigenTokens, TqMerkPerApp, TqGroepen, TqLettertypen, TqEmailDocumenten,
TqEigenCss, TqWissels, TqGalerij, TqBundel, TqContrastrapport, TqLogboek, TqAiAssistent,
TqOmgevingsmarkering and TqVoorbeeldSessie. A board element whose feature thematiq does not have
MUST be left out and named in the task of its board.

#### Scenario: The token set page
@e2e exclude Measured with compdiff against TqTokenSet
- GIVEN an administrator on the Theming page
- WHEN they open the Zuiddrecht set
- THEN the page MUST be drawn as TqTokenSet
- AND compdiff MUST pair every component with no flag

## MODIFIED Requirements

### Requirement: Settings Hint Text
The settings panel MUST include instructional text explaining the purpose of the controls.

#### Scenario: Settings hint rendered
- GIVEN the settings panel is loaded
- WHEN the heading row renders
- THEN the row MUST hold the "Thematiq" title, the environment chip when the server declares an environment, and the line "Choose a token set from a Dutch government body as a base, or adjust single tokens." (localized), as TqTheming draws it
- AND without a declared environment the occ command that declares one MUST show on a line under the row
- AND the row MUST appear before the token set selector

#### Scenario: Hint text is localized
@e2e exclude Requires Dutch-language Nextcloud session — test environment uses English locale.
- GIVEN the admin has their Nextcloud language set to Dutch
- WHEN the settings panel loads
- THEN the line MUST be displayed in Dutch via the `$l->t()` localization function

#### Scenario: Hint text provides sufficient context
@e2e exclude Subjective copy-quality assertion — not verifiable via automated DOM test; hint text presence is covered by admin-settings spec-coverage.
- GIVEN a first-time admin user opens the settings panel
- WHEN they read the line
- THEN they MUST understand that they can either choose a token set as a base OR adjust single tokens
