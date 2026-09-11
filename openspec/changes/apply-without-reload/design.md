# Design: apply and upload without a reload

Stage 2 of `MAKEOVER-PLAN.md`. The proposal says what changes; this records the decisions and the
measurements behind them, so the next reader does not re-derive them.

## Measured baseline (2026-09-10, `js/admin.js` before this change)

| What | Where | Behaviour |
|---|---|---|
| Theming-sync confirm | `:980` | `window.location.reload()` on a 1500 ms timer |
| Preview start / discard | `:555`, `:579` | forced reload |
| Config-bundle import | `:3554` | forced reload (out of scope) |
| Set change (no token diff) | `:636`, `:640` | toast "reload the page to see changes" |
| Dark variants, Marianne, menu labels | `:1180`, `:1223`, `:1289` | same toast |
| Hide slogan | `:1262` | "reload the login page" |
| Token set upload / delete | `:2996`, `:3146` | "Reload the page to apply it / refresh the dropdown" |
| Font upload / delete | `:3258`, `:3364` | same (out of scope) |
| Apply-dialog confirm | `:2109`–`:2122` | `commitTokenSetChange()` then "Token overrides applied." — **never reaches `checkAndShowThemingDialog()`**, no restyle |

The last row is the bug that made the whole feature look broken: the only path an admin takes
never offered Nextcloud's own colours and logo, so the Theming panel stayed stock forever.

## Decisions

### 1. Swap the real stylesheets; do not write variables inline

The set reaches a page as a run of elements `CssInjectionService::injectDesignSystemStyles()`
emits: design-system files, `tokens/<set>.css`, the logo `<style>`, `token-overrides/<set>.css`,
`tokens/dark/<set>.css`, the contrast fixes. Writing resolved `--nldesign-*`/`--color-*` values
inline on `<html>` (the old stage 3.5 idea) reproduces the token file only. It cannot reproduce
`token-overrides/*.css` (rules, not variables), the logo `background-image`, or the dark variant's
`@media`/attribute-scoped rules. It also cannot remove a design system: switching to `nextcloud`
means the design-system files must LEAVE the page. So the client replaces the run. Inline
`important` variables remain right for the playground's per-variable editing, where there is no
file to swap.

### 2. The server owns the list; the client asks

Only `CssInjectionService` knows which files make up a set and in which order, because it emits
them. Any JavaScript copy of that knowledge drifts. So the set-dependent part of the cascade became
a private `designSystemLayers()` list of entries; `injectDesignSystemStyles()` emits it verbatim
(the existing order tests hold unchanged), and `getStylesheetManifest()` maps the same list to URLs.
`LayerController::getStylesheets()` serves it — a new two-dependency controller rather than a ninth
service on `SettingsController`, which seven test files construct by hand.

Not in the manifest: `custom-overrides.css`, `custom-css.css`, the hide-slogan/show-menu-labels
sheets and the preview banner. They do not change with the set and must stay AFTER the set layers so
an admin's overrides keep winning; the swap inserts the new run where the old one was, so their
position is preserved.

### 3. Match by pathname, not href; inline styles by id

The page's own `<link>`s carry the template's cache-buster (`?v=d98319d8-36`); the manifest carries
`?v=<installed_version>`. Matching on the pathname (`/custom_apps/thematiq/css/tokens/x.css`) is
what makes them the same file, and it holds under both `custom_apps/` and `apps/` because
`IURLGenerator::linkTo()` produced the path. An inline `<style>` has no href, so the logo style now
carries `id="nldesign-logo-url"` (`CssInjectionService::LOGO_STYLE_ID`), added through the
`emitInlineStyle()` seam that the tests already stub.

A page rendered before this change has a logo `<style>` without the id. The swap then inserts the
new one without removing the old; the newer wins the cascade, and the next server render is clean.

### 4. Full replace, load-then-remove

The swap inserts every element of the new manifest, in order, directly after the last element of
the old run (or, with no old run, before `custom-overrides.css` / the first `link.theme`), waits for
each `<link>`'s `load` (or `error`, or 8 s), then removes the old run. While both are present the
later run wins, so there is no flash and no moment with neither set. Re-fetching the shared
design-system files on an nldesign→nldesign switch is a handful of cached requests and buys the
simplest correct algorithm; a diff-and-keep variant is available in `diffLayers()` if that ever
matters.

### 5. Core theming reaches the page the way core does it

After `POST /settings/theming`, core regenerates `/apps/theming/theme/*.css` from the new values but
the page still holds the old sheets. Core's own admin panel handles its saves by bumping the `v=` on
those links (`refreshStyles`); `layerSwap.refreshThemeStylesheets()` does the same. The panel's
fields are updated through the hooks core renders for exactly this purpose —
`[data-admin-theming-setting-primary-color]` / `-background-color` (each with a
`[data-admin-theming-setting-color-picker]` button whose label is the hex and a
`[data-admin-theming-setting-color]` swatch), `[data-admin-theming-setting-file="logo"]`,
`[data-admin-theming-preview-logo]` — read from `apps/theming/src/AdminTheming.vue`,
`ColorPickerField.vue`, `FileInputField.vue` on `stable32`. They belong to core's Vue app, so they
are re-checked per Nextcloud major; a missing hook is skipped, never fatal.

### 6. Upload adds the option, does not select it

Selecting a set is what opens the apply dialog. Auto-selecting an upload would open a confirm per
upload, which is wrong for an admin importing several sets, and pre-selecting silently would make
the set impossible to "select" (no `change` event). So the option is added in alphabetical position,
the dropdown is focused, and the toast says "Select it to apply it." The client re-reads
`GET /settings/tokensets` rather than widening the upload response: no controller change, no test
churn, one extra request the admin never notices.

### 7. One confirm: the sync is a section of the apply dialog

Plan task 2.4 wants the theming sync folded into the apply dialog as a checked-by-default section,
and switching openwoo → nextcloud → openwoo on 2026-09-10 showed why: a second modal on every
switch. So `openTokenSetApplyDialog()` reads core theming alongside the token preview,
`computeThemingPlan()` turns it into one plan (`match` with a POST payload, `reset` for the stock
set, or `none`), the apply dialog renders the plan's rows under a checked box, and the confirm
runs `applyThemingPlan()` after the layer swap. No second modal on the path an admin takes.

The standalone dialogs (`showThemingDialog`, `showThemingResetDialog`) are kept for one path
only: when the apply dialog has no token changes to show, `saveTokenSet()` runs and the sync
dialog is then the ONE dialog. That is exactly the path the seven Playwright specs and two vitest
specs that locate `#nldesign-theming-dialog-overlay` exercise (their fetch router answers the
token preview with an error), so they hold unchanged and keep testing a dialog that still exists.
Both surfaces share `computeThemingPlan()` and `themingRowsHtml()`, so they cannot disagree about
what a sync would do.

### 8. `backgroundMime`

Core paints its default background image over the plain colour until `backgroundMime` is
`backgroundColor` — what the panel's "Remove background image" sets. `applyColors()` sets it when
it applied a `background_color` and the request carries no `background` image; `applyImages()`,
which runs after, overrides it with the image mime when there is one. This is what the README used
to tell admins to do by hand.

## Not verified on the authoring machine

PHPUnit (no `vendor/`, no phpunit in the container) and Playwright were not run. Verified: `php -l`
on every PHP file, `node --check` on both scripts, vitest `layerSwap.spec.js` 13/13, both l10n
gates, `build-l10n-js --check`, and the manifest itself executed live inside the app container for
`custom-openwoo` (11 layers), `amsterdam` (11) and `nextcloud` (0).
