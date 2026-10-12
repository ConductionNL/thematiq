---
kind: code
---

# Give the main button a colour of its own

## Why

Some house styles draw the one main button of a page in another colour than the brand. OpenProject's
is the clearest case: the top bar and the links are blue, the main button is green. The boards for
the OpenProject twin draw it that way.

Thematiq knows one colour for both. `overrides.css` reads Nextcloud's primary element colour
(`--color-primary-element`, which fills the main button) from `--nldesign-color-primary`. So a token
set cannot have a green main button without turning its header and links green too, and
`GET /api/shell-tokens` answers `action` equal to `primary` for every set.

An administrator can already move the button on one instance: the token editor has a row for
`--color-primary-element`. A token set cannot.

Decided by Ruben on 12 Oct 2026: thematiq gets a separate action token that defaults to the primary.
Token sets that want another main button colour set it, and Nextcloud's own main buttons follow it.

## What changes

- A token set may declare `--nldesign-color-action`, with `--nldesign-color-action-hover` and
  `--nldesign-color-action-text` beside it. They are optional and have no declared default.
- The NL Design System and La Suite bundles read Nextcloud's primary element colour, its hover and
  its text from those tokens first, and the main button's own tokens too.
- The text on the action colour is checked for contrast like the text on the primary.
- The shell tokens answer the action colour as `action` and its hover as `action-deep`.
- No shipped set declares the tokens. Every shipped set renders exactly as before.

## Impact

- **Nothing changes for any existing set.** Each line that reads the action token names its old
  value as the fallback. See design.md, decisions 1 and 5.
- **A set that starts using the token moves more than buttons.** Everything Nextcloud draws in the
  primary element colour follows. The list is in design.md, decision 3.
- 63 pages under `docs/reference/token-sets/` and one test fixture are regenerated, because they
  print the raw value of five main button tokens in `defaults.css`.
- No screen: no page draws differently until a token set declares the token, and none does.
