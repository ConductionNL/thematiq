---
kind: code
---

# The OpenProject plugin's set file, both ways

## Why

The OpenProject plugin keeps one design system as a set file: `{id, name, tokens, logo, dark,
vocabulary}`. The plugin's parity page (wave T) asks thematiq to write and read that file, so a set
moves between the two products without a link. Ruben's rule since 12 Oct 2026: the two products
reach the same functionality by adding, never by removing.

## What changes

- A new download, `GET /settings/tokensets/{id}/set-file`: the set's shell catalogue entry with its logo verdict,
  as `thematiq-{id}.json`. It works for shipped and uploaded sets. The admin page lists it next to
  the Figma formats.
- `ShellCatalogueService::entry()` builds one set's entry; `build()` uses it, so the catalogue does
  not change.
- An upload that is a set file is read by `OpenProjectSetFile`: each role under the name thematiq
  reads it from, each vocabulary key with `--nldesign-` in front. Left out and named: unknown roles
  and keys, values that are not text, and the dark block.

## Not in this change

Dark colours from the file. Thematiq generates a set's dark variant; reading the dark block needs
a place to keep dark values per set, which is its own change.
