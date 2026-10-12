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

Only instances on the Cunningham set change, and they change visibly. Seen on a Nextcloud 35
instance on 12 Oct 2026, light and dark:

Turns green:

- the main button, and the confirm button of a dialog
- the login button
- a checked checkbox, radio button and switch
- the counter badge of the selected navigation entry, in light
- the folder icons and the share icons in the Files list

Stays as it was, because the La Suite bundle draws these itself:

- the header, links, filter labels and `--color-primary`
- the selected entry in the app navigation, a grey wash
- the focus ring of a text input
- the storage bar
- the counter badges in dark

The main button's label is white on `#1f883d`, 4.52:1, in both themes. Before it was `#eaf1fb` on
`#1167d4`, 4.72:1.

Screens: every page of a Cunningham instance that shows one of the above. The OpenProject boards
are the reference for the green; no Nextcloud board draws Cunningham with it yet.
