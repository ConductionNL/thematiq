---
kind: code
---

## Why

There is nowhere to look at a theme. The no-reload apply change removed the reload per attempt, so changing a
set now repaints the page you are on — but the page you are on is the settings page, which
shows a header, a dropdown and four tabs of token rows. It does not show a primary button,
an input in its invalid state, a note card, a list item with an avatar, or the login card.
Those are the things a brand is judged by, and today the only way to see them is to open
another app and hope.

That gap is why the OpenWOO values were built blind. `c:/tmp/thematiq-openwoo/` was authored
one reload at a time against whatever page happened to be open, and nothing in the repo can
say whether it is right. The converter then has to answer "is the converter's output correct?"
against a reference that was itself guessed. Without a set that is known to be right, "the
converter is wrong" stays an opinion instead of a diff.

This change is the component playground: the token editor in the theming panel becomes a
selector, a stage and a filtered token list. Picking a component draws it on the preview in
each of its states, with a numbered marker per state, and narrows the editor to the tokens
that component reads — each row numbered to match. It is where the OpenWOO reference set gets
built by hand, against components, so that the converter has a fixture to diff against.

It depends on nothing that is not already in the panel: the editor's rows and its Save, the
preview container, and the token registry. The `--nldesign-*` values it exports come from the
service that already resolves them for the preview swatches.

## What Changes

- **Built into the token editor, not a page of its own.** The panel at
  `/settings/admin/theming` is where an admin already changes a theme, and the presentation
  mock already showed the shape: the editor's four tabs move above the preview and become the
  selector, a row of component chips goes under them, the preview gains a third stage next to
  its app and login views, and choosing a component filters the editor to the tokens that
  component reads. Design decision 2 records why an earlier draft's separate page was the
  wrong trade.
- **Vanilla rendering, no build step, following `js/admin-mock.js`.** The mock already
  renders a component stage with numbered callouts, filters the real token rows beside it,
  and recolours the component live while a token is edited — it works today behind `?mock=1`.
  This change generalises that mechanism from one component to the full inventory. No
  bundler, no `vue`, no `@nextcloud/vue`, no dist artifact: `js/playground.js` is vanilla and
  loaded the way `js/admin.js` is. Design decision 1 records why the Vue option was not taken.
- **The inventory is data, not code.** `js/playground/components.json` carries one entry per
  component: the tab its chip appears under, the states its stage draws, the tokens it reads
  with what each paints and which state it belongs to, the facts it depends on that have no
  token, and the class names its specimen markup uses. Unit tests assert every listed token
  exists in `TokenRegistry`, that the components between them reach every token the editor can
  write, and that every component has stage markup — so a chip can never claim a token that is
  not real, and no token is reachable only by scrolling the full list.
- **The rows are the editor's rows.** A component's token rows are clones of the editor's own,
  and an edit is written back into the original input and re-dispatched there, so the panel's
  dirty tracking, reset buttons and Save keep working untouched. There is no second store and
  no second save path. The live recolour is scoped to `#nldesign-preview`, so the specimen
  repaints and the settings page around it does not.
- **Rows for what has no token.** A disabled button's 50 % opacity, Nextcloud's clickable-area
  floor, a logo that is an asset rather than a value: each is listed with the fact, no editor,
  and the converter's own reason code, so the panel and an import report explain the same thing
  in the same words.
- **Export as a token set.** A button beside Download and Upload serialises the active set's
  resolved `--nldesign-*` values with the saved overrides folded in, as one flat `:root { }`
  block — the shape `css/tokens/*.css` and the custom-set upload already accept — and offers it
  as a download. This is how the hand-built OpenWOO reference leaves the panel and becomes the
  fixture the converter is diffed against.
- **A linkable selection.** The open tab and component live in the URL hash
  (`#preview=status/primary-button`), so a component can be linked to and survives a reload.

## Impact

- **Affected specs**: `component-playground` (new), `admin-settings` (the panel gains the
  instrument and publishes what it reads), `custom-css-overrides` (unchanged writer, new
  surface), `theme-preview` (the instrument describes the previewed set).
- **Affected code**: `lib/Service/PlaygroundStateService.php` (new),
  `lib/Service/TokenSetPreviewService.php` (resolved tokens and the variable-to-token map),
  `lib/Settings/Admin.php` (publishes the four keys), `templates/settings/admin.php` (loads
  the script and its stylesheet), `js/playground.js` (new),
  `js/playground/components.json` (new), `css/playground.css` (new), `l10n/*`.
- **Reused, not rebuilt**: the token editor's rows, dirty tracking and Save; the preview
  container and its two existing stages; `TokenRegistry` for the vocabulary;
  `css/systems/nldesign/overrides.css` for the variable-to-token map; the converter's reason
  codes for the token-less rows.
- **Tests**: `tests/vitest/playgroundInventory.spec.js` (the inventory against the registry,
  the stage markup and the stylesheets), `tests/vitest/playgroundSelection.spec.js` (chips,
  rows per state, the URL hash, the export),
  `tests/Unit/Service/PlaygroundStateServiceTest.php` (what the panel publishes and what
  happens when a piece is missing), `tests/Unit/Settings/AdminInitialStateTest.php` (the keys
  are published, for the set the page is wearing), and a Playwright visual spec per component
  in light and dark under `tests/e2e/visual/`.
- **No new dependency and no build step.** The repo's "no build for the admin panel" stance
  in `project.md` is unchanged, and `npm run build` stays fonts and icons.
