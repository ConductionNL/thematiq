# Spec: Workplace layout

## ADDED Requirements

### Requirement: A token set may group the apps menu
A token set's manifest entry MAY carry an `app_menu` block with `groups`, `others_title`, `hint`
and `manage_label`. Each group MUST have a `title` and at least one app; an app is an id of lower
case letters, digits and underscores, with an optional `description`; a group MAY carry a `link`
`{label, url}`, shown only with an http(s) address or a path on this server. Texts MUST follow the
login copy's rules (one string or a map per language). At most four groups of 24 apps MUST be
kept. An administrator MAY store the same shape in the app config key `app_menu_config`; its keys
MUST win over the set's, key by key.

#### Scenario: Zuiddrecht ships the board's section
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuServiceTest.php reads the shipped manifest
- GIVEN the token set `zuiddrecht` and a Dutch request
- WHEN the menu is resolved
- THEN the first group MUST be "Werk" with eight apps, dossiq described as "Zaken en Woo"
- AND its link MUST be "Mijn werkdag in launchpad" to `/apps/launchpad/`

#### Scenario: What may not be shown is dropped
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuServiceTest.php
- GIVEN an override with an app id `Bad-Id`, a group without a title, an empty group and a `javascript:` link
- WHEN the menu is resolved
- THEN none of those MUST be returned

### Requirement: The workplace header draws the apps menu of the board
While the header style resolves to `workplace`, the open apps menu MUST be 640px wide (never wider
than the window), MUST NOT scroll inside its grid, MUST NOT underline or break an app's name
mid-word, and MUST draw each icon in a chip. The page MUST carry the initial state
`thematiq/app-menu` and MUST load `js/app-menu.js`; without the workplace header neither MUST be
emitted. The script MUST write text only and MUST NOT move the tiles in the DOM. It MUST place
each group's heading (with its link) before the group's apps in the group's order, give those apps
their description (hidden from assistive technology), then the other apps under `others_title`,
then a footer with the hint and, when Nextcloud shows a "more apps" tile, `manage_label` linking to
that tile's address, which then hides. Without groups no heading MUST be drawn.

#### Scenario: Zuiddrecht's apps menu
@e2e exclude Live-checked on :8080 against the AppMenu board with screens-diff.mjs --click; unit: vitest tests/vitest/appMenu.spec.js
- GIVEN the token set `zuiddrecht` and a signed-in administrator
- WHEN the grid button opens the apps menu
- THEN "WERK" and "Mijn werkdag in launchpad" MUST head the eight apps with their descriptions
- AND the other apps MUST follow under "NEXTCLOUD", then the hint and "Apps beheren"

#### Scenario: The layer asks with the page's set
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php and AppMenuServiceTest.php
- GIVEN a page without the workplace header style
- WHEN it renders
- THEN no apps menu state and no script MUST be emitted
