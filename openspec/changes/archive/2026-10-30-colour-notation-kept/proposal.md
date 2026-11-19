---
kind: code
---

# A colour keeps the text it was written in

## Why

A W3C Design Tokens file holds a colour as an object. thematiq's download turned `rgb(54, 105, 165)`
into an object and the upload gave back `#3669a5`: the same colour, another notation. The
OpenProject plugin keeps the original text in its own extension and uses it while the object is
unchanged. The Figma round left this open on the thematiq side. Wave T of the plugin's parity map,
and Ruben's rule since 12 Oct 2026 (add, never remove), ask for it.

## What changes

- The download writes a colour's text in `$extensions["nl.conduction.thematiq"].css` when it is not
  the hex itself.
- The upload reads that text, or the plugin's `$extensions["nl.conduction.openproject-thematiq"].css`,
  and uses it instead of the object when it parses to the same colour. A colour a tool changed keeps
  the object's value.
- Only sRGB notations: names, hex, `rgb()`, `hsl()` and `hwb()`. A `lab()`, `lch()` or `color()`
  value keeps its conversion to sRGB, which the upload reports and the contrast checks read.
