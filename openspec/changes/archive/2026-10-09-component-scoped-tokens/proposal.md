---
kind: code
---

## Why

The token editor could only write Nextcloud's own globals, and Nextcloud has around sixty of
them for every component in the product. `--color-primary-element` alone paints the primary
button, the selected navigation entry, the sidebar's active tab, a focused text input, the
checked checkbox, the progress bar, the dialog's confirm button and the counter bubble.

So the component playground — the instrument built precisely so an admin could style one
component while looking at it — could not reach a single component. Every one of its 34 chips
named a global. Moving `Primary button` moved seven other components with it, and moving the
login button was impossible without moving the main button too. The chip rendered a control
that did something other than what its title said.

This was not a playground bug. The playground faithfully cloned rows from `TokenRegistry`,
and the registry only carried globals. Meanwhile `css/systems/nldesign/defaults.css` already
declared 104 `--nldesign-component-*` tokens and `theme.css` already painted buttons, links,
headings and form fields from them — but nothing exposed those names for editing, so the two
halves never met.

## What Changes

- **The mapping becomes data.** `scripts/mapping/component-tokens.json` holds, per component,
  the selectors it occupies and, per token, the global it replaces, its label, its type and
  whether the brand primary used to drive it. 123 component tokens across 33 components.
  `TokenRegistry.php` and `scripts/generate-component-scopes.mjs` both read it, following the
  precedent `scripts/mapping/nlds-to-nextcloud.json` set, so the editable registry and the
  stylesheet that applies it cannot drift.

- **`css/component-scopes.css` re-scopes rather than repaints.** For each component, the
  Nextcloud variable it consumes is redeclared inside that component's own subtree:

      .app-navigation {
        --color-primary-element: var(
          --nldesign-component-navigation-active-background-color,
          var(--thematiq-global-color-primary-element)
        );
      }

  Nextcloud's own stylesheets keep doing the painting. The alternative — an `!important`
  element rule per component, which is the house style in `theme.css` — was rejected: it
  would have meant roughly twenty new rule blocks written against Nextcloud internals, each
  able to drift on a release, to achieve what redirecting one variable achieves.

- **The `--thematiq-global-*` capture makes the fallback possible.** A rule cannot say
  `--color-primary-element: var(--X, var(--color-primary-element))`; a custom property that
  depends on itself is discarded as invalid, the same cycle `StockTokensService` documents.
  `:root` copies each global under a `--thematiq-global-*` name and the scopes fall back to
  the copy. Because the component tokens are declared nowhere but `defaults.css`, an instance
  that has set none renders exactly as it did before this layer existed.

- **The registry gains a component layer.** `TokenRegistry::getTokens()` now returns the brand
  globals plus the component tokens, each carrying `group` (`brand`, or the component id) and
  `primary`. The brand globals stay editable from the four-tab list — that list IS the
  brand-level control, and moving one is MEANT to move every component that has not opted out.

- **`primary_drives_components`, a separate admin toggle.** Giving the primary back its reach
  over every component stays possible, but becomes a deliberate choice rather than the only
  behaviour available. While it is on, `css/primary-lock.css` forces the 26 tokens flagged
  `primary` back to the captured global, and the editor renders those rows disabled. Stored
  per-component values are never deleted, so switching it off restores them. Default off,
  which changes nothing visually: with no per-component value stored the component tokens
  already resolve to the brand primary.

- **The playground's 34 chips are repointed**, and the inventory guard changes with them.
  `playgroundInventory.spec.js` now exempts the brand globals from its coverage rule and adds
  the inverse check — that no chip names a global — so the defect this change fixes cannot
  come back silently.

## Impact

- Affected specs: `component-tokens` (the component layer and the toggle), `css-architecture`
  (two new layers and where they sit), `admin-settings` (the toggle), `token-editor-ui` (the
  locked rows), `component-playground` (chips name component tokens).
- Affected code: `scripts/mapping/component-tokens.json`, `scripts/generate-component-scopes.mjs`,
  `css/component-scopes.css`, `css/primary-lock.css`, `css/admin.css`,
  `lib/Service/TokenRegistry.php`, `lib/Service/TokenRegistryInterface.php`,
  `lib/Service/CssInjectionService.php`, `lib/Service/ConfigBundleService.php`,
  `lib/Controller/SettingsController.php`, `lib/Settings/Admin.php`, `appinfo/routes.php`,
  `templates/settings/admin.php`, `js/admin.js`, `js/playground/components.json`.
- NOT affected, deliberately: `lib/Capabilities.php`. Its payload is a pinned eight-key public
  contract, and this setting does not change what a client renders — the colours it affects
  already reach the client as CSS.
