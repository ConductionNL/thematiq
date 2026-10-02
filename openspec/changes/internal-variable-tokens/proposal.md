---
kind: code
depends_on:
  - nc-variable-inventory
  - theme-vocabulary-complete
---

## Why

Nextcloud's components carry their own variables, below the theme vocabulary. The date picker has 61, the select box 42, the text editor's toolbar 30, code highlighting 18. A theme that sets every one of the 111 theme variables still cannot reach them. Of the 438 measured on Nextcloud 34, 94 hold a hardcoded colour and 87 a hardcoded size or timing. Those never follow any theme. Code blocks keep Nextcloud's syntax colours under every house style. The date picker keeps its own hover tint.

Our own shared library, `@conduction/nextcloud-vue`, adds 54 `--cn-*` variables for KPI tiles, grids and widgets. thematiq sets none of them, so a house style stops at the edge of every Conduction app's dashboard.

## What Changes

- **Every themable internal variable becomes a token.** Each settable `component` and `slot` inventory entry gets an `--nldesign-nc-*` token, and each settable `--cn-*` entry an `--nldesign-cn-*` token. Measured: 420 Nextcloud tokens and 33 Conduction tokens, 453 in all; the status file holds the exact list and the reason for every exclusion.
- **Unset changes nothing.** A token nobody sets leaves the component exactly as Nextcloud or the library styles it, including every hardcoded value.
- **One rule per set token, written at render time.** A variable a component only reads gets its rule on `body`. A variable a component declares on its own element gets a rule on that element's selectors. Either rule is written only when a set or the admin gives the token a value, as an inline layer after the component scopes. No file is written.
- **Grouped by component.** Each token carries the component that owns it, from the inventory, so the editor in change 4 can list them per component.
- **Runtime variables stay excluded.** A variable a script writes on render keeps status `excluded`, because a stylesheet value would be overwritten: `--systemtag-color` in Files, 18 Vue `v-bind()` hashes, 18 `--cn-*` names the library writes from style bindings (grid positions, tile colours, the context menu's position), and four more. Six generic slot names (`--color`, `--font-size` and the like) stay excluded too, because a value on body would reach every unrelated reader.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `component-tokens`: the component token layer grows from 123 hand-picked tokens to every themable internal variable of Nextcloud and the shared library.
- `css-architecture`: one new inline layer, the internal scopes, directly after the component scopes.

## Impact

- Changed: `scripts/mapping/variable-status.json` (453 entries settable, 47 excluded with a reason), `lib/Service/CssInjectionService.php`, `lib/Service/TokenRegistry.php` (`getInternalTokens()`, `isEditable()`).
- New: `scripts/inventory/generate-internal-tokens.mjs`, the generated `scripts/mapping/internal-tokens.json` the server reads, `lib/Service/InternalScopesService.php`, the generated `docs/reference/internal-tokens.md`.
- Visual: none until a set or admin sets one of the new tokens.
- Other Conduction apps: an app whose bundled library is older than the inventory's library version simply lacks some `--cn-*` names; setting one is then a no-op there.
