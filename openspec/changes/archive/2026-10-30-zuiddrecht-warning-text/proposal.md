# Proposal: zuiddrecht-warning-text

## Why

Nextcloud derives `--color-warning-text` from the warning colour; for Zuiddrecht's `#7A4F00` it computes `#664700`.
The boards draw warning text in the set's warning ink itself, `#7A4F00` (the warning note on DqZaaktypeOverzicht,
board `rgb(122, 79, 0)`, live `rgb(102, 71, 0)`).

## What changes

The Zuiddrecht set sets `--nldesign-nc-color-warning-text: #7A4F00`. Its dark variant is regenerated, so dark mode gets
its own derived value (`#ffd485`) instead of the light ink.

## Reach

Every Nextcloud and nextcloud-vue surface that reads `--color-warning-text` under the Zuiddrecht set. `#7A4F00` on the
warning tint `#FFF4DE` is 6.53:1.
