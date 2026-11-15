---
kind: code
---

# A search field in the token set picker

## Why

The token set list holds over sixty sets and grows with every upload. thematiq's own parity list
rated `cat-searchable-picker` as partial: the list is a plain select, found only by its first
letters. The OpenProject plugin has a search field in its picker since its #7, and wave T of its
parity map asks thematiq for the same. Ruben's rule since 12 Oct 2026: add, never remove.

## What changes

- A search field above **Design token set**. Typing hides the sets whose name and id do not
  match. Case and accents do not count. The selected set always stays in the list. A status line
  says how many sets match. Escape empties the field.
- Searching never changes the selection, so it never opens the apply dialog.
- Strings in English and Dutch.
