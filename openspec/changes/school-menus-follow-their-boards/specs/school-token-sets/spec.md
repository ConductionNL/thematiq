## ADDED Requirements

### Requirement: A school set names its resident menu's rows

Each of the four school sets MUST name `--nldesign-website-menu-item-min-block-size` (the board's row
height), `--nldesign-website-menu-current-bar-width: 0` and `--nldesign-website-menu-current-color` (its
accent's text colour; Wilgenboom the text colour). Zuiddrecht MUST NOT name them.

#### Scenario: Vaartveld's menu
@e2e exclude Unit test in vitest: tests/vitest/schoolTokenSets.spec.js
- GIVEN the vaartveld set on a portal
- WHEN Noor opens her own area
- THEN the menu rows are 44px, "Overzicht" stands on the aqua wash in #0B6259 without a bar
