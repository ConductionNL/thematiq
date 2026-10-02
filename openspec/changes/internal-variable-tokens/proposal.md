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

- **Every themable internal variable becomes a token.** Each `component` and `slot` inventory entry that is not `runtime` gets an `--nldesign-nc-*` token. Each `--cn-*` entry gets an `--nldesign-cn-*` token. That is roughly 513 Nextcloud tokens and 54 Conduction tokens; the inventory holds the exact list.
- **Unset changes nothing.** A token nobody sets leaves the component exactly as Nextcloud or the library styles it, including every hardcoded value.
- **Two ways in, picked per variable.** A variable a component only reads, with its own fallback, is bridged once on `body`. A variable a component declares on its own element gets a rule on that element's selector, written only when a set or the admin gives it a value.
- **Grouped by component.** Each token carries the component that owns it, from the inventory, so the editor in change 4 can list them per component.
- **Runtime variables stay excluded.** A variable Nextcloud's JavaScript writes on render, such as `--systemtag-color` in Files, keeps status `excluded`, because a stylesheet value would be overwritten. About 15 are written through `setProperty`; the extractor also counts inline style bindings, and the inventory holds the verdict per variable.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `component-tokens`: the component token layer grows from 123 hand-picked tokens to every themable internal variable of Nextcloud and the shared library.
- `css-architecture`: two new layers, the body-level internal bridge and the generated internal scopes, with fixed positions.

## Impact

- Changed: `scripts/mapping/component-tokens.json` (generated internal section), `scripts/generate-component-scopes.mjs`, a new `css/internal-bridge.css`, `lib/Service/CssInjectionService.php`, `lib/Service/TokenRegistry.php`, the custom-overrides writer.
- New: a writer for `css/generated/internal-scopes-{set}.css`, built when a set is applied or overrides are saved.
- Visual: none until a set or admin sets one of the new tokens.
- Other Conduction apps: an app whose bundled library is older than the inventory's library version simply lacks some `--cn-*` names; setting one is then a no-op there.
