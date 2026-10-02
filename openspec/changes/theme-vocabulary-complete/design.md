## Context

See proposal.md for why. The facts the approach rests on, checked on 2 October 2026:

- Nextcloud core declares its theme variables on `:root`, under `[data-theme-default]`, `[data-theme-dark]` and the high-contrast selectors. thematiq's design systems declare the variables they map on `body`.
- `css/component-scopes.css` already copies each global it needs to a `--thematiq-global-*` name on `body` and `#nldesign-preview`, and falls back to that copy. Its header explains why the capture is on `body`, not `:root`: `body` sees both declarations.
- A house style reaches 66 of the 111 theme variables for the whole page: 52 set by the nldesign stylesheets and 14 that Nextcloud derives from the primary and background colour thematiq writes. 35 of the other 45 are commented in `css/systems/nldesign/overrides.css` with a reason. The last 10, such as `--color-main-background`, `--color-scrollbar` and the `-text` status colours, are only re-scoped inside single components by `component-scopes.css`, and fall back to Nextcloud's value there.
- The 45 fall into four groups: 20 colours Nextcloud calculates per theme or keeps internal (selection, highlight, loading, shadow, hover and text variants, the main background), 4 assistant colours, 8 sizes Nextcloud keeps at standard values (font size, line height, input border widths, container radii), and 13 layout values.
- Shipped Nextcloud code redeclares some theme variables in deeper elements, `--default-clickable-area` most of all.

## Goals / Non-Goals

**Goals:**
- Every `theme` inventory entry outside the `icon` class is mapped or settable.
- An instance that sets none of the new tokens renders identically, in every Nextcloud theme.

**Non-Goals:**
- Reaching inside components that redeclare a theme variable themselves. That is change 3, which scopes per component.
- Choosing values for the new tokens in the shipped token sets. Each set keeps Nextcloud's values until its maintainer decides otherwise.

## Decisions

### One capture on body that prefers the token, re-scoped on body's children

For each settable variable `X`, the capture block in `component-scopes.css` and a new generated stylesheet, `css/theme-scopes.css`, work together:

```css
/* capture, on body and #nldesign-preview */
--thematiq-global-X: var(--nldesign-X, var(X));

/* theme-scopes.css */
body > *, #nldesign-preview > * {
	X: var(--thematiq-global-X);
}
```

Unset, the capture is `X` as `body` sees it: whatever Nextcloud declared for the active theme. Set, it is the token. Every child of `body` then takes the capture. Nothing in Nextcloud's markup declares a theme variable between `body` and its children, so with no token set the children see the value they would have inherited anyway.

The capture keeps its existing name. The component scopes already fall back to `--thematiq-global-X`, so a component with no token of its own now follows the theme's value instead of Nextcloud's. That is what reaches the 10 variables that today are only re-scoped per component.

No rule reads a property declared on its own element. `--thematiq-global-X` is declared only on `body` and `#nldesign-preview`, and read only below them. A unit test computes every settable variable on a body child and inside the preview, and fails on an empty value, which is how a browser reports a cycle.

Alternatives considered:
- *Declare `X: var(--nldesign-X)` on `body` with a default in `defaults.css`.* This is how the 52 existing global mappings work. Rejected for the 45, because any default is one flat value, and a flat value is the dark-mode breakage every recorded reason warns about.
- *Fall back to `X` itself.* A custom property that refers to itself is invalid, and CSS then discards it. The component layer documents the same cycle.
- *Take a second capture on `body > *`.* Rejected: a component scope on a direct child of `body`, such as `#header`, would then read a capture that points back at its own declaration, which is a cycle.
- *Emit declarations from PHP only for tokens the active set declares.* It works, but the injection service would have to parse every set and every override on each request. The generated stylesheet does the same job once at build time.

### The generator is the component-scopes generator, extended

`scripts/generate-component-scopes.mjs` gains a theme section that reads the `settable` theme entries from the inventory, writes `theme-scopes.css`, and adds `var(--nldesign-X, …)` to each matching capture. One generator, one drift check (`npm run test:component-scopes`), and the existing rule that refuses two tokens claiming one variable.

### Load position

`theme-scopes.css` loads after the design-system stylesheets and before `component-scopes.css`. A component scope's own rule is more specific than `body > *`, so on an element both match, the component token wins and its fallback is the capture. A set that changes `--color-warning-hover` therefore reaches a component that has no token of its own.

### Dark values use the existing dark variant files

A set's `css/tokens/dark/{id}.css` already declares `--nldesign-*` values inside the dark scopes. A light set file declares on `:root`, and a `:root` value applies in dark too. That is right for brand colours and wrong for the 45: a pale search highlight chosen for light mode would land in dark mode. So `theme-scopes.css` resets each of the 45 tokens to `initial` inside the dark scopes:

```css
:where(<the dark scopes #705 uses>) {
	--nldesign-X: initial;
}
```

`initial` makes the token guaranteed-invalid, so the capture falls back to Nextcloud's dark value. The `:where()` gives the reset zero specificity, so a dark variant file or an override that names the dark scopes outranks it, although `theme-scopes.css` loads after both. Checked in Chrome on 2 October 2026: a light-only value stays out of dark, a dark file still sets dark, and an override with dark scopes still wins in dark. The scenario "A set gives a light value only" pins this down.

Admin overrides are written by `CustomOverridesService` as Nextcloud variable names on `:root`. For the 45 that cannot work: the `body > *` redeclaration gives each child its own value, and an inherited `:root` value loses to it. So an override of a settable variable is stored as its `--nldesign-*` token, which the capture reads. Brand overrides keep the #705 behaviour: the writer also puts a derived dark value into the two dark scopes. A settable token gets a dark value only when the admin gives one (change 4); otherwise the dark reset leaves Nextcloud's dark value in place. There is one exception to the brand rule as well. The six dark-mode-compatibility variables of REQ-CSS-007 (`--color-main-background` and its `-rgb` and `-translucent` variants, `--color-background-plain`, `--background-invert-if-dark` and `--background-invert-if-bright`) take a single admin value in light only, because one value in dark is precisely the breakage REQ-CSS-007 guards against. Change 4 adds a dark value per row.

### The recorded reasons become guidance

Each `overrides.css` comment moves into `variable-status.json` as the token's `note`. The editor shows it next to the field in change 4. Two notes become audits: the selection pair and the highlight pair join `token-set-contrast-audit`.

### Structural variables: settable, flagged, untouched by default

The 13 structural variables get the same treatment plus `advanced: true` in the registry. Nothing ships a value for them.

## Risks / Trade-offs

- [`body > *` misses elements Nextcloud moves outside `body`] → Nothing in Nextcloud renders outside `body`; teleported modals land as body children, which the selector covers. The browser test opens a modal and checks a settable variable inside it.
- [A deeper Nextcloud redeclaration hides a set value] → That is correct behaviour for a component that sets its own value, and change 3 exposes those. The inventory records each redeclaring selector, so the editor in change 4 can say "this component overrides it".
- [One more stylesheet, about 45 declarations on every body child] → Custom-property declarations on roughly ten direct children cost nothing measurable. The performance spec's existing page-weight budget covers it.
- [A set value moves a contrast pair] → The two pair audits flag it before the set ships.
- [Nextcloud puts the theme attribute on `:root` rather than `body` on some page] → The zero-specificity reset would then lose to the set's `:root` declaration on the same element. Task 2.5 checks where the attribute sits on the login page, a workspace page and the public share page before the reset lands.

## Migration Plan

No data migration. The status file moves 45 entries from `excluded` to `settable`, and the inventory guard records the higher baseline. Rollback is reverting the PR: no stored value is lost, because unset tokens were never stored.

## Seed Data

Not applicable. This change adds no OpenRegister schema.
