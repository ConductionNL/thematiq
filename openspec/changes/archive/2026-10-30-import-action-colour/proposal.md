---
kind: code
---

# Bring an action colour in with an imported theme

## Why

A token set may give its main button a colour apart from its primary (`action-colour-token`). A
set written by hand can declare it. A theme that comes in through the converter or as a design
tokens file cannot: the mapping table has no rule for it and the DTCG mapper has no path.

## What changes

- `scripts/mapping/nlds-to-nextcloud.json`: three rules. `--{p}-color-action`,
  `--{p}-color-action-hover` and `--{p}-color-action-text` become the three action tokens.
- `DesignTokensMapper`: the paths `color.action`, `color.action-hover`, `color.action-text` and
  `color.on-action`.
- A theme that names only the action colour gets its text derived, white or black, by the rule the
  text on the primary uses.

## Impact

- A theme without an action colour converts to the same tokens as before. The rules have no
  fallback from the primary, and the Utrecht button colour stays the source of the primary.
- The three converter fixtures change in one line each: the hash of the mapping table in their
  header.
- No screen: the conversion report lists the new tokens like any other.
