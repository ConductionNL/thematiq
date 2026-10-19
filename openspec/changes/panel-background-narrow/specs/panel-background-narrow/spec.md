# panel-background-narrow Delta

## ADDED Requirements

### Requirement: The panel catch-all skips nextcloud-vue

The nldesign solid-ground rule SHALL NOT paint an element with a `cn-` class through its `[class*='panel']`
catch-all.

#### Scenario: dossiq's queue

- **GIVEN** an index page with a side panel in a nextcloud-vue app under an nldesign set
- **THEN** the page shows the workspace ground around its cards, not the card colour
