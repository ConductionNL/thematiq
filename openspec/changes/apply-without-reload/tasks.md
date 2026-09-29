# Tasks: apply and upload without a reload

Numbers in brackets were the planning task numbers. Tick a box when
the work is merged to `development`, not when it is started.

## State on 2026-09-10

Every task below except the two marked open is in the working tree, syntax-checked, and — where a
gate exists that runs on this machine — green: vitest `layerSwap.spec.js` 13/13, both l10n gates,
`build-l10n-js --check`. The manifest was executed live inside the app container. PHPUnit and
Playwright were NOT run (no `vendor/`, no phpunit, no browser session), so the two PHPUnit cases and
the workflow spec are written but unverified; the fold of the sync dialog into the apply dialog is
deferred (design decision 7).

## 1. Spec and design

- [ ] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md`, spec deltas on
      `css-architecture`, `theming-sync-dialog`, `token-set-apply-dialog`, `custom-token-sets`,
      `theme-preview`, `theming-sync`. [plan 2.1]
- [ ] 1.2 Record the measured baseline (every reload and reload-asking toast, by line) and the
      apply-dialog bypass in `design.md`.

## 2. Server

- [ ] 2.1 `CssInjectionService::designSystemLayers()` — the set-dependent cascade as an ordered
      list; `injectDesignSystemStyles()` emits it verbatim (existing order tests unchanged). [plan 2.2]
- [ ] 2.2 `CssInjectionService::getStylesheetManifest($tokenSet)` — the same list as
      `{layer, kind, href|css, id}`; includes the custom-font link; excludes the set-independent
      layers. [plan 2.2]
- [ ] 2.3 `LayerController::getStylesheets()` + route
      `GET /settings/tokenset-stylesheets/{tokenSetId}` (admin-only, 404 for an unknown id). [plan 2.2]
- [ ] 2.4 The logo `<style>` carries `id="nldesign-logo-url"` (`emitInlineStyle(css, id)`), and the
      logo layer accepts svg/png/jpg/gif/webp under `img/logos/` so a converter-extracted PNG logo is
      picked up. [plan 2.3]
- [ ] 2.5 `ThemingService::applyColors()` sets `backgroundMime` to `backgroundColor` when it applied
      a background colour and no background image is in the same request. [plan 2.6]
- [ ] 2.6 `tests/Unit/Service/CssInjectionServiceTest.php`: the manifest's file layers equal the
      injected set layers in order, carry `?v=<installed_version>`, and the inline layer carries the
      id; stock Nextcloud's manifest is empty. **Written, not run.** [plan 2.2]

## 3. Client

- [ ] 3.1 `js/lib/layerSwap.js` (dual-mode): `pathnameOf`, `layerKey`, `diffLayers`,
      `bumpVersion`, `findLayerElements`, `insertBeforeAnchor`, `createLayerElement`, `swap`,
      `refreshStylesheets`, `refreshThemeStylesheets`. Loaded by `templates/settings/admin.php`
      before `admin.js`. [plan 2.3]
- [ ] 3.2 `js/admin.js` helper block: `fetchLayerManifest`, `applyLayersFor` (primes the current
      set's manifest at load), `refreshCustomOverridesLink`, `setConditionalLayer`,
      `refreshCoreTheming`, `updateCoreThemingPanel`, `refreshTokenSetCatalogue`,
      `upsertTokenSetOption`, `removeTokenSetOption`, `reflectSelection`. Every helper degrades to
      the old reload-asking toast when the module is absent.
- [ ] 3.3 Apply-dialog confirm: write overrides → refresh `custom-overrides.css` → save set → swap
      run → offer theming sync. Publish mode identical, and it ends the on-page preview. Fixes the
      bypass (baseline item 3). [plan 2.4]
- [ ] 3.4 `saveTokenSet()` (no token diff): save → swap → "Applied." → theming sync. [plan 2.4]
- [ ] 3.5 Theming-sync confirm: `refreshCoreTheming()` (bump core's `.theme` links, write the values
      into core's panel hooks) instead of the 1500 ms reload. [plan 2.4, 2.5]
- [ ] 3.6 Preview start/discard swap on the settings page; the preview panel is shown/hidden
      client-side; Publish is bound whenever the button exists. [plan 2.9]
- [ ] 3.7 Upload: re-read `GET /settings/tokensets`, add the `<option>` in alphabetical position and
      the `tokenSetsData` entry, focus the dropdown, toast "Select it to apply it." Delete: remove
      both; swap back to stock when the deleted set was on the page. [plan 2.8]
- [ ] 3.8 Toggles: dark variants and Marianne re-apply the current set; hide-slogan and
      show-menu-labels add/drop their stylesheet. Toasts read "Applied." (slogan: "Visible on the
      login page."). Font toasts and the config-bundle reload untouched. [plan 2.10]
- [ ] 3.9 l10n: 8 new `t('thematiq', …)` strings in `l10n/en.json`, translated in `nl.json`,
      backfilled in every other locale by `check-l10n-completeness --write`, `.js` files rebuilt.
- [ ] 3.10 `tests/vitest/layerSwap.spec.js` — the pure parts, 13 cases. **Green.**
- [ ] 3.11 Stock resets core theming. Selecting the `nextcloud` set (design system `none`) opens
      a RESET dialog — current values vs Nextcloud's defaults (`default_primary_color` /
      `default_background_color` now in the `GET /settings/theming` snapshot, core's own logo) —
      whose confirm calls `POST /settings/theming` with `reset=1` → `resetToDefaults()` →
      `ThemingDefaults::undo()` for primary, background, logo, background image (audit action
      `theming_sync_reset`). Found live on 2026-09-10: the old "match the manifest" sync offered
      the stock set's stale `#0082c9` and, having no logo, showed the previous set's wordmark as
      both Current and Proposed and would have kept it. The manifest's stale value is corrected
      to `#00679e`; `.nldesign-dialog-preview-logo` gets a size so a 1369 px SVG no longer fills
      the dialog. Reset verified live in the container (all values unset, `hasImage(logo)`
      false, `--color-primary:#00679e`).
- [ ] 3.12 Fold: the theming sync is a checked-by-default SECTION of the apply dialog
      ("Also update / reset Nextcloud theming (login page, e-mails, mobile apps)" plus the diff
      rows), applied on the same confirm — no second modal. `computeThemingPlan()` builds one
      plan (`match` / `reset` / `none`) for both surfaces; `applyThemingPlan()` runs it. The
      standalone dialogs remain ONLY for the path where the apply dialog has no token changes
      to show (then they are the one dialog), which is the path both vitest specs exercise, so
      no spec moved. Found live on 2026-09-10 when switching openwoo → nextcloud → openwoo
      produced the second modal each time. [plan 2.4, completes it]

- [ ] 3.13 The panel's pickers actually change colour, and the dialog stops appearing when
      nothing would change. Core's `ColorPickerField.vue` binds the button colour through
      `v-bind('value')`, compiled to a hash-named inline custom property
      (`background-color: var(--6cc639bc)`), so setting the label left the button blue; admin.js
      now rebinds that property, discovering the hash at runtime, and sets the text colour by
      core's own brightness rule. And `if (proposed.logo)` never compared, so a set with a logo
      always looked changed — `synced_logo` / `synced_background` app values (written by the
      sync, cleared by the reset) make the comparison possible, because core records only THAT a
      custom image exists. Verified in Chromium against the running instance: from stock, the
      primary button goes `rgb(0,103,158)` → `rgb(35,132,92)` and the background button white
      with black text, 0 navigations; re-running with everything synced opens no dialog at all.
      `tests/vitest/admin-core-theming-panel.spec.js` (8 cases) pins it — written, NOT runnable
      here (every jsdom spec in this repo fails to start on `ERR_REQUIRE_ESM` from
      `html-encoding-sniffer`, pre-existing).

- [ ] 3.14 Four defects found by driving the real page (Chromium, against the running instance):
      (a) the reset used a route of its own and answered 405 on a warm route cache, surfacing as
      "failed to apply" on a switch that had succeeded — it is now `reset=1` on the existing
      `POST /settings/theming`, which every cached table already has; (b) a theming failure was
      caught by the token-set handler and reported as "Failed to apply token set" — it has its own
      catch now; (c) `setCoreColorField()` could not recover from white-on-white, because both
      custom properties then matched the displayed value — the match is claimed once and the
      remainder is the text colour; (d) `css/tokens/nextcloud.css` still declared the pre-NC29
      `#0082c9` as "stock", so the set that exists to BE stock was not, and the apply dialog
      pinned that wrong blue into `custom-overrides.css` where `!important` kept it through every
      reset and refresh — the file is now aligned with the running instance's own stock values
      (`#00679e`, hover `#3285b1`, light `#e5eff5`, link hover `#00507a`).
      Verified from a seeded OpenWOO state: switching to `nextcloud` resets, the header shows
      core's logo, both pickers show `#00679e` with correct text contrast, 0 navigations, and it
      survives a reload.
- [ ] 3.15 Property discovery settles positionally when the heuristics cannot place a
      property. The text colour was only ever corrected when it already held pure black or
      white, so a field an earlier run had left in any other state stayed invisible until a
      reload. Verified by stranding the background picker both white-on-white and with a
      green text colour: the next sync recovers both, and a white background lands on black
      text (bg rgb(255,255,255) / text rgb(0,0,0)).

## 4. End to end

- [ ] 4.1 `tests/e2e/workflows/apply-without-reload.workflow.spec.ts`: one `load` event for
      select → confirm → sync → back to stock; set layers present then absent; core's panel shows
      the synced primary. **Written, not run.** [plan 2.11]

## 5. Open

- [ ] 5.1 Run PHPUnit and Playwright on a machine that has them; run the hydra gates via WSL and
      record the coverage line.
