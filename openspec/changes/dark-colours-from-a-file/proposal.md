---
kind: code
---

# Dark colours from a file

## Why

A file from the OpenProject plugin carries the dark colours of its design system: the set file's
`dark` block, a W3C file's `dark` in the plugin's extension, a Tokens Studio file's dark token set.
Thematiq read the light colours and named the dark ones as left out, because it generates a set's
dark variant itself and had nowhere to keep dark values per uploaded set. The Figma round left this
open. Wave T of the plugin's parity map, and Ruben's rule since 12 Oct 2026 (add, never remove), ask
for it.

## What changes

- The readers turn each kind of dark block into dark values by CSS name: a role under the name
  thematiq reads it from, a vocabulary key with `--nldesign-` in front, a Tokens Studio token by its
  `cssVariable` or its path.
- The upload validates them like the light values. One the validator refuses refuses the upload.
- They are kept per uploaded set in `css/tokens/dark-pins/{id}.css` and win over the derived dark
  values in every generation, protected from the contrast repair, like a shipped set's own dark block.
- The upload result says how many dark values were kept. Deleting the set deletes them.
