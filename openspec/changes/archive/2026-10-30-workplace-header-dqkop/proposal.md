---
kind: code
---

# The workplace header as the DqKop board

## Why

Measured on 16 Oct 2026 against `dossiq/DqZaken` and `pipelinq/PqLeads`: the
top bar alone differs from the board by about 8.4% on every dossiq and
pipelinq page. The workplace header was drawn while the app navigation still
carried the brand: the search field right after the grid button, no contacts
menu, 28px padding. Since @conduction/nextcloud-vue draws the app's brand
block at the start of the bar (its `screens-brand-block-top-bar`), `DqKop`
is the bar to match: 14px and 20px padding, the search field centred in the
bar, a bell and a contacts menu as 44px round buttons, a divider, the person.

## What changes

- `css/header-workplace.css`: the bar's padding, the centred search field
  (following the grid button below 1240px), the contacts menu shown again,
  44px bell and contacts buttons, the board's icons (Material Design Icons
  magnify, bell-outline, contacts-outline, apps), the divider before the
  person, and the brand block's caption and divider in the light bar's muted
  text and border colours.
- `js/header-user.js`: the chevron is Material Design Icons' chevron-down, as
  the board draws it.

## Not in this change

- The search field's line and the brand block's width: the library draws
  those (nextcloud-vue `screens-board-top-bar-search`).
- The role under the name: it is the profile's `role`, which is data.
