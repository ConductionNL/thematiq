---
kind: code
---

## Why

With the login page and the top bar drawn (openspec/changes/workplace-layout-core-pages), the
standard apps still showed the look of a coloured-header theme: the dashboard's cards with the
controls' 8px radius and a 30px title, the Files list laid straight on the grey workspace with a
15px header row, the settings sections on the grey without a card, and the theme picker's select at
36px. The DqTokens board says what the workplace is: a grey surface, white cards with a 12px radius
and a hairline, 18px card titles, quiet 14px table headers. Measured side by side on Nextcloud 34 on
6 October 2026.

## What Changes

- **`css/workplace-layout.css` draws the standard apps' chrome** while the layout is `light`: the
  dashboard's panels as cards (container radius, hairline, the cards' shadow colour) with the card
  title size (`--nldesign-component-heading-3-font-size`); the Files list as one card on the surface,
  its header and footer rows and the README block on the card's background, the header labels 14px,
  semibold and muted, file names medium weight; the settings sections, the personal settings block
  and the profile section as cards; the theme picker's token set select 44px high with a 16px label.
- No colour literal, as before.

## Out of scope

The theme picker's markup (the board's previews, colour list and apply button) and the settings
navigation's card (the board draws a grey panel in a white card; the workplace layer keeps the white
navigation with the set's red selected entry).
