---
kind: code
---

## Why

The Zuiddrecht workplace boards (DqZijbalk, DqKop, NcLogin) draw a 264px navigation, the entry you
are on as a soft red wash with a dark red bold label, the brand stripe on the login card and the
grey shield as a login watermark. The first Zuiddrecht change made two of these admin options
(the light layout and the stripe) and hard-coded the rest for one set in `css/token-overrides/`.
An administrator of another municipality cannot make the same choices without writing CSS, and
the two options that exist do not travel in the configuration bundle, so a promotion from
acceptance to production silently loses them.

## What Changes

- **Four more layout options**, each stored in the app config, shown on the admin page with the
  pattern the two older ones use, saved through `POST /settings/layout` with them, resolved per
  token set and emitted as conditional stylesheets:
  `navigation_width` (a whole number of pixels, 200 to 480), `navigation_active_style`
  (`default` | `soft`), `brand_stripe_placement` (`header-and-login` | `header` | `login`) and
  `login_watermark` (`1` | `0`). The empty value follows the active set's `layout` block, and a set
  that names none resolves to the behaviour every set had: Nextcloud's width, the default entry,
  the stripe in both places, the watermark drawn.
- **Five conditional stylesheets**: `navigation-width.css` (reads one inline `:root` variable the
  page carries, `--thematiq-navigation-width`), `navigation-active-soft.css` (every colour a
  token, the accent with the primary as fallback), `brand-stripe-header-only.css` and
  `brand-stripe-login-only.css` (take one copy of the shared stripe rule off again) and
  `login-watermark-off.css`.
- **Zuiddrecht names** `navigation_width: 264` and `navigation_active_style: "soft"`. Its stripe
  placement and watermark stay at the built-in defaults.
- **The configuration bundle carries every layout choice** under `config.layoutOptions`, as
  stored (the empty "follow the theme" included), bundle version 4; a bundle without the section
  imports each as "follow", and a value an option refuses is a hard error naming the key.

**Out of scope:** the brand block an app shows in its own navigation (name and logo through the
component library's `nav.brand`) is the app's decision, not a theme's; thematiq exposes the name
and logo already and adds no switch for it. The component library's own stripe
(`CnBrandStripe`) still reads the `--cn-brand-stripe-*` hand-over while the placement is
`login`.

## Capabilities

### Modified Capabilities
- `workplace-layout`: the navigation width, the selected entry's style and the login watermark
  switch join the layout option, with per-set defaults.
- `brand-stripe`: where the stripe is drawn.
- `config-portability`: the layout options travel in the bundle.

## Impact
- `lib/Service/LayoutOptionsService.php`, `lib/Controller/LayoutController.php`,
  `lib/Service/CssInjectionService.php`, `lib/Settings/Admin.php`,
  `lib/Service/ConfigBundleService.php`, `templates/settings/admin.php`, `js/admin.js`,
  five files under `css/`, `token-sets.json`, `l10n/*`, `docs/features/toggles.md`,
  `docs/features/configuration-bundle.md`.
- Every other set: unchanged, measured by `LayoutOptionsServiceTest` walking every shipped set
  and by the injection test's control.
