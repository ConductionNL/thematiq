# Tasks: Zuiddrecht workplace theme

## 1. Spec
- [x] 1.1 Proposal and the four spec deltas.

## 2. Token set
- [x] 2.1 `css/tokens/zuiddrecht.css`, one flat `:root` block on the nldesign vocabulary.
- [x] 2.2 `token-sets.json` entry with `theming` and `layout`; the set is selectable.
- [x] 2.3 Logos: colour, white, emblem, grey emblem under `img/logos/`.
- [x] 2.4 Generated dark variant; the dark generator verifies the new token pairs.
- [x] 2.5 `css/token-overrides/zuiddrecht.css`: the dark page background and the dark surface.
- [x] 2.6 Generated reference page, contrast report and counts.

## 3. Fonts
- [x] 3.1 Fira Sans 500 and 600, normal and italic, in both font directories, with the licence.

## 4. Component tokens
- [x] 4.1 Generator: `selfOnly`, `fallback`, `alsoGlobals`; output unchanged for the old mapping.
- [x] 4.2 Mapping: navigation active label, navigation badge, card shadow, content surface, status pills.
- [x] 4.3 Playground chips name the new tokens.

## 5. Options
- [x] 5.1 `LayoutOptionsService`: stored choice, set defaults, resolution.
- [x] 5.2 `CssInjectionService` emits `workplace-layout` and `brand-stripe` for the resolved state.
- [x] 5.3 `POST /settings/layout`, admin panel controls, l10n.
- [x] 5.4 `css/workplace-layout.css` and `css/brand-stripe.css`.

## 6. Tests
- [x] 6.1 PHPUnit: `LayoutOptionsServiceTest`, injection, endpoint.
- [x] 6.2 vitest: `zuiddrechtTokenSet.spec.js`, `componentScopesGenerator.spec.js`, `workplaceLayout.spec.js`.

## 7. Verify
- [ ] 7.1 Live check on a running instance (the coordinator does this).
