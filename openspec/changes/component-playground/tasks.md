# Tasks: component playground

Tick a box when the work is merged to `development`, not when it is started.

## 1. Spec and design

- [ ] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md`, spec deltas on
      `component-playground` (new), `custom-css-overrides`, `admin-settings`, `theme-preview`.
     
- [ ] 1.2 Record the rendering decision and the reason the Vue build was not taken, measured
      against what `js/admin-mock.js` already does.

## 2. The page

- [ ] 2.1 `PlaygroundController::index()` + route `GET /playground`
      (`#[AuthorizedAdminSetting(Admin::class)]`, returns a `TemplateResponse`). A non-admin
      gets the same refusal the settings section gives, not a blank page.
- [ ] 2.2 `templates/playground.php`: the shell — left rail, sticky edit bar, section
      container — plus `css/playground.css`. Loads `js/lib/layerSwap.js`,
      `js/lib/tokenTransforms.js` and `js/playground.js`, in that order.
- [ ] 2.3 Initial state: the token set catalogue, the active set, the active preview, the
      resolved values for the active set, and the conversion report when the set has one.
      Same keys the settings page publishes, so one reader serves both.
- [ ] 2.4 Entry points: a button in the settings section and "Open playground" in the apply
      dialog.

## 3. Inventory and sections

- [ ] 3.1 `js/playground/components.json`: one entry per component — id, label, section, the
      Nextcloud variables with what each paints, the `--nldesign-*` source token, the derived
      variables that are locked, and the class names the markup uses.
- [ ] 3.2 Render one section per entry, in this order: header, app navigation, app
      content card, app sidebar, buttons, links, text inputs, select and multiselect,
      textarea, checkbox/radio/switch, note cards, toasts, dialog, popover and actions menu,
      list items, table, badges and counter bubbles, headings and paragraph, empty content,
      progress and loading, login card, e-mail template link, dark mode.
- [ ] 3.3 Header, navigation and sidebar sections annotate the live chrome instead of
      re-rendering it (design decision 2); the login card carries its own `#body-login`
      markup.
- [ ] 3.4 Each section is followed by a collapsed `<details>` listing its variables: name,
      current computed value, source `--nldesign-*` token, source NLDS token when a report
      exists, and an editor — colour picker plus hex for colours, text otherwise. One
      "Expand all / Collapse all"; open state remembered in `localStorage`.
- [ ] 3.5 Derived variables render locked with their computed value and the conversion reason,
      never with an editor (design decision 5).
- [ ] 3.6 Left rail: section list, filter box, and an "only what this set changes" toggle
      that hides sections none of whose variables the active set declares.

## 4. Editing, saving, exporting

- [ ] 4.1 Editing sets the variable inline on `<html>` with `important` priority; the real
      component repaints.
- [ ] 4.2 Sticky bar: "N unsaved changes", Save, Discard. Discard removes the inline values
      and restores the inputs to the computed values.
- [ ] 4.3 Save merges through `POST /settings/overrides`, bumps `custom-overrides.css`, waits
      for the sheet, then drops the inline values — the ordering from design decision 3.
     
- [ ] 4.4 Export: serialise the active set's resolved `--nldesign-*` values plus unsaved edits
      into one sorted flat `:root { }` block and offer it as a download.
- [ ] 4.5 Token-set switcher at the top, reusing the settings page's dropdown, its preview
      semantics and `layerSwap`; plus a dark-mode toggle for the page.
- [ ] 4.6 Conversion notes per section when the active set has a conversion report; nothing when
      it has none.

## 5. Tests

- [ ] 5.1 `tests/vitest/playgroundInventory.spec.js`: every Nextcloud variable in
      `components.json` exists in `TokenRegistry`, every `--nldesign-*` name is declared in
      `defaults.css`, and every class name appears in the stylesheets that style it (design
      decision 4).
- [ ] 5.2 `tests/vitest/playgroundStore.spec.js`: dirty tracking, the save payload equals the
      token editor's payload for the same edits, and exporting the active set with no edits
      round-trips to a file the vocabulary audit rates as it rates the source set.
- [ ] 5.3 `tests/Unit/Controller/PlaygroundControllerTest.php`: admin-only, renders, publishes
      the initial-state keys the script reads.
- [ ] 5.4 Playwright visual spec per section in light and dark for `nextcloud`,
      `rijkshuisstijl` and `openwoo`, under `tests/e2e/visual/`.
- [ ] 5.5 l10n: new strings in `l10n/en.json`, translated in `nl.json`, backfilled everywhere
      else by `check-l10n-completeness --write`, `.js` rebuilt.

## 6. Acceptance

- [ ] 6.1 With the OpenWOO set active: expand Buttons, change the primary hover, see the real
      primary button change, save, open another page and see the same hover.
- [ ] 6.2 Export an `openwoo.css` that the vocabulary audit rates complete. This is the handover
      to the converter — the reference its output is diffed against.

## 7. Open

- [ ] 7.1 Authoring the OpenWOO reference values is design work that follows this change; the
      playground is the tool, not the set (design decision 7).
