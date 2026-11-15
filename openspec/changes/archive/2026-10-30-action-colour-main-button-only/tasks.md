# Tasks: action-colour-main-button-only

- [x] 1 `overrides.css`, `theme.css`, `lasuite/bridge.css`: the three element variables read the
      primary again.
- [x] 2 `TokenSetPreviewService`: back to what it was; `variable-status.json` and `mappings.md`.
- [x] 3 `ShellRoleMapper`: `action` and `action-deep` read the main button's own tokens from the
      administrator's values, not `--color-primary-element`.
- [x] 4 `ActionColourTokenTest`: the element variables are pinned to the primary; no stylesheet
      assigns an action token to them.
- [x] 5 `tests/css/check-action-colour-unset.mjs`: with the token set, the buttons must change and
      a checked checkbox, radio, switch, progress bar, selected navigation entry, counter badge,
      input and folder icon must not, per bundle.
- [x] 6 `docs/reference/tokens.md`, `custom-token-sets.md`, `shell-tokens-api.md`.
- [x] 7 `composer check:strict` once, `npm run lint`.
