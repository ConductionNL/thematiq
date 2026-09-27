# Spec delta: environment marker (environment-marker)

A new capability. A server declares which OTAP environment it is, and every page that is not production says so.

## ADDED Requirements

### Requirement: The environment is declared per server

The app MUST read the environment from the system value `thematiq.environment` in `config.php`, with the allowed values `development`, `test`, `acceptance` and `production`. The app MUST NOT store the environment in app config or in any database table, so that a database copied from one environment to another never carries the source environment's label.

#### Scenario: A restored production database does not label test as production

- GIVEN a test server with `thematiq.environment` set to `test` in its `config.php`
- AND a database restored from production
- WHEN a user opens the Files app on the test server
- THEN the page MUST show the "Test environment" label

#### Scenario: An unset value shows nothing

- GIVEN a server with no `thematiq.environment` in `config.php`
- WHEN a user opens any page
- THEN the page MUST NOT show an environment stripe or label

### Requirement: Every non-production page is marked

When the environment is `development`, `test` or `acceptance`, every page Nextcloud renders MUST show a stripe above the header with a text label naming the environment, and the page title MUST start with the short label. This MUST hold on the login page, on public share pages, on pages of apps excluded from theming, and when the design system is `none`. When the environment is `production` the app MUST NOT render a marker.

#### Scenario: A user on acceptance sees the label on the login page

- GIVEN a server with `thematiq.environment` set to `acceptance`
- WHEN a user opens the login page
- THEN the page MUST show the text "Acceptance environment" above the login form
- AND the browser tab title MUST start with "[Acceptance]"

#### Scenario: An app excluded from theming still shows the marker

- GIVEN a server with `thematiq.environment` set to `test`
- AND the Calendar app excluded from theming in Settings > Administration > Theming
- WHEN a user opens the Calendar app
- THEN the page MUST show the "Test environment" label
- AND the Calendar page MUST otherwise render unthemed as before

#### Scenario: Production shows nothing

- GIVEN a server with `thematiq.environment` set to `production`
- WHEN a user opens the dashboard
- THEN the page MUST NOT contain the environment stripe
- AND the browser tab title MUST NOT carry an environment prefix

### Requirement: An unknown value is shown, not hidden

A value outside the allowed list MUST render the label "Unknown environment" with the non-production styling, and the app MUST log a warning naming the value. A typo MUST NOT make a server look like production.

#### Scenario: A typo in config.php stays visible

- GIVEN a server with `thematiq.environment` set to `tset`
- WHEN a user opens the dashboard
- THEN the page MUST show the label "Unknown environment"
- AND the Nextcloud log MUST contain a warning naming the value `tset`

### Requirement: The marker is accessible and independent of the house style

The label MUST be text inside an element with `role="note"`, placed before the header in the page order. Each environment MUST use one fixed stripe colour that does not come from the active token set, and the label text MUST reach a contrast of 4.5:1 against the stripe in light and in dark mode.

#### Scenario: A screen reader user hears the environment first

- GIVEN a server with `thematiq.environment` set to `test`
- WHEN a screen reader user opens the Files app
- THEN the first note in the page order MUST read "Test environment"

#### Scenario: A brand colour cannot hide the stripe

- GIVEN the active token set's primary colour is the same colour as the test stripe
- WHEN a user opens the dashboard on a test server
- THEN the stripe MUST keep its fixed colour and label
- AND the label MUST still reach 4.5:1 against the stripe

### Requirement: The settings page shows the environment

Settings > Administration > Theming MUST show the environment the server declares, or state that none is set together with the `occ config:system:set thematiq.environment --value=<environment>` command. The page MUST NOT offer a control that writes the value.

#### Scenario: An administrator sees which environment the server is

- GIVEN a server with `thematiq.environment` set to `acceptance`
- WHEN an administrator opens Settings > Administration > Theming
- THEN the theming section MUST show "Environment: acceptance"

#### Scenario: An administrator learns how to set it

- GIVEN a server with no `thematiq.environment` value
- WHEN an administrator opens Settings > Administration > Theming
- THEN the theming section MUST state that no environment is set
- AND it MUST show the occ command that sets it
