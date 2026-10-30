# Proposal: error-text-ink

## Why

The boards draw error text on the page, such as a late deadline ("1 dag te laat", DqDagAfsluiten), in Zuiddrecht's
error ink `#A30000`. Live drew it in the error fill colour, because `--color-text-error` read `--nldesign-color-error`,
the colour behind error buttons and badges. A token set had no way to give its error text a darker ink than its fill.

## What changes

`--color-text-error` reads a set's `--nldesign-color-error-text` when it declares one, and its error colour otherwise,
so every other set renders as before. The Zuiddrecht set declares `#A30000` (7.22:1 on white); its dark variant is
regenerated with its own value.

## Reach

Every Nextcloud and nextcloud-vue surface that paints `--color-text-error` under the Zuiddrecht set. Fills
(`--color-error`, `--color-element-error`, `--color-border-error`) are unchanged.
