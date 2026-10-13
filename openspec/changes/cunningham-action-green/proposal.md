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

Only instances on the Cunningham set change, and only in one thing: the filled primary button.

Turns green:

- the main button of a page (NcButton primary, `button.primary`, `input.primary`)
- the confirm button of a dialog
- the login button

Stays as it was: everything else. Checked checkboxes, radio buttons and switches, the counter
badge, the folder and share icons in Files, the selected navigation entry, the focus ring, the
storage bar, the header and the links keep the set's blue.

A first version of this change wired the action colour into Nextcloud's primary element colour,
and a live look showed the checkboxes, switches and folder icons turning green too. Ruben decided
on 13 Oct 2026 that only the main button gets the colour (`action-colour-main-button-only`).

The main button's label is white on `#1f883d`, 4.52:1, in both themes. Before it was `#eaf1fb` on
`#1167d4`, 4.72:1.

Screens: every page of a Cunningham instance that shows a primary button. The OpenProject boards
are the reference for the green; no Nextcloud board draws Cunningham with it yet.
