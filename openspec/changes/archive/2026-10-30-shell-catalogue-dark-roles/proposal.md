---
kind: code
---

# Dark roles in the shell catalogue

## Why

The shell catalogue gives another product every token set as eighteen roles, for a light page. The
OpenProject plugin also has a dark mode, and thematiq already ships a dark variant per set.

## What changes

- A set that ships `css/tokens/dark/<id>.css` gets an optional block in the catalogue:
  `"dark": {"tokens": {…eighteen roles…}}`.
- The values are read from that committed dark file, which `DarkPaletteService` wrote. Nothing is
  derived a second time and no instance is needed. A token the dark file leaves out keeps its
  light value, as in the browser.
- A set without a dark variant gets no `dark` block. Today that is the stock `nextcloud` set.

## Impact

- The catalogue grows; `version` stays 1, because the block is optional and nothing moved.
- No screen: the catalogue is a data file for another product.
