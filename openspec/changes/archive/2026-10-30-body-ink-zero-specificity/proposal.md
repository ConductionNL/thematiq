# Proposal: body-ink-zero-specificity

## Why

The nldesign layer forced the body text colour onto body, #app, #content, .app-content, p, span, div, li and a with
`!important` and id-level specificity. A component that asks for its own colour lost: muted text through
`--color-text-maxcontrast` drew in the main text (the linked card's "niets gekoppeld" line in #1B1C1D where the board
has rgb(94, 97, 104)), and lanes had to hand their ink to `--nldesign-color-on-surface` to get it back.

## What changes

The body text colour is set on `:where(body)` only, with zero specificity and no `!important`, and inherited. Any
component rule of its own wins, so muted text keeps its token. The dashboard panels, which opt back in from the
dashboard's light-on-plain ink, now also set the body text colour as `color`. The link, heading and dashboard rules are
unchanged.
