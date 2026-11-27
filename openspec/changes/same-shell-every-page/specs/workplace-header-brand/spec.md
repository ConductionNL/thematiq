# workplace-header-brand

## MODIFIED Requirements

### Requirement: Every app gets the brand block in the workplace bar (REQ-TQWH-001)
While the header style resolves to `workplace`, every page that draws no brand of its own MUST show the
brand block at the start of the top bar, as the DqKop board draws it: a 237 px link with the set's
emblem (the capability `nldesign.logos.emblem`), the organisation (Nextcloud's theming name) over the
page's name, then a thin divider in the border colour. The page's name MUST be the open app's
(Nextcloud's own navigation entry), else the active settings section's (Nextcloud's settings navigation
entry, such as "Personal settings"), as Nextcloud translates it. Without either, the block MUST show
the emblem and the organisation alone and MUST NOT draw an empty name. The page MUST load
`js/header-brand.js` only with the workplace header. The script MUST write text only and MUST mark the
bar `data-thematiq-brand` while the block is in it.

#### Scenario: Files gets the brand block
@e2e exclude Unit: vitest tests/vitest/headerBrand.spec.js draws the block from the initial state and the capabilities
- GIVEN the Zuiddrecht set with the workplace header
- WHEN a Files page renders
- THEN the bar MUST start with the Zuiddrecht emblem, "Gemeente Zuiddrecht" over "Files" and a divider
- AND the bar MUST carry `data-thematiq-brand`

#### Scenario: Settings names its section
- GIVEN the Zuiddrecht set with the workplace header
- WHEN the personal settings page renders
- THEN the bar MUST start with "Gemeente Zuiddrecht" over the name of the personal settings
- AND the bar MUST carry `data-thematiq-brand`

#### Scenario: No name to show
@e2e exclude Unit: vitest tests/vitest/headerBrand.spec.js with neither an active app nor an active settings entry
- GIVEN a page with neither an open app nor an active settings section
- WHEN the script runs
- THEN the block MUST show the emblem and the organisation and no name element

#### Scenario: Another header style loads nothing
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/HeaderUserServiceTest.php
- GIVEN the default header style
- WHEN a page renders
- THEN `js/header-brand.js` MUST NOT be loaded

## REMOVED Requirements

### Requirement: Mail, Calendar and Deck fall back to Nextcloud's logo and name (REQ-TQWH-004)
**Reason**: Ruben decided on 27 November 2026 that no thematiq page differs from another. The fallback
held for three apps only, because only their pages loaded `css/workplace-apps/brand.css`.
**Migration**: replaced by REQ-TQWH-005 below; the rules move into `css/header-workplace.css`, which
every page with the workplace header loads, and `css/workplace-apps/brand.css` goes.

## ADDED Requirements

### Requirement: Every page falls back to Nextcloud's logo and name (REQ-TQWH-005)
With the workplace header, `css/header-workplace.css` MUST show Nextcloud's logo and the current app's
name in bold, a hairline, then the apps grid, as MlHeader, ClWeek, TbBoard and DqKop draw the bar's
start, on every page until the brand block is drawn. The rules MUST step back while the bar carries
`data-thematiq-brand`, while it holds an app's own brand (`#header > .cn-brand-bar-host`), and while the
page's navigation carries one (`.cn-app-nav__brand`); a browser without `:has()` MUST keep the bar's
start hidden, as before this change, and MUST NOT show two brands. The rules MUST write no colour
literal.

#### Scenario: Files before the script runs
@e2e exclude Static file check: tests/vitest/headerWorkplace.spec.js reads the rules; the first paint is shorter than an e2e step can hold
- GIVEN a Files page with the workplace header before `js/header-brand.js` ran
- WHEN the bar renders
- THEN it MUST show the logo and "Files" in bold before the apps grid

#### Scenario: dossiq keeps one brand
@e2e exclude Static file check: tests/vitest/headerWorkplace.spec.js reads the step-back conditions; compdiff dossiq/DqZaken
- GIVEN a dossiq page whose bar holds `.cn-brand-bar-host`
- WHEN the bar renders
- THEN Nextcloud's logo and current-app name MUST stay hidden
