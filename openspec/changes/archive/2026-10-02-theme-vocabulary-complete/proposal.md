---
kind: code
depends_on:
  - nc-variable-inventory
---

## Why

A house style reaches 66 of the 111 variables in Nextcloud's theme vocabulary. It cannot change the other 45. Text selection, the search-hit highlight, the loading spinner, box shadows, the warning and info hover colours, input border widths, the base font size and line height all stay stock Nextcloud under every theme.

35 of them were left out on purpose, and `overrides.css` records why. The other 10, such as `--color-main-background` and `--color-scrollbar`, are re-scoped inside single components but never set for the whole page. Almost every reason is the same one: Nextcloud calculates the value per theme, so one flat value would break dark mode or high contrast. The reason is sound. The conclusion that nobody may set the variable is not. The fix is to let a theme set a value without forcing one on every theme that does not.

## What Changes

- **Settable, not overridden.** Each of the 45 gets an `--nldesign-*` token that has no default. When a token set or the admin sets it, the Nextcloud variable takes that value. When nobody does, it keeps the exact value Nextcloud calculated for the current theme, light, dark or high contrast.
- **Values per colour scheme.** A set may give a light and a dark value, through the existing dark variant files. A set that gives only a light value keeps Nextcloud's own dark value.
- **Structural variables are marked advanced.** The 13 layout variables (header height, navigation and sidebar widths, mobile breakpoint, body height and margin, grid baseline, the three clickable-area sizes, background blur) become settable but carry an `advanced` flag. Change 4 renders them behind a warning.
- **Contrast pairs are audited together.** Selected text against the selection wash, and the search highlight against body text, join the existing contrast audit. A set that moves one side of a pair below 4.5:1 is reported.
- **The excluded-token list shrinks.** It keeps only entries the inventory classes as `icon` or `runtime`. Layout and auto-calculated variables move from excluded to settable.
- **The `overrides.css` reasons become notes.** Each reason stays next to its token as guidance for theme authors, and stops being a refusal.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `nextcloud-variable-mapping`: "Overrides CSS Structure" changes from "mapped or commented out" to "mapped, settable or excluded"; a settable variable keeps Nextcloud's per-theme value until a theme sets it.
- `token-editor-ui`: "Excluded Token Registry" shrinks to icon and runtime entries; the 45 become editable, the structural ones flagged advanced.
- `token-set-contrast-audit`: two contrast pairs are added.
- `token-import-export`: "Import Validation" takes its excluded-token example from the icon class, and a formerly excluded token now imports.
- `css-architecture`: a new layer, `theme-scopes.css`, with a fixed position between the design system and the component scopes.

## Impact

- Changed: `css/systems/nldesign/{defaults,overrides}.css`, a new generated `css/theme-scopes.css`, the lasuite bridge, `lib/Service/TokenRegistry.php` and the four `*Tokens.php` files, `scripts/mapping/variable-status.json`.
- Visual: none on an instance whose set and overrides declare none of the new tokens. The guard in change 1 and a computed-value comparison prove it.
- Other Conduction apps: they read Nextcloud's variables, so a theme that sets one of the 45 reaches them too. That is intended.
