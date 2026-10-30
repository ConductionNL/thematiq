---
kind: code
---

# Treat the action colour as the primary in a dark variant

## Why

A token set may declare an action colour (`action-colour-token`). The dark generator knew nothing
of it. Two things followed for a set that declares one:

- The action colour was darkened like any surface, where a primary keeps its brand colour in dark
  when its text reads on it.
- A set that declared the colour without a text of its own got no action text in dark. The contrast
  check then reported the pair with the primary's text as its foreground, and the repair read a
  token the dark declarations did not hold. Generating the dark variant of such a set failed.

No shipped set declares an action colour yet, so neither showed.

## What changes

- The action colour, its hover and its text keep their light values in dark when the text reaches
  4.5:1 on the colour: the brand exception the primary has, on the action's own pair.
- A set that declares the colour without a text gets the primary's text to start from, which is
  what the light stylesheets fall back to. The repair then has a foreground to work on.

## Impact

- No existing dark variant changes. A forced regeneration of all of them writes identical files, so
  the generator version stays 9.
- No screen: no shipped set has an action colour.
