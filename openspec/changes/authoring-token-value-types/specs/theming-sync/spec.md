# Spec delta: theming sync (authoring-token-value-types)

## ADDED Requirements

### Requirement: Translucent colours are blended before they reach Nextcloud core

When a set's primary or background colour has an alpha below 1, the theming values sent to
Nextcloud core MUST be that colour blended over the set's background colour (white when the set
declares none), as 6-digit hex. The theming sync dialog MUST show the original value and the
blended value, with the note "Nextcloud's own theming has no transparency. It gets this colour
instead." The existing color validation for the sync request MUST stay as it is.

#### Scenario: A translucent primary colour is synced as its blend
- GIVEN an administrator on Settings > Administration > Theming
- AND a custom set whose `--nldesign-color-primary` is `#15427380` and background `#ffffff`
- WHEN the administrator applies the set and confirms the theming sync
- THEN Nextcloud core theming MUST receive the primary colour `#8aa0b9`
- AND the dialog MUST have shown `#15427380` next to `#8aa0b9` with the note

#### Scenario: An opaque colour is synced unchanged
@e2e exclude Blend arithmetic, covered by PHPUnit on CustomTokenSetService
- GIVEN a set whose primary colour is `#154273`
- WHEN its theming values are derived
- THEN `primary_color` MUST be `#154273`
