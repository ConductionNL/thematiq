# Proposal: nldesign-select-border-control-step

## Why

The nldesign defaults give a native select the hairline border (`--nldesign-color-border`) while a text box gets the
control step (`--nldesign-color-border-dark`). A set with a light hairline then draws its selects with an edge that
fails the 3:1 non-text contrast of WCAG 1.4.11: Zuiddrecht's `#E4E6EA` on white is 1.2:1. The boards draw the select
edge the same grey as a text box (DqWerkbord "Zaaktype:" and "Kolommen:", board `rgb(130, 138, 149)`, live
`rgb(228, 230, 234)`), and the Zuiddrecht set says control borders take the dark step.

## What changes

`--nldesign-component-form-select-border-color` defaults to `--nldesign-color-border-dark` in `defaults.css`, and the
Utrecht bridge falls back to it when a set gives no `--utrecht-select-border-color`, the same chain the text box uses
through `--utrecht-form-input-border-color`.

## Reach

Native selects under every nldesign set that does not set its own select border. Sets that set
`component-form-select-border-color` or `--utrecht-select-border-color` keep their value. The generated dark variants
carry the resolved value and change when they are next regenerated.
