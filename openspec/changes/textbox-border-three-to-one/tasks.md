# Tasks: textbox-border-three-to-one

### Task 1: a field's edge is 3:1 in every set
- **files**: `css/systems/nldesign/defaults.css`, `css/systems/nldesign/utrecht-bridge.css`, `scripts/mapping/nlds-to-nextcloud.json`, the repaired `css/tokens/*.css` and every `css/tokens/dark/*.css`
- [x] Implement: the default field border is the control step; the repair table holds the field borders and the control step at 3:1; failing shipped values repaired
- [x] Test: `tests/Unit/Service/BrandFormServiceTest.php` (parity fixture), `tests/Unit/ActionColourTokenTest.php` (dark variants), `scripts/check-converter-fixtures.mjs`
