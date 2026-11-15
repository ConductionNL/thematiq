---
kind: code
---

# The action colour reaches the main button and nothing else

## Why

`action-colour-token` let a token set give its main button a colour apart from its primary. It
wired the token into Nextcloud's primary element colour (`--color-primary-element`), because that
variable fills the main button. A look at a live instance showed what else reads it: checked
checkboxes, radio buttons and switches, the counter badge, and the folder and share icons in Files
all turned green with the button.

Ruben decided on 13 Oct 2026: only the main button gets the action colour. That is what the
OpenProject boards draw and what the OpenProject plugin does, and the two products must not differ.

## What changes

- `--color-primary-element`, its hover and its text read the primary again, in the NL Design System
  and La Suite bundles, exactly as before the action colour existed.
- The action tokens are read by the main button only: its component tokens in the NL Design System
  bundle, and the primary button rules in the La Suite bundle.
- The shell tokens' `action` role no longer follows an administrator's value for
  `--color-primary-element`. It follows the main button's own tokens.
- The browser check proves both directions: unset, nothing changes; set, the buttons change and
  nothing else does.

## Impact

- No shipped set declares an action colour on development, so no page changes.
- A set that declares one changes its filled primary buttons, and nothing else.
- No screen: no page draws differently until a set declares the token.
