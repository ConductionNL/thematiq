# Tasks: button-tokens-not-forced

### Task 1: Button weight, corner and border width are tokens, not forced values
- **files**: `css/systems/nldesign/theme.css`, `css/systems/nldesign/element-overrides.css`
- [x] Implement: token hand-off through --font-weight-element and --border-radius-element; plain-button rule at core specificity; forced corner rules removed
- [x] Test: `tests/vitest/boardLookButtonsOptOut.spec.js` (no forced weight, corner or width outside the board look; the tokens still reach the button)
