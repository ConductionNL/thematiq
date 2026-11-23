# workplace-header-brand

## ADDED Requirements

### Requirement: Every app gets the brand block in the workplace bar (REQ-TQWH-001)
While the header style resolves to `workplace`, a page of an app that draws no brand of its own MUST
show the brand block at the start of the top bar, as the DqKop board draws it: a 237 px link to the
open app with the set's emblem (the capability `nldesign.logos.emblem`), the organisation (Nextcloud's
theming name) over the app's name (Nextcloud's own navigation entry for the open app), then a thin
divider in the border colour. The page MUST load `js/header-brand.js` only with the workplace header.
The script MUST write text only and MUST mark the bar `data-thematiq-brand` while the block is in it.
Without an open app or without a name to show, nothing MUST be drawn.

#### Scenario: Files gets the brand block
@e2e exclude Unit: vitest tests/vitest/headerBrand.spec.js draws the block from the initial state and the capabilities
- GIVEN the Zuiddrecht set with the workplace header
- WHEN a Files page renders
- THEN the bar MUST start with the Zuiddrecht emblem, "Gemeente Zuiddrecht" over "Files" and a divider
- AND the bar MUST carry `data-thematiq-brand`

#### Scenario: Another header style loads nothing
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/HeaderUserServiceTest.php
- GIVEN the default header style
- WHEN a page renders
- THEN `js/header-brand.js` MUST NOT be loaded

### Requirement: An app's own brand wins (REQ-TQWH-002)
When the bar holds a Conduction app's own brand (`#header > .cn-brand-bar-host`) or the app's
navigation carries it (`.cn-app-nav__brand`), the brand block MUST NOT be drawn, and a block already
drawn MUST be taken out when either appears later. The script MUST sync the block again only when a
change touches the bar or an app's brand, not on every change to the page.

#### Scenario: dossiq draws its own
@e2e exclude Unit: vitest tests/vitest/headerBrand.spec.js
- GIVEN a dossiq page whose bar holds `.cn-brand-bar-host`
- WHEN the script runs
- THEN no `thematiq-brand-host` MUST be in the bar

#### Scenario: Unrelated changes do not resync
@e2e exclude Unit: vitest tests/vitest/headerBrand.spec.js counts the syncs
- GIVEN a page with the brand block drawn
- WHEN the app adds rows to its own content
- THEN the block MUST NOT be built again

### Requirement: The block waits for its stylesheet (REQ-TQWH-003)
The script MUST draw the block only while `css/header-workplace.css` styles it (the sheet sets
`--thematiq-brand-block: 1` on the bar), so a browser that holds an older cached sheet keeps the bar it
had instead of an unstyled emblem.

#### Scenario: An old sheet in the cache
@e2e exclude Unit: vitest tests/vitest/headerBrand.spec.js
- GIVEN the bar without `--thematiq-brand-block: 1`
- WHEN the script runs
- THEN no block MUST be drawn

### Requirement: Mail, Calendar and Deck fall back to Nextcloud's logo and name (REQ-TQWH-004)
With the workplace header and the light layout, a page of Mail, Calendar or Deck MUST load
`css/workplace-apps/brand.css`, which shows Nextcloud's logo and the app's name in bold, a hairline,
then the apps grid, as MlHeader, ClWeek and TbBoard draw the bar. While the bar carries
`data-thematiq-brand` these rules MUST step back. The sheet MUST write no colour literal.

#### Scenario: Before the script runs
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js reads the sheet; link order in tests/Unit/Service/CssInjectionServiceTest.php
- GIVEN a Deck page with the workplace header before `js/header-brand.js` ran
- WHEN the bar renders
- THEN it MUST show the logo and "Deck" in bold before the apps grid
