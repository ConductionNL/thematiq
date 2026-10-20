# Tasks: primary-light-text-step

### Task 1: text on the light primary reaches 4.5:1 in every set
- **files**: `css/systems/nldesign/{defaults,theme,overrides}.css`, `scripts/mapping/nlds-to-nextcloud.json`, `js/lib/tokenConverter.js`, `lib/Service/ConverterColourRepair.php`, `lib/Service/DarkPaletteService.php`, the failing `css/tokens/*.css`, every `css/tokens/dark/*.css`
- [x] Implement: the token, its mapping, the repair pairs with `from`, the shipped values
- [x] Test: `tests/Unit/Service/TokenSetConverterContrastRepairTest.php`, `scripts/check-converter-fixtures.mjs`, `tests/vitest/nextcloudVariableInventory.spec.js`
