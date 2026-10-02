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

### Slots and read-only variables: one bridge on body

For each `slot` entry, and each `--cn-*` name the library reads but does not declare, `css/internal-bridge.css` holds one line on `body`:

```css
body { --plyr-audio-control-background-hover: var(--nldesign-nc-plyr-audio-control-background-hover); }
```

Unset, the component's own fallback applies, as the Chrome check shows. Set, the value reaches every reader. This file is static and generated from the inventory.

### Declared variables: a rule on the owner's selector, written only when set

A component that declares a variable on its own element ignores anything inherited. The value has to be declared on the same element:

```css
:is(.dp__theme_light):not(#thematiq-never-an-id) {
	--dp-hover-color: var(--nldesign-nc-dp-hover-color);
}
```

The `:not(#…)` adds one ID's worth of specificity, so the rule beats the component's own class-level declaration whatever order the stylesheets arrive in. Vue injects some component styles at runtime, after thematiq's stylesheets, so order alone is not enough.

These rules are written only for tokens that have a value. `CssParserService::parseRootBlock()` and `parseDarkBlock()` already parse a set's declarations, and `CustomTokenSetValidator::validateDeclarations()` already checks values. The writer takes the parsed set plus the saved overrides, looks up each internal token's selectors in the inventory, and writes `css/generated/internal-scopes-{set}.css`. It runs when a set is applied, when overrides are saved, and on install and upgrade.

Alternatives considered:
- *Static rules for every declared variable, falling back to Nextcloud's stock value copied from the inventory.* Rejected. The copy freezes Nextcloud's value at extraction time, and the specificity bump makes the frozen copy win after the next Nextcloud release changes it. That would change appearance with nothing set, which the spec forbids.
- *Writing the rules into `custom-overrides.css`.* Rejected, because a set's own internal tokens would then exist only after an admin save.

### One file per set, so per-group theming keeps working

Group theming can serve different sets to different users. One generated file per set id means the injection service picks the file for the resolved set, exactly as it picks the set's token file.

### Token names keep the Nextcloud name

`--nldesign-nc-` plus the variable name without its leading dashes; `--nldesign-cn-` for the shared library. A theme author who reads Nextcloud's CSS finds the token without a lookup table.

### Labels are the variable names, grouped under translated headings

About 567 tokens would need a hand-written English label and a Dutch translation each. The editor in change 4 shows internal tokens under a translated component heading ("Date picker", "Media player") with the CSS name as the row label. Theme authors working at this depth know the names from Nextcloud's own CSS. The component headings are translated per ADR-007.

### Runtime detection

The extractor marks a variable `runtime` when shipped JavaScript writes it with `setProperty` or through an inline `style` binding. About 15 `setProperty` writes were found on Nextcloud 34; the binding scan decides the rest. A variable wrongly left `settable` is harmless: setting it shows the component's own render-time value winning, and the browser check in task 4.3 catches visible cases.

## Risks / Trade-offs

- [A selector in the inventory no longer exists on a newer Nextcloud] → The rule is a no-op there. The inventory regeneration in change 1 updates the selectors, and the guard flags the drift.
- [The generated file is written inside the app directory] → `custom-overrides.css` already does this, with the `ensureExists()` handling and the error logging that layer documents. The same writer path is reused.
- [About 380 static bridge lines on body] → Custom properties on one element cost nothing measurable. The page-weight budget covers the file size.
- [A set value on a hardcoded colour breaks a component's own contrast] → Change 4's editor shows the stock value next to the field. The contrast audit stays limited to the brand pairs and the two pairs from change 2; extending it to every component pair is out of scope.

## Migration Plan

No stored data changes. On upgrade, the repair step writes an empty internal scopes file per shipped set. Rollback is reverting the PR; the generated files are ignored when the layer is not injected.

## Seed Data

Not applicable. This change adds no OpenRegister schema.
