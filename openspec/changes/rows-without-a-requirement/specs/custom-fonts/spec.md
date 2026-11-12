# custom-fonts Specification (delta)

## ADDED Requirements

### Requirement: An administrator picks a font from an approved library

The Theming page SHALL list a library of approved fonts: the fonts the app bundles, each with its licence, and the fonts administrators uploaded. An administrator SHALL be able to assign a font from the library to the body or heading role without uploading a file. A font assigned this way SHALL be served from the instance, like an uploaded font.

#### Scenario: An administrator assigns a bundled font
- GIVEN the app bundles the Barlow font
- WHEN the administrator opens the font list on the Theming page
- THEN Barlow is listed with its licence
- AND assigning it to the heading role sets the heading font token to Barlow with its fallback chain
- AND no file upload is asked for
