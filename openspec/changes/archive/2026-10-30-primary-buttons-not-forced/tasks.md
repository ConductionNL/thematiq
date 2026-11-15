# Tasks: primary-buttons-not-forced

### Task 1: The primary button and the button padding are not forced
- **files**: `css/systems/nldesign/theme.css`, `scripts/mapping/component-tokens.json`, `css/component-scopes.css` (generated)
- [x] Implement: forced primary and hover rules removed; on-surface hand-off for the label; input.primary and a.primary in the primary scope; padding without `!important`
- [x] Test: `tests/vitest/primaryButtonsNotForced.spec.js`; `componentTokenReach` known-gap entry removed
