# Tasks: house-style-font-size-and-weight

## 1. Tags and tests
- [ ] 1.1 Add `@spec openspec/specs/token-editor-ui/spec.md#requirement-font-family-size-and-weight-come-from-the-house-style` to `TokenRegistry::getTypographyTokens()` in `lib/Service/TokenRegistry.php`
- [ ] 1.2 Extend `tests/Unit/TokenCssShapeTest.php`: `css/systems/nldesign/overrides.css` maps `--font-face` and the three `--font-weight-*` variables to their `--nldesign-*` tokens and leaves `--default-font-size` unmapped
- [ ] 1.3 Unit test on `TokenRegistry::getTypographyTokens()`: it returns exactly the Typography list of `Functional Tab Groups`
