# Tasks: component playground

Tick a box when the work is merged to `development`, not when it is started.

## 1. Spec and design

- [ ] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md`, spec deltas on
      `component-playground` (new), `admin-settings`, `custom-css-overrides`, `theme-preview`,
      `nextcloud-variable-mapping`.
- [ ] 1.2 Record the rendering decision and the reason the Vue build was not taken, measured
      against what `js/admin-mock.js` already does.
- [ ] 1.3 Record why this is built into the token editor rather than served as a page, and
      leave the rejected page in the design so the trade is not re-made (design decision 2).

## 2. The instrument

- [ ] 2.1 `js/playground.js`, loaded by `templates/settings/admin.php` after `admin.js`; it
      waits for the editor admin.js renders and attaches to it.
- [ ] 2.2 The selector: move `.nldesign-tabs` above `#nldesign-preview`, add the chip row
      under it, and keep the tab buttons in sync — admin.js can no longer deactivate them
      once the strip has left its container.
- [ ] 2.3 The stage: a third `.nldesign-preview-stage[data-view="component"]` in the preview,
      with the App/Login switch hidden because the tabs now decide the view.
- [ ] 2.4 `lib/Service/PlaygroundStateService.php` + `lib/Settings/Admin.php`: publish
      `playgroundInventory`, `playgroundReasons`, `playgroundTokens`,
      `playgroundTokenSources`, `playgroundVersion` and `playgroundSet` for the set the page
      is wearing.
- [ ] 2.5 `css/playground.css`: the selector, the chips, the stage, the specimens and the
      filtered list. Nothing in it may style a specimen from anything but the real
      `--color-*` variables.

## 3. Inventory and components

- [ ] 3.1 `js/playground/components.json`: one entry per component — id, tab, title, subtitle,
      the states its stage draws, the tokens it reads with what each paints and which state,
      the token-less facts with their reason codes, and the class names its markup uses.
- [ ] 3.2 A component per chip, under the four editor tabs, covering every token the registry
      carries: header bar, login card, login button, logo & slogan, background; app
      navigation, content card, table, sidebar, text input, select, checkbox & switch,
      textarea, dialog, list item, progress; primary/secondary/tertiary/error button, note
      cards, badge & counter, toast; heading, paragraph, link, muted text, status text.
- [ ] 3.3 Stage markup per component, one specimen per state, each captioned with the state
      it draws.
- [ ] 3.4 The filtered list: cloned editor rows, each saying what it paints, grouped by the
      state that paints it. No "show all N tokens of <tab>" link back to the full list:
      the chip row above the stage already carries the full view as its first chip, which is
      the same destination and the place an admin is already looking to change what the stage
      shows. A second route to it was considered and dropped.
- [ ] 3.5 Token-less rows render the fact, no editor, and the converter's reason code.

## 4. Editing and exporting

- [ ] 4.1 An edit in a cloned row is written back into the editor's original input and
      re-dispatched there, so dirty tracking, the reset control and Save are untouched.
- [ ] 4.2 The live recolour is set on `#nldesign-preview`, so the specimen repaints and the
      settings page does not.
- [ ] 4.3 The cloned row's reset drives the editor's own reset, then re-reads what it restored.
- [ ] 4.4 Export as token set, beside Download and Upload: the active set's resolved
      `--nldesign-*` values with the SAVED overrides folded in, one sorted flat `:root { }`
      block; overrides that map to no token are reported, not dropped, and where two overrides
      read one token the variable carrying the token name wins and the loser is reported
      (design decision 6).
- [ ] 4.5 The open tab and component live in the URL hash, and a stale hash degrades to the
      plain panel.

## 5. Tests

- [ ] 5.1 `tests/vitest/playgroundInventory.spec.js`: every token exists in `TokenRegistry`,
      the components reach every token the editor can write, every component has stage markup
      and every piece of stage markup a component, every class name appears in a shipped
      stylesheet, and every reason code is one the converter defines.
- [ ] 5.2 `tests/vitest/playgroundSelection.spec.js`: the chips of a tab, the rows grouped per
      state, the URL hash round-trip and its refusals, and the export (round-trip, audit
      rating, an override written back to its token, an override that cannot be expressed, and
      two overrides competing for one token).
- [ ] 5.3 `tests/Unit/Service/PlaygroundStateServiceTest.php`: the six keys, the inventory is
      the shipped file, the tabs are the editor's own, and a missing mapping table or
      inventory costs only what it must.
- [ ] 5.4 `tests/Unit/Settings/AdminInitialStateTest.php`: the keys are published, and for the
      set the page is wearing.
- [ ] 5.5 Playwright visual spec per component in light and dark, under `tests/e2e/visual/`.
- [ ] 5.6 `tests/Unit/Service/TokenSetPreviewServiceTest.php`: the variable-to-token map read
      out of a fixture `overrides.css`, the resolved and declared layers, and what the semantic
      layer drops.
- [ ] 5.7 l10n: new strings in `l10n/en.json`, translated in `nl.json`, backfilled everywhere
      else by `check-l10n-completeness --write`, `.js` rebuilt.

## 6. The stock token set

- [ ] 6.1 `lib/Service/StockTokensService.php`: build the `nextcloud` set from
      `DefaultTheme::getCSSVariables()`, inverting the `--color-*` → `--nldesign-*` map that
      `TokenSetPreviewService` parses out of `overrides.css` (design decision 8).
- [ ] 6.2 Literals only: resolve `var()` chains, drop what cannot be frozen, and pick the
      defining variable where several read one token.
- [ ] 6.3 `lib/Service/CssInjectionService.php`: emit the resolved block as the tokens layer
      for that set, and fall back to `css/tokens/nextcloud.css` on every failure path.
- [ ] 6.4 Spec delta on `nextcloud-variable-mapping`, and `@spec` tags on the service.
- [ ] 6.5 `tests/Unit/Service/StockTokensServiceTest.php`: the inversion, the many-to-one
      choice, the values that cannot be frozen, and each way the fallback is reached.
- [ ] 6.6 Resolve the ADMIN colour, not the signed-in user’s: `getColorPrimary()` returns a
      personal colour when one is set and user theming is on by default, and this output is
      instance-wide (design decision 8).
- [ ] 6.7 Cache the resolved block across requests, keyed on the Nextcloud version and the
      theming cachebuster — this is the DEFAULT set and the layer list is built on every
      render. Successes only, so a failure does not outlive its cause (design decision 8).

## 7. The guest stylesheet

- [ ] 7.1 Vendor `core/css/guest.css` from `nextcloud/server v34.0.0` verbatim as
      `scripts/sources/nextcloud-guest.css`, keeping its AGPL-3.0-or-later SPDX header and both
      copyright lines.
- [ ] 7.2 `scripts/generate-guest-css.mjs`: re-emit every rule under
      `:where(#nldesign-preview .nldesign-pg-guestpage)` so the login specimens are painted by
      core's own declarations without a single one reaching the settings page (design decision 9).
- [ ] 7.3 `npm run generate:guest-css` / `npm run test:guest-css`, the same check/--write shape
      as `generate-lasuite-tokens.mjs`, and `css/playground-guest.css` committed as its output.
- [ ] 7.4 Keep the generated file out of the formatters that would rewrite it: `ignoreFiles` in
      `stylelint.config.js`, and the generated pair in `.prettierignore`.

## 8. Acceptance

- [ ] 8.1 Pick Buttons & Status → Primary button: the stage draws it in four states, the list
      shows five rows, changing the hover colour repaints the hover specimen, and Save writes
      it exactly as the full list would.
- [ ] 8.2 Export a token set the vocabulary audit rates complete. This is the handover to the
      converter — the reference its output is diffed against.

## 9. Open

- [ ] 9.1 Authoring the OpenWOO reference values is design work that follows this change; the
      instrument is the tool, not the set (design decision 10).
