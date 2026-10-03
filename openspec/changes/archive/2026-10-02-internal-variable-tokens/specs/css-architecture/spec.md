## ADDED Requirements

### Requirement: The internal scopes sit after the component scopes
The app MUST emit the internal scopes as an inline `<style id="thematiq-internal-scopes">` directly after `css/component-scopes.css`, in every themed render context, and MUST emit nothing for them when the set and the overrides give no internal token a value. The app MUST NOT write a file for them.

@e2e exclude Stylesheet order is a server-side injection fact; the browser-visible effect is tested in `component-tokens`.

#### Scenario: Order on a workspace page
- GIVEN the active set gives `--nldesign-nc-dp-hover-color` a value
- WHEN a workspace page is rendered
- THEN the stylesheet manifest MUST list `component-scopes`, then `internal-scopes`, as an inline layer

#### Scenario: Custom overrides still come last
- GIVEN the admin has saved overrides
- WHEN a page is rendered
- THEN the `custom-overrides` link MUST follow the internal scopes

#### Scenario: Nothing set adds nothing
- GIVEN neither the active set nor the overrides declare an internal token
- WHEN a page is rendered
- THEN the page MUST carry no internal scopes layer
