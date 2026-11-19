---
kind: code
---

# Give the main button its own colour in the short form

## Why

A token set may give its main button a colour apart from its primary (`action-colour-token`). The
short form that makes a house style from two colours cannot: an administrator who wants a green
main button on a blue house style has to make the set first and edit it afterwards.

## What changes

- The form "Start from your colors" gains one box, off by default: "Give the main button its own
  color", with a colour beside it.
- With the box ticked the set gets `--nldesign-color-action`, a hover step 15% darker and the text
  that reads best on it. The samples in the form show the main button in that colour, and the
  readout adds its text contrast.
- `scripts/mapping/brand-form.json` holds the three rules. A rule that reads the action colour is
  left out when the form gives none.

## Impact

- With the box not ticked the form makes exactly the set it made before.
- Three new strings, in English and Dutch. The other locales get the English text until translated.
- The parity fixture gains three cases with an action colour.
