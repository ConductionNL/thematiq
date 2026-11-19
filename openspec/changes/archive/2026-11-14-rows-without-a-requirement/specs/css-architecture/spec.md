# Spec delta: CSS architecture (rows-without-a-requirement)

The capability rows `int-tokens-for-other-apps` and `int-no-js-overhead` name requirements this spec
did not state.

## ADDED Requirements

### Requirement: The theme reaches every page as plain CSS variables

The active theme MUST reach every themed page as stylesheets and style elements that set CSS custom
properties on `:root` and Nextcloud's own variables. Another app MUST be able to style itself with
those variables with no build step, no import and no script of this app.

#### Scenario: Another app uses the brand colour
@e2e exclude Stylesheet contract; covered by PHPUnit (CssInjectionServiceTest)
- GIVEN the `rijkshuisstijl` set is active
- WHEN another app's page sets `color: var(--color-primary-element)`
- THEN that text MUST be drawn in the set's primary colour

### Requirement: The theme adds no script to an ordinary page

With a token set active and no opt-in page feature on, the theme MUST add stylesheets and style
headers only, and no script. Opt-in page features that need a script (the workplace header, the apps
menu and the others specified in `workplace-layout` and `environment-marker`) are outside this
requirement.

#### Scenario: An ordinary page under a theme
@e2e exclude Injection contract; covered by PHPUnit (CssInjectionServiceTest::testAnOrdinaryPageGetsStylesheetsAndHeadersOnly)
- GIVEN the `rijkshuisstijl` set is active and no opt-in page feature is on
- WHEN a user opens Files
- THEN the theme MUST have added stylesheets and style headers only
- AND it MUST NOT have added a script
