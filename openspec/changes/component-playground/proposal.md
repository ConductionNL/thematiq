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

This change is the component playground: an admin-only page that renders every
component Thematiq styles, inside the real Nextcloud shell so the real cascade paints it,
with the variables each component actually reads listed beside it, editable, applied to the
page as you type, saved through the same `custom-overrides.css` the token editor writes, and
exportable as a flat `--nldesign-*` token set. It is where the OpenWOO reference set gets
built by hand, against real components, so that the converter has a fixture to diff against.

It depends on the no-reload apply change and nothing else: the layer swap for the set switcher, the no-reload
save path, and the existing `/settings/overrides` endpoint. It does not depend on the
converter — the per-component "what was not applied" notes appear when the active set has a
conversion report and are simply absent when it does not.

## What Changes

- **An admin-only page, not a modal.** `GET /apps/thematiq/playground`, reached from a
  button in the settings section and from the apply dialog. A modal cannot show the header,
  the app navigation, the app sidebar or the login card, which are the most visible surfaces
  a theme changes. The page runs inside the real Nextcloud shell, so the chrome around it is
  themed by the actual cascade rather than by a copy of it.
- **Vanilla rendering, no build step, following `js/admin-mock.js`.** The mock already
  renders a component stage with numbered callouts, filters the real token rows beside it,
  and recolours the component live while a token is edited — it works today behind `?mock=1`.
  The playground generalises that mechanism from one component to the full inventory. No
  bundler, no `vue`, no `@nextcloud/vue`, no dist artifact: `js/playground.js` is vanilla and
  loaded the way `js/admin.js` is. Design decision 1 records why the Vue option was
  not taken.
- **The inventory is data, not code.** `js/playground/components.json` carries one entry per
  component: id, label, section, the Nextcloud variables it reads with what each paints, the
  `--nldesign-*` token each comes from, and the variables Nextcloud derives and no one can
  set. A unit test asserts every listed Nextcloud variable exists in `TokenRegistry` and
  every listed `--nldesign-*` name exists in `css/systems/nldesign/defaults.css`, so a panel
  can never claim a variable that is not real.
- **Editing is inline on `<html>` with `important` priority.** This is the one place inline
  variables are the right tool: the component on the page is the real one, styled by the real
  cascade, and an inline `important` custom property is what beats `theme.css`'s
  `body … !important` rules without touching a stylesheet. Set switching deliberately does
  not work this way (see the apply-without-reload design); per-variable editing has no
  other option.
- **Saving goes through the endpoint that already exists.** A sticky bar shows the unsaved
  count, Save and Discard. Save merges into `custom-overrides.css` through
  `POST /settings/overrides`, the same file and endpoint the settings page's token editor
  writes, then refreshes that stylesheet and drops the inline values. The two surfaces edit
  one file; the settings page stays the compact view and the playground is the visual one.
- **Export as a token set.** A button serialises the active set's resolved `--nldesign-*`
  values plus the unsaved edits into one flat `:root { }` block — the shape `css/tokens/*.css`
  and the custom-set upload already accept — and offers it as a download. This is how the
  hand-built OpenWOO reference leaves the playground and becomes the fixture the
  converter is diffed against.
- **Navigation and comparison.** A left rail lists the sections with a filter box and an
  "only what this set changes" toggle; the token-set switcher from the settings page sits at
  the top with the same preview semantics and the same layer swap; a dark-mode toggle flips
  the page so a set can be judged in both schemes.
- **Conversion notes ride along.** When the active set has a conversion report, each section
  shows the reasons that apply to its variables. When it has none, nothing is shown — the
  playground never invents an explanation.

## Impact

- **Affected specs**: `component-playground` (new), `custom-css-overrides` (the playground is
  a second writer of the same file), `admin-settings` (the entry point), `theme-preview` (the
  switcher runs the same preview semantics).
- **Affected code**: `lib/Controller/PlaygroundController.php` (new),
  `appinfo/routes.php`, `lib/Settings/Admin.php` (entry button + initial state),
  `templates/playground.php` (new), `js/playground.js` (new),
  `js/playground/components.json` (new), `css/playground.css` (new), `js/admin.js` (the
  "Open playground" button), `l10n/*`.
- **Reused, not rebuilt**: `js/lib/layerSwap.js` for the switcher, `js/lib/tokenTransforms.js`
  for value normalisation, `POST /settings/overrides` for saving, `TokenRegistry` for the
  variable vocabulary, and `ThemePreviewService` for session previews.
- **Tests**: `tests/vitest/playgroundStore.spec.js` (dirty tracking, save payload, export
  serialisation), `tests/vitest/playgroundInventory.spec.js` (every listed variable exists),
  `tests/Unit/Controller/PlaygroundControllerTest.php` (admin-only, renders), and a Playwright
  visual spec per section in light and dark for the `nextcloud`, `rijkshuisstijl` and
  `openwoo` sets under `tests/e2e/visual/`.
- **No new dependency and no build step.** The repo's "no build for the admin panel" stance
  in `project.md` is unchanged, and `npm run build` stays fonts and icons.
