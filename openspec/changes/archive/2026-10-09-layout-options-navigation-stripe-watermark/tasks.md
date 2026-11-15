# Tasks: Four more layout options, and every layout choice in the bundle

## 1. Spec
- [x] 1.1 Proposal and the three spec deltas.

## 2. Service and controller
- [x] 2.1 `LayoutOptionsService`: the four options, their per-set defaults, `stylesheets()` and `inlineStyles()`.
- [x] 2.2 `LayoutController::save()`: the four values, validated before any write, audited per change.
- [x] 2.3 `CssInjectionService`: the inline width after the conditional links.

## 3. Admin page
- [x] 3.1 `templates/settings/admin.php` and `lib/Settings/Admin.php`: the four controls, the authorised keys.
- [x] 3.2 `js/admin.js`: one save for all six, the newer layers and the inline width synced without a reload.
- [x] 3.3 Strings in every locale, Dutch translated.

## 4. Stylesheets and the set
- [x] 4.1 `css/navigation-width.css`, `css/navigation-active-soft.css`, `css/brand-stripe-header-only.css`, `css/brand-stripe-login-only.css`, `css/login-watermark-off.css`.
- [x] 4.2 `token-sets.json`: zuiddrecht names 264 and the soft entry.

## 5. Bundle
- [x] 5.1 `ConfigBundleService`: `config.layoutOptions` exported, validated and applied; bundle version 4.

## 6. Tests and docs
- [x] 6.1 PHPUnit: service, controller, injection, bundle.
- [x] 6.2 Vitest: `tests/vitest/layoutOptions.spec.js`; the stylesheet inventory tests widened.
- [x] 6.3 `docs/features/toggles.md`, `docs/features/configuration-bundle.md`, CHANGELOG.

## 7. Verify
- [x] 7.1 Live check on :8080 with the zuiddrecht set: the navigation 264px and the soft entry with nothing stored; each option flipped on the admin page and seen on the open page without a reload.
      Live 2026-10-09 on the throwaway NC 34 instance (:8098, not :8080: decision 135) with zuiddrecht and nothing stored: navigation 264px, soft active entry rgb(252, 237, 236). Flipped on the admin page with no navigation of the page: workplace layout default (workplace-layout.css and header-workplace.css unlinked), stripe placement header (brand-stripe-header-only.css, gradient under #header), stripe off (no gradient), width 320 (column 320px), active style default (solid entry), login watermark off (login page links login-watermark-off.css and drops the body watermark) and back on. Shots ~/memcap-work/build-all/thematiq/live-pass/layout-options-flipped.png, layout-login-*.png.
