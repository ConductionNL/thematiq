---
kind: spec
---

# Proposal: house-style-font-size-and-weight

## Summary

Row `typ-font-tokens` says the font family, size and weight are set through tokens per house style. The code does this, but the spec does not say it: `token-editor-ui` lists `--default-font-size`, `--font-size-small` and `--default-line-height` in the Typography tab, while `TokenRegistry::getTypographyTokens()` offers `--font-face` and the three `--font-weight-*` tokens, and sizes travel as component tokens per set. This change amends the spec to the code and to board TqComponenten.

## What changes

- MODIFIED `Functional Tab Groups`: the Typography tab lists `--font-face`, `--font-weight-default`, `--font-weight-element` and `--font-weight-heading`, as the registry does.
- ADDED `Font family, size and weight come from the house style`: which tokens a set may declare, how they map onto Nextcloud's variables, and that the body size stays Nextcloud's 15px.

## Evidence on development

- `lib/Service/TokenRegistry.php` `getTypographyTokens()`: `--font-face`, `--font-weight-default`, `--font-weight-element`, `--font-weight-heading`.
- `css/systems/nldesign/overrides.css`: `--font-face: var(--nldesign-font-family)`, `--font-weight-default`/`-heading` mapped, `--default-font-size` unmapped.
- `css/systems/nldesign/element-overrides.css`: heading 1 to 6, textbox and button font size defaults.
- `css/tokens/rijkshuisstijl.css`: a set that declares its own heading sizes.

## Screens

TqComponenten (the Typography group and the Typography tab). See `screens.md`.
