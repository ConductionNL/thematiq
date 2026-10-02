# nextcloud-variable-inventory Specification

## Purpose
Record every CSS custom property that Nextcloud and the shared Conduction library declare or read, so thematiq's coverage of them is a checked fact rather than a claim.

@e2e exclude Build-time inventory and guard. Every scenario is about a generated JSON file, an extractor's output and a unit test's verdict; nothing here renders in a browser.

## Requirements

### Requirement: The inventory lists every custom property of a Nextcloud release

The repository MUST contain a generated inventory that lists every custom property a supported Nextcloud release declares or reads in its shipped core and bundled apps.

#### Scenario: A property declared by the theming app is listed
- GIVEN Nextcloud's theming app declares `--color-mark` in its theme stylesheet
- WHEN the inventory is generated from that release
- THEN the inventory MUST contain an entry for `--color-mark`
- AND the entry's class MUST be `theme`

#### Scenario: A property only read by a component is listed
- GIVEN the media player reads `var(--plyr-audio-control-background-hover, ...)` and no shipped file declares it
- WHEN the inventory is generated
- THEN the inventory MUST contain `--plyr-audio-control-background-hover` with class `slot`

#### Scenario: The inventory names its source
- GIVEN the inventory is generated
- WHEN a reader opens the file
- THEN it MUST state the Nextcloud version it was read from
- AND it MUST state the `@conduction/nextcloud-vue` version it was read from

### Requirement: The inventory lists every Conduction library property

The inventory MUST list every `--cn-*` custom property that `@conduction/nextcloud-vue` declares or reads.

#### Scenario: A KPI tile property is listed
- GIVEN the shared library reads `--cn-kpi-accent`
- WHEN the inventory is generated
- THEN the inventory MUST contain `--cn-kpi-accent` with class `conduction`

### Requirement: Every inventory entry carries a class

Each entry MUST carry exactly one class from `theme`, `component`, `slot`, `icon`, `runtime`, `unread` or `conduction`.

#### Scenario: An icon image is classed as icon
- GIVEN Nextcloud declares `--icon-download-dark: url(...)`
- WHEN the inventory is generated
- THEN the entry MUST have class `icon`

#### Scenario: A property written by JavaScript is classed as runtime
- GIVEN the Files app sets `--systemtag-color` from JavaScript with `setProperty`
- WHEN the inventory is generated
- THEN the entry MUST have class `runtime`

### Requirement: Every inventory entry records its stock value per theme

Each `theme` entry MUST record the value Nextcloud gives it under the default, dark, high-contrast and dark high-contrast themes.

#### Scenario: A value that differs per theme is recorded per theme
- GIVEN Nextcloud gives `--color-main-text` one value in light and another in dark
- WHEN the inventory is generated
- THEN the entry MUST hold both values, keyed by theme

### Requirement: Every inventory entry records what thematiq does with it

Each entry MUST carry a status of `mapped`, `settable` or `excluded`, and an excluded entry MUST carry a reason.

#### Scenario: An excluded entry without a reason fails the guard
- GIVEN an inventory entry has status `excluded` and no reason
- WHEN `npm run test:inventory` runs
- THEN it MUST fail and MUST name the entry

#### Scenario: An entry without a status fails the guard
- GIVEN Nextcloud adds a property the inventory has not classified
- WHEN the inventory is regenerated and `npm run test:inventory` runs
- THEN it MUST fail and MUST name the new property

### Requirement: The guard fails when coverage goes down

The guard MUST fail when the number of `mapped` or `settable` entries is lower than the committed baseline and the change does not record why.

#### Scenario: A removed mapping is caught
- GIVEN the baseline records 66 mapped theme entries
- WHEN a change leaves 65 and records no removal
- THEN `npm run test:inventory` MUST fail
- AND the message MUST name the entry that lost its status

#### Scenario: thematiq maps a name the inventory does not know
- GIVEN a stylesheet in thematiq assigns `--color-primary-element-ligth`
- WHEN `npm run test:inventory` runs
- THEN it MUST fail and MUST name the unknown property

### Requirement: The committed inventory matches its extractor

The committed inventory MUST be exactly what the extractor produces for the recorded versions.

#### Scenario: A hand edit is caught
- GIVEN someone edits `nextcloud-variables.json` by hand
- WHEN the drift check runs with the recorded versions
- THEN it MUST fail and MUST report the first differing entry
