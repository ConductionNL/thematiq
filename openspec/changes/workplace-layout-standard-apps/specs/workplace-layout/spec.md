# Spec: Workplace layout

## ADDED Requirements

### Requirement: The Light Layout Draws The Standard Apps As Cards On The Surface
While the layout is `light`, the dashboard's panels MUST be cards: the container radius, a 1px border
in the scheme's border colour, the cards' shadow colour, and a title in the card title size
(`--nldesign-component-heading-3-font-size`). The Files list MUST be one card on the surface: its
header and footer rows and the README block MUST take the card's background, its header labels MUST
be 14px, semibold and muted. The settings sections, the personal settings block and the profile
section MUST be cards. The theme picker's token set select MUST be 44px high.

#### Scenario: The dashboard's panels are cards
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the dashboard panel rules are read
- THEN the panel MUST carry the container radius, a 1px border and the card shadow colour, and its title the card title size

#### Scenario: The Files list is one card
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the Files list rules are read
- THEN the list MUST carry the main background, a 1px border and the container radius, its header and footer rows the main background, and its header labels 14px

#### Scenario: Settings sections are cards
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rules
- GIVEN `css/workplace-layout.css`
- WHEN the settings rules are read
- THEN every section MUST carry the main background, a 1px border and the container radius
