# Spec delta: token set picker search (token-set-picker-search)

## ADDED Requirements

### Requirement: The token set list can be searched by name

The admin page MUST have a search field for the token set list. Typing MUST hide every set whose
name and id do not contain the text, ignoring case and accents, and MUST keep the selected set in
the list. A status line MUST say how many sets match. Escape MUST empty the field and show every
set. Searching MUST NOT change the selection.

#### Scenario: Finding a set by part of its name
@e2e tests/e2e/spec-coverage/token-set-picker-search.spec.ts
- GIVEN the admin page with more than sixty sets
- WHEN the administrator types `rijks`
- THEN the list MUST show Rijkshuisstijl and the selected set, and fewer sets than before
- AND the selected set MUST be the same as before

#### Scenario: Accents and case
@e2e exclude a fixture with a set name that has an accent is needed, and no shipped set name is guaranteed to keep one; proven by tests/vitest/admin-token-set-search.spec.js (ignores case and accents, and matches the id too)
- WHEN the administrator types `FRYSLAN`
- THEN `Provinsje Fryslân` MUST match
