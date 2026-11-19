# Spec delta: The workplace header as the DqKop board (workplace-header-dqkop)

## ADDED Requirements

### Requirement: The workplace header lays the bar out as DqKop

While the header style resolves to `workplace`, the bar MUST be padded 14px at the start and 20px at
the end with no gap between its parts. The search field MUST be 560px wide and centred in the bar's
content box; below 1240px it MUST follow the grid button and shrink. The bell and the contacts menu
MUST be 44px round buttons with 22px icons, 6px apart, and a 1px by 28px divider in the border
colour MUST stand before the person. A brand block an app draws at the start of the bar MUST show its
caption in the muted text colour and its divider in the border colour. Every colour MUST read a
token.

#### Scenario: The bar of DqZaken
@e2e exclude Static file check: tests/vitest/headerWorkplace.spec.js; the live check is the top-bar pixel diff against dossiq/DqZaken
- GIVEN the workplace header style and a 1440px window
- WHEN a dossiq page renders
- THEN the search field MUST start at 437px, and the bell, the contacts menu, the divider and the person MUST follow at the end of the bar

#### Scenario: A narrow window
@e2e exclude Static file check: tests/vitest/headerWorkplace.spec.js
- GIVEN a window of 1200px
- WHEN the bar renders
- THEN the search field MUST follow the grid button instead of being centred
