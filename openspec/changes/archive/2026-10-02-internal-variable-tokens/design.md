## Context

See proposal.md for why. The inventory from change 1 gives, per internal variable: its class, the component that owns it, the selectors that declare it, and its stock value. Measured on Nextcloud 34: 438 `component` entries (131 follow a theme variable, 61 chain to another internal one, 94 hardcoded colours, 87 hardcoded sizes or timings, 65 keywords or set at runtime) and 140 `slot` entries. The shared library adds 54 `--cn-*` names, 26 declared by its own components and 50 read.

Checked in Chrome on 2 October 2026 with a minimal page:

- `body { --slot: var(--tok) }` with `--tok` unset computes `--slot` to the guaranteed-invalid value, and a component's `var(--slot, <fallback>)` then uses its own fallback.
- With `--tok` declared on `:root`, the component gets the token's value.
- A component that declares `--slot` on its own element keeps its own value either way.

## Goals / Non-Goals

**Goals:**
- Every non-runtime internal variable of Nextcloud and the shared library can be set by a set or the admin.
- Nothing renders differently until something is set.

**Non-Goals:**
- Shipping values for the new tokens in any token set.
- Variables in custom apps' own bundled copies of `@nextcloud/vue`. Those apps use the library's names, so a set value reaches them only where their bundled version has the same variable.
- Translated labels per internal token. See the decision below.

## Decisions

### One rule per set token, built at render time

A component that declares a variable on its own element ignores anything inherited, so the value has to be declared on that element too:

```css
:is(.vue-date-time-picker__wrapper[data-v-02e90461] .dp__theme_light, …):not(#thematiq-never-an-id) {
	--dp-hover-color: var(--nldesign-nc-dp-hover-color);
}
```

The `:not(#…)` adds one ID's worth of specificity. `:is()` takes the highest specificity in its list, so the rule beats the component's own declaration whatever order the stylesheets arrive in. Vue injects some component styles at runtime, after thematiq's stylesheets, so order alone is not enough. The browser check proves it: without the bump, the declared-variable checks fail.

Written for a token nobody set, that declaration would replace the component's own value with nothing. So a rule is written only for a token something gives a value. A variable components only read (a slot, or a `--cn-*` name the library reads but does not declare) gets the same rule on `body`, under the same condition.

`InternalScopesService` builds the rules on each render, from what the page already wears: the set's token file (shipped or in app data), its dark variant when that layer is injected, and the saved overrides. It reads their declarations, keeps the names the internal token map knows, and writes `var()` references only. No value from a set or an upload reaches the output. The injector emits the result as an inline `<style id="thematiq-internal-scopes">` after the component scopes, and nothing when the result is empty.

Alternatives considered:
- *Generated files per set, `css/generated/internal-scopes-{set}.css`, rebuilt when a set is applied or overrides are saved* (this design's first draft). Rejected after `runtime-files-in-appdata`: a file written into the app directory is what gave every instance a code integrity warning. Writing it to app data instead would still need a writer on every path that changes a set, and per-group theming would need one file per group's set. Reading at render has neither problem: every change takes effect on the next render.
- *A static body bridge for slots*, `body { X: var(--nldesign-nc-X) }` for all of them. Rejected, as unnecessary: the conditional rule on `body` does the same and writes nothing when unset.
- *Static rules for every declared variable, falling back to Nextcloud's stock value copied from the inventory.* Rejected. The copy freezes Nextcloud's value at extraction time, and the specificity bump makes the frozen copy win after the next Nextcloud release changes it.

### `:root` is written as body, and a dark-only token is scoped

A dark value is declared on body's dark scopes, so a rule on `:root` would not see it. The token map writes Nextcloud's `:root` selector as `body`; nothing Nextcloud renders sits outside body.

A token declared on `:root` (in the set's light file or the overrides) applies in every theme. A token declared only in dark scopes gets its rule inside those scopes, so light keeps the component's own value.

### Selectors come from the inventory, cleaned

The inventory reads selectors out of minified bundles. The generator removes escaped newlines and webpack wrappers, splits comma lists, and drops any selector jsdom cannot parse: 315 distinct selectors remain. The browser check confirms every one is valid in Chrome, because `:is()` drops an invalid selector in silence.

### Per-group theming

The rules are a function of the resolved set, so each group gets the rules of its own set with no extra work.

### Token names keep the Nextcloud name

`--nldesign-nc-` plus the variable name without its leading dashes; `--nldesign-cn-` plus the name after `--cn-` for the shared library. A theme author who reads Nextcloud's CSS finds the token without a lookup table.

### Labels are the variable names, grouped under translated headings

453 tokens would need a hand-written English label and a Dutch translation each. The editor in change 4 shows internal tokens under a translated component heading ("Date picker", "Media player") with the CSS name as the row label. Theme authors working at this depth know the names from Nextcloud's own CSS. The component headings are translated per ADR-007, in change 4, which is the first change that shows them. Until then the registry keeps internal tokens out of `getTokens()`, which feeds the editor's tabs; `isEditable()` accepts them, so an import or an override save keeps them.

### Runtime detection

The extractor marks 36 variables `runtime`. Reading the rest found more that a script writes: 18 Vue `v-bind()` hash variables, two component variables whose value is one, `--confetti-i` (an index per element) and `--mermaid-font-family` (inserted with `insertRule`). In `@conduction/nextcloud-vue` 2.57.1, 18 `--cn-*` names are written from style bindings, found by searching the library's source for each name outside a `var()`. All are excluded with that reason. A variable wrongly left `settable` is harmless: setting it shows the component's own render-time value winning.

## Risks / Trade-offs

- [A selector in the inventory no longer exists on a newer Nextcloud] → The rule is a no-op there. The inventory regeneration in change 1 updates the selectors, and the guard flags the drift.
- [Reading the set's files on every render] → They are the same small files the page already links; the overrides file is read from app data, which the injector already checks on every render. A failure skips only this layer and logs it.
- [A token set only in light also applies in dark] → Documented on the feature page: give the token a value in the dark file to differ. The inventory records no per-theme stock value for internal variables, so a light-only rule could not restore the component's own dark value.
- [A set value on a hardcoded colour breaks a component's own contrast] → Change 4's editor shows the stock value next to the field. The contrast audit stays limited to the brand pairs and the two pairs from change 2; extending it to every component pair is out of scope.

## Migration Plan

No stored data changes and no file is written, so there is no repair step. Rollback is reverting the PR.

## Seed Data

Not applicable. This change adds no OpenRegister schema.
