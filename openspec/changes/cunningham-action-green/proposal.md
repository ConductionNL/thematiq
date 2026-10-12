---
kind: code
---

# Give the Cunningham set a green main button

## Why

The OpenProject boards on identity.conduction.nl/screens draw the one main button of a page green,
apart from the blue brand. The Cunningham set is the house style those boards stand on. Thematiq
has an optional action colour since `action-colour-token`; no shipped set used it.

## What changes

`css/tokens/cunningham.css` declares three tokens, and nothing else changes in it:

| Token | Value | From |
|---|---|---|
| `--nldesign-color-action` | `#1f883d` | `--sc-action` of the `openproject` design system on the boards |
| `--nldesign-color-action-hover` | `#197032` | `--sc-action-deep` of the same |
| `--nldesign-color-action-text` | `#ffffff` | 4.52:1 on the action colour |

The dark variant, the token reference page and the shell catalogue are regenerated.

## Impact

Only instances on the Cunningham set change, and they change visibly. Everything Nextcloud draws in
its primary element colour turns green:

- the main button, and the confirm button of a dialog
- the login button
- a checked checkbox, radio button and switch
- the progress bar
- the selected entry in the app navigation, and the active tab of the sidebar
- the focus border of a text input and a text area
- the counter badge

The header, links, `--color-primary` and the light tints stay blue.

Screens: every page of a Cunningham instance that shows one of the above. The OpenProject boards
are the reference for the green; no Nextcloud board draws Cunningham with it yet.
