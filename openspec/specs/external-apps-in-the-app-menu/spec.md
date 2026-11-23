# external-apps-in-the-app-menu Specification

## Purpose
Apps outside Nextcloud, such as OpenProject, XWiki or Element, listed in the workplace apps menu as links that open in a new tab. Only an address the server accepts is listed, and an app without one is left out.

## Requirements

### Requirement: The apps menu lists the external apps from Menu settings
The external apps MUST be set in Menu settings (apps-menu-settings), not on the thematiq admin page: an
administrator's `menu.externalApps` for everyone, then a person's own. Each MUST pass the server's
check of an external app (an `https` address, or plain `http` on the loopback address only) before it
is stored. In the workplace apps menu, every visible external app MUST be listed after the Nextcloud
apps and take its place in the person's order, with its title and, while `payoff` is on, its payoff as
the second line. With `showExternalApps` off, or without any external app, none MUST be listed. Each
link MUST open in a new tab with `rel="noopener noreferrer"` and MUST say so to assistive technology.
The addresses an administrator gave on the admin page before Menu settings existed MUST move into
`menu.externalApps` once, keeping their menu keys.

#### Scenario: An administrator adds two external apps
@e2e exclude Unit: vitest tests/vitest/menuSettingsDialog.spec.js and tests/vitest/appMenuSettings.spec.js; server check in tests/Unit/Service/AppMenuSettingsTest.php
- GIVEN an administrator adds OpenProject at `https://op.example.org` and Forgejo at `https://code.example.org` for everyone
- WHEN a person opens the apps menu with More apps unfolded
- THEN both MUST be listed after the Nextcloud apps
- AND Forgejo MUST link to `https://code.example.org` in a new tab

#### Scenario: An address that is not one
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuSettingsTest.php (testAnExternalAppIsChecked)
- WHEN an administrator saves an external app with `javascript:alert(1)` as its address
- THEN the answer MUST be 400 and nothing MUST be stored

#### Scenario: The admin page's addresses move
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuLegacyExternalAppsTest.php (testTheAdminPageAppsMoveOnceAndKeepTheirKeys)
- GIVEN `openproject_url` was set on the admin page
- WHEN the menu is first drawn
- THEN OpenProject's modules MUST be in `menu.externalApps` with their old keys
