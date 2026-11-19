# Tasks: body-ink-zero-specificity

### Task 1: the body ink is inherited, not forced
- **files**: `css/systems/nldesign/element-overrides.css`
- [x] Implement: `:where(body) { color: … }` replaces the forced element list; dashboard panels set `color`
- [x] Test: `tests/vitest/tileWidgetOptOut.spec.js` (no rule forces a colour onto a div or span)
