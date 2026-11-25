# css-architecture

## ADDED Requirements

### Requirement: Static layer sheets carry the version and the theming cachebuster (REQ-TQCA-001)
Every static stylesheet `CssInjectionService` links from `css/` (the layout, header, app layer, brand
stripe and navigation sheets) MUST carry `?v=<installed_version>-<theming cachebuster>`, the app config
values `thematiq` / `installed_version` and `theming` / `cachebuster`, each `0` when absent. The page
links and the stylesheet manifest of apply-without-reload MUST carry the same value.

#### Scenario: A deploy raises only the cachebuster
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php pins `9.9.9-42`
- GIVEN `installed_version` 9.9.9 and the theming cachebuster 42
- WHEN the light layout's sheets are linked
- THEN `header-workplace.css` MUST be linked with `?v=9.9.9-42`

### Requirement: The word separator dot draws in the bundled Fira Sans (REQ-TQCA-002)
The bundled font stylesheets MUST give every Fira Sans face a sibling face with the same family, weight
and style, limited to `unicode-range: U+2E31` and served from `fira-sans-separator.otf`, which holds
Fira's own middle dot under that code point (SIL OFL 1.1, as Fira). `scripts/build-fonts.js` MUST
generate these faces, and its check mode MUST report no drift.

#### Scenario: Deck's card panel
@e2e exclude Static file check: vitest tests/vitest/separatorGlyph.spec.js reads the font stylesheets and the font file
- GIVEN Deck's card panel writes "Modified: 2 days ago ⸱ Created: 5 days ago"
- WHEN it renders in Fira Sans regular or bold
- THEN the separator MUST draw as a dot, not as an empty box

### Requirement: A launchpad app tile keeps its own colours (REQ-TQCA-003)
An app tile (`a.cn-app-tile`) is a link drawn as a button. The link rules of the nldesign system and
of the high-contrast system MUST leave it out wherever they leave out the other button-like links: no
link colour in any state and no underline.

#### Scenario: A blue tile on LpVandaag
@e2e exclude Static file check: vitest tests/vitest/anchorButtonText.spec.js (app tile) and highContrastDark.spec.js
- GIVEN a filled app tile on launchpad's today page under the Zuiddrecht set
- WHEN it renders, at rest and on hover
- THEN its label MUST keep the tile's own text colour and MUST NOT be underlined

### Requirement: Only a data table greys its rows on hover (REQ-TQCA-004)
The nldesign row hover MUST apply only to a data table: a table with a head row, without nested tables
and without a presentation role. The table part of the selector MUST sit in `:where()`, so the rule
keeps about the specificity it had.

#### Scenario: Calendar's week grid
@e2e exclude Static file check: vitest tests/vitest/tableRowHover.spec.js; counted live on :8076 (Files 60 cells, dossiq 35, Calendar 0 of 115)
- GIVEN Calendar's week view, a table whose one row holds the whole week
- WHEN the pointer rests on the grid
- THEN no cell MUST be greyed by the row hover
- AND a row of the Files list MUST still grey on hover
