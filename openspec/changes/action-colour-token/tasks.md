# Tasks: action-colour-token

## 1. Wire

- [x] 1.1 `css/systems/nldesign/overrides.css` and `theme.css`: the primary element colour, its
      hover and its text read the action tokens first; `--thematiq-action-hover` is the hover step.
- [x] 1.2 `css/systems/nldesign/utrecht-bridge.css` and `defaults.css`: the main button's tokens
      read the action tokens first.
- [x] 1.3 `css/systems/lasuite/bridge.css` and `element-overrides.css`: the same for the La Suite
      bundle.

## 2. Resolve

- [x] 2.1 `TokenSetPreviewService`: `var(--optional, var(--token))` maps to `--token`; the preview
      shows a set's own action colour.
- [x] 2.2 `ContrastService::check()`: the action text on the action colour, once a set moves it.
- [x] 2.3 `ShellRoleMapper`: `action` and `action-deep` read the action tokens first.

## 3. Test

- [x] 3.1 `tests/Unit/ActionColourTokenTest.php`: every reader falls back to its old value, the
      token is optional everywhere, no shipped set declares it, every shipped set resolves as
      before, five dark variants regenerate byte for byte, a set with the token is followed, the
      contrast pair.
- [x] 3.2 `tests/Unit/Service/ShellRoleMapperTest.php`: the action role with and without the token.
- [x] 3.3 `tests/vitest/playgroundSelection.spec.js`: its copy of the server's parse reads the
      new form.

## 4. Regenerate

- [x] 4.1 `composer docs:token-reference`: 63 pages, five raw values each, no swatch moved.
- [x] 4.2 `php scripts/generate-brand-form-fixture.php`.

## 5. Document

- [x] 5.1 `docs/reference/tokens.md`: the three tokens and what follows them.
- [x] 5.2 `docs/features/shell-tokens-api.md`: the `action` role.

## 6. Checks

- [x] 6.1 The generator checks (`test:component-scopes`, `test:theme-scopes`, `test:inventory`,
      `test:lasuite-bridge-coverage` and nine more), stylelint and prettier on the touched files.
- [x] 6.2 Browser comparison of every set, light and dark, before and after: no difference.
      Forced regeneration of all dark variants: no difference.
- [x] 6.2a The browser comparison is a check in the repository:
      `npm run test:action-colour-unset` (`tests/css/check-action-colour-unset.mjs`), and
      `tests/e2e/spec-coverage/action-colour-unset.spec.ts` runs it in the Playwright job.
- [x] 6.3 `composer check:strict`, once, before the push. `npm run lint`.
- [ ] 6.4 A set that declares the token, on a running instance: the main button, a checkbox and
      the progress bar in the action colour, light and dark.
