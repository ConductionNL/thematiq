## ADDED Requirements

### Requirement: Esdoornveen's hero photo has the board's slanted corner

The esdoornveen set MUST name `--nldesign-hero-image-clip-path` with the polygon of its home board. The wilgenboom, vaartveld and warmtepompacademie sets MUST NOT name it.

#### Scenario: The Esdoornveen home
@e2e exclude Token value checked in vitest: tests/vitest/schoolTokenSets.spec.js
- GIVEN the esdoornveen set and a hero with a photo beside its text
- WHEN the portal renders the home page
- THEN the photo's lower right corner is cut off, 75px wide and 120px high
