# custom-fonts

## ADDED Requirements

### Requirement: A Bundled Font Stylesheet Is One Something Links

Every stylesheet the app ships under `css/` that declares an `@font-face`, token sets aside, MUST be
linked: listed under a design system in `design-systems.json`, or the public site layer
`css/fonts.css`, or a layer the injection service adds on an administrator's choice. The app SHALL
NOT ship a font stylesheet that nothing links.

#### Scenario: Every font stylesheet is linked

- GIVEN the stylesheets under `css/` that declare an `@font-face`, the token sets left out
- WHEN each is looked up in the design systems' stylesheet lists
- THEN each is in a list, or is `css/fonts.css`, or is the opt-in Marianne layer
- AND `css/fonts-conduction.css` is not shipped

#### Scenario: The faces of the retired stylesheet are still served

- GIVEN the nldesign design system is active
- WHEN a token set names Figtree or IBM Plex Mono
- THEN `css/systems/nldesign/fonts.css` declares that face
- AND the file it points at is in `css/systems/nldesign/fonts/`
