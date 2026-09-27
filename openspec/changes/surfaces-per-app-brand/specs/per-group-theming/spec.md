# Spec delta: per-group theming (per-group-theming)

The resolution order gains one step: an app's own brand, between an admin preview and the group mapping.

## ADDED Requirements

### Requirement: An app brand wins over the group mapping on that app's pages

On a page of an app that has a brand mapping, the effective token set MUST be the app's set, unless an admin theme preview is active for the session, which MUST still win. On every other page, resolution MUST follow the existing order (preview, group, instance default). Sessionless pages MUST keep resolving to the instance default.

#### Scenario: A branded app looks the same for every municipality

- GIVEN group `gemeente-a` mapped to `gemeente-a-huisstijl` and the Collectives app branded with `kennisbank`
- WHEN a member of `gemeente-a` opens Collectives
- THEN the page MUST render in `kennisbank`
- AND when they open Files, it MUST render in `gemeente-a-huisstijl`

#### Scenario: A preview still wins

- GIVEN an administrator with an active theme preview of `amsterdam`
- WHEN they open the branded Collectives app
- THEN the page MUST render in `amsterdam`
