# zuiddrecht-token-set

## ADDED Requirements

### Requirement: The set declares the DqTokens type scale (REQ-TQZD-001)
`css/tokens/zuiddrecht.css` MUST declare `--nldesign-component-heading-1-font-size: 28px` for the page
title and `--nldesign-component-heading-3-font-size: 17px` for the card title, as the DqTokens board
draws them. It MUST NOT change Nextcloud's body text size (15 px) or its small text size (13 px), and
meta text MUST stay in the muted colour #5E6168.

#### Scenario: The sizes of DqTokens
@e2e exclude Static file check: a vitest reads css/tokens/zuiddrecht.css
- GIVEN the shipped `zuiddrecht` set
- WHEN its declarations are read
- THEN the page title MUST be 28px and the card title 17px
- AND no declaration MUST set `--default-font-size` or `--font-size-small`

#### Scenario: A dashboard card title
@e2e exclude Looked at live against the DqTokens board; the size comes from the token in the first scenario
- GIVEN the light workplace under Zuiddrecht
- WHEN the dashboard renders
- THEN a panel's title MUST be 17px

### Requirement: Keyboard focus shows a 2 px blue ring (REQ-TQZD-002)
In the light workplace under Zuiddrecht, every control that receives keyboard focus MUST show a 2 px
solid ring in the action blue #3669A5, always visible and never removed for keyboard focus. The ring
MUST reach at least 3:1 against white and against the workspace #F5F6F8. Focus by pointer MAY show no
ring where the app already leaves it out. The colour MUST be a token of the set, not a literal in a
layout sheet.

#### Scenario: Tabbing to a button
@e2e exclude Static file check plus a live look: a vitest reads the set and the ring rule; the contrast is computed in the test
- GIVEN the Zuiddrecht set and the light workplace
- WHEN a person tabs to a primary button on the dashboard
- THEN a 2px ring in #3669A5 MUST show around it

#### Scenario: Another set keeps its ring
@e2e exclude Static file check: the ring rule reads a Zuiddrecht token with Nextcloud's own as fallback
- GIVEN the `rijkshuisstijl` set
- WHEN a person tabs to a button
- THEN the ring MUST be the one that set shows today
