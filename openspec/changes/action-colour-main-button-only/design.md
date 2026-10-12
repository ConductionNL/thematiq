# Design: the action colour reaches the main button and nothing else

## Where a primary button is drawn

| Where | Selector | NL Design System bundle | La Suite bundle |
|---|---|---|---|
| NcButton, primary, nc-vue 9 | `.button-vue--primary` | `theme.css`, from the main button tokens | `element-overrides.css` |
| NcButton, primary, nc-vue 8 | `.button-vue--vue-primary` | the same rule | the same rule |
| Nextcloud's legacy button | `button.primary` | the same rule | the same rule |
| Legacy submit input | `input.primary` | the same rule | the same rule |
| Legacy link button | `a.primary` | the same rule | not painted as a button |
| Any submit input | `input[type='submit']` | `component-scopes.css` gives it the button's variables | the same file |
| The confirm button of a dialog | `.modal-container`, `.modal-wrapper`, `.dialog` around a primary button | the dialog scope hands the button tokens down | the primary button rule |
| The login button | `#body-login .button-vue--primary` and four legacy forms, `.login-form button[type='submit']` | `theme.css` | the primary button rule (the login page uses NcButton) |

Both bundles already painted the button with rules of their own, from tokens of their own. So the
action colour never needed `--color-primary-element`.

- **NL Design System.** `utrecht-bridge.css` and `defaults.css` fill
  `--nldesign-component-button-primary-action-background-color`, `-border-color`, `-color` and
  `-hover-background-color` from the action tokens, then from a set's Utrecht button tokens, then
  from the primary. `theme.css` paints the selectors above from those tokens. `component-scopes.css`
  also sets `--color-primary-element`, its hover and its text **on the button itself** from the
  same tokens, so the states nc-vue draws (active, focus) follow too, inside the button only.
- **La Suite.** `element-overrides.css` paints the fill, the border, the label and the hover of the
  primary button, with the action tokens first and Cunningham's brand fill behind them.

## Decision 1: the element variables go back, untouched

`overrides.css`, `theme.css` and `lasuite/bridge.css` declare `--color-primary-element`,
`-hover` and `-text` as they did before `action-colour-token`. `ActionColourTokenTest` pins the
three values per file and fails on any stylesheet that assigns an action token to them.

Measured against a snapshot taken before the action colour existed: every custom property and
every button colour of every set, light and dark, is the same again. The only differences are five
values from an unrelated dark link fix.

## Decision 2: what could not be reached

- A button an app draws without one of the classes above, with its own background from
  `--color-primary-element`, stays with the primary. Nothing in Nextcloud core or nc-vue does that
  for its primary button.
- In the La Suite bundle the button's focus ring and `a.primary` are not painted from the action
  tokens. The focus ring is the bundle's own, in the brand colour.
- In a dark variant of an NL Design System set the button's colours are literals the generator
  writes from the set's own file. They follow the action colour once the set declares it and its
  dark variant is regenerated.

## Decision 3: the shell tokens

`action` was read from an administrator's `--color-primary-element` first. That variable now means
checked boxes and switches, not the button, so the role stops reading it. An administrator who
moves the main button on one instance does it with the button's own tokens in the token editor
(`--nldesign-component-button-primary-action-background-color` and its hover), and `action` and
`action-deep` follow those. Without them: the set's action colour, else its primary. No shipped
set's answer changes.

## Decision 4: the preview and the status table

The preview shows `--color-primary-element` in the primary again, for every set, which is now
true. `TokenSetPreviewService` is back to what it was. `variable-status.json` names the primary for
the three element variables again.
