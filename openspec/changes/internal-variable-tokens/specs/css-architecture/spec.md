## ADDED Requirements

### Requirement: The internal layers sit after the component scopes
The app MUST inject `css/internal-bridge.css` directly after `css/component-scopes.css`, and the active set's generated internal scopes file directly after that, in every themed render context.

@e2e exclude Stylesheet order is a server-side injection fact; the browser-visible effect is tested in `component-tokens`.

#### Scenario: Order on a workspace page
- GIVEN the nldesign design system is active and set `utrecht` is applied
- WHEN a workspace page is rendered
- THEN the injected stylesheets MUST list `component-scopes`, then `internal-bridge`, then `generated/internal-scopes-utrecht`

#### Scenario: Custom overrides still come last
- GIVEN the admin has saved overrides
- WHEN a page is rendered
- THEN `custom-overrides` MUST follow the internal scopes file
