# css-architecture Specification (delta)

## ADDED Requirements

### Requirement: The theme reaches every page as plain CSS variables

On every page render, including the login page, the app SHALL add the active set's `--nldesign-*` custom properties as plain stylesheets and style blocks. Any other app SHALL be able to read them with `var(--nldesign-*)` in its own CSS, with no build step and no script dependency.

#### Scenario: Another app reads a token
- GIVEN a token set with primary colour `#154273` is active
- AND another app's stylesheet declares `color: var(--nldesign-color-primary)`
- WHEN a user opens that app's page
- THEN the element renders in `#154273`

### Requirement: The theme adds no script to an ordinary page

Applying a token set SHALL add stylesheets and style blocks only. On an ordinary page, with none of the opt-in page features on (workplace header, apps menu, search wording, login or not-found copy, environment marker), the app SHALL add no script. Its admin scripts SHALL load on the Theming settings page only.

#### Scenario: A user opens the Files app
- GIVEN a token set is active and no opt-in page feature is on
- WHEN a user opens the Files app
- THEN the page carries the app's stylesheets
- AND it carries no script from this app
