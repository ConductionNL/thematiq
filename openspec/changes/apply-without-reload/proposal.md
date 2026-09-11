---
kind: code
---

## Why

Applying a theme in Thematiq costs a page reload per attempt, and the reloads are where the
feature breaks. Measured in `js/admin.js` on 2026-09-10: the theming-sync confirm forces one on a
1500 ms timer; starting or discarding a session preview forces one; and eleven toasts end in
"reload the page to see changes" — after a set change, after each of four toggles, after a token
set upload or delete, after a font upload or delete. An admin who uploads a theme is told to reload
before it can even be chosen: `loadCustomTokenSets()` refreshes the list panel, but the dropdown
and the `tokenSetsData` map it was built from come from initial state and are never touched.

Worse, the one path an admin actually takes skips the theming sync entirely. The apply dialog's
confirm calls `commitTokenSetChange()` and stops; `checkAndShowThemingDialog()` is only reached
from `saveTokenSet()`, which runs only when the apply dialog had nothing to show. So the header,
login page and e-mails kept the previous instance colour however often a set was applied — baseline
item 3 of the measured baseline, and the reason the Theming panel in the screenshot that opened this
work still showed Nextcloud's own blue and logo after a conversion.

This change comes before the playground and the converter: after it, selecting a set and confirming changes the page you are looking at —
Thematiq's stylesheets, Nextcloud's own colours and logo, and the core Theming panel's fields
further up the same page — and a set you just uploaded is in the dropdown the moment the upload
returns. No reload anywhere in the flow.

## What Changes

- **The cascade becomes data the client can ask for.** `CssInjectionService` gains a private
  `designSystemLayers()` list — every set-dependent layer, in cascade order, as entries — and
  `injectDesignSystemStyles()` emits that list verbatim. A new public
  `getStylesheetManifest($tokenSet)` maps the same list to `{layer, kind, href|css, id}` so the
  page render and the manifest cannot disagree about which files make up a set. `LayerController`
  exposes it as `GET /settings/tokenset-stylesheets/{tokenSetId}` (admin-only, read-only).
- **`js/lib/layerSwap.js` swaps the stylesheet run.** Dual-mode like `tokenTransforms.js`. Inserts
  the new set's `<link>`/`<style>` elements at the place the old run occupies, waits for each to
  load, then removes the old run — no flash, and `none ↔ nldesign` works in both directions. It
  never decides what a set consists of; it matches page elements to manifest layers by pathname
  (the page's `?v=` and the manifest's differ) and inline styles by id, which is why the logo
  `<style>` now carries `id="nldesign-logo-url"`.
- **Not inline variables.** Writing resolved `--color-*` values on `<html>` cannot reproduce a
  set's `token-overrides/*.css` rules, its logo `background-image` or its dark variant's scoped
  rules; the page would look almost right, which is worse than a reload. Inline `important`
  variables stay the tool for per-variable edits in the playground (stage 3), where they are the
  only option.
- **One confirmed flow, reachable.** The apply dialog's confirm now: writes the overrides,
  re-requests `custom-overrides.css`, saves the set, swaps the run, and THEN offers the theming
  sync — the same second step `saveTokenSet()` always had. Publish mode too. The sync dialog's
  confirm re-requests core's `/apps/theming/theme/*.css` (what core's own panel does after a save)
  and writes the new values into core's panel fields on this page via its `data-admin-theming-*`
  hooks, instead of reloading.
- **Upload puts the set in the dropdown.** After a successful upload the client re-reads
  `GET /settings/tokensets`, adds the entry to `tokenSetsData` and inserts an alphabetical
  `<option>`. Deliberately not auto-selected: selecting is what opens the apply dialog, and an admin
  uploading several sets does not want one per upload. Delete removes the option and, when the
  deleted set was on the page, swaps back to stock as the server did.
- **Preview start/discard swap instead of reloading** on the settings page; the server-rendered
  preview panel is shown/hidden client-side and Publish is bound whenever the button exists. The
  banner on other pages keeps its server-rendered behaviour.
- **Toggles are true on the page they are saved on.** Dark variants and Marianne re-apply the
  current set (their layer is gated in the manifest); hide-slogan and show-menu-labels add or drop
  their own stylesheet.
- **`backgroundMime`.** A synced `background_color` now also sets `backgroundMime` to
  `backgroundColor` (unless the same request brings a background image), which is what core's
  "Remove background image" does — without it the default image blob keeps covering the colour.
- Every "reload the page" toast this makes untrue reads "Applied." (or names where the change
  shows). The font toasts and the config-bundle reload are out of scope and unchanged.

## Capabilities

### Modified Capabilities

- `css-architecture`: the set-dependent layers are one ordered list with two consumers (the page
  render and the manifest endpoint); the logo `<style>` carries an id.
- `theming-sync-dialog`: reached from every path that changes the set; its confirm refreshes
  styles and core's panel fields instead of reloading.
- `token-set-apply-dialog`: confirming applies the set to the current page and then offers the
  theming sync.
- `custom-token-sets`: an uploaded set is selectable without a reload; a deleted one disappears
  without one.
- `theme-preview`: start and discard apply on the settings page without a reload.
- `theming-sync`: a synced background colour also clears the default background image.

## Impact

- **Behaviour**: nothing an admin could do before is removed; every flow just stops asking for a
  reload, and the theming sync is now actually offered after an apply-dialog confirm — which means
  instances will start seeing their core theming follow the set, as the spec always said it should.
- **Code**: `lib/Service/CssInjectionService.php` (refactor + `getStylesheetManifest()`),
  `lib/Controller/LayerController.php` (new), `appinfo/routes.php`, `lib/Service/ThemingService.php`
  (`backgroundMime`), `js/lib/layerSwap.js` (new), `js/admin.js`, `templates/settings/admin.php`
  (loads the module), `l10n/*` (8 new strings; nl translated, other locales backfilled with the
  English source as the repo's completeness gate prescribes).
- **Tests**: `tests/vitest/layerSwap.spec.js` (pure parts), two new cases in
  `tests/Unit/Service/CssInjectionServiceTest.php` (manifest equals the injected set layers; stock
  manifest is empty), `tests/e2e/workflows/apply-without-reload.workflow.spec.ts` (one `load` event
  for the whole flow).
- **One confirm.** The theming sync is a checked-by-default section of the apply dialog — "Also
  update Nextcloud theming (login page, e-mails, mobile apps)", or "Also reset … to its defaults"
  for the stock set — applied on the same confirm. The standalone sync dialog remains only for
  the path where the apply dialog has no token changes to show, where it is the one dialog; the
  seven e2e specs and two vitest specs that locate `#nldesign-theming-dialog-overlay` exercise
  exactly that path and are unchanged.
- **No new dependency, no build step**; `js/admin.js` stays vanilla.
