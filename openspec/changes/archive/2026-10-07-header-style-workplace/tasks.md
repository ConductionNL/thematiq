# Tasks: The workplace top bar, as a layout option

## 1. Spec
- [x] 1.1 Proposal and the workplace-layout delta.

## 2. Service and controller
- [x] 2.1 `LayoutOptionValues` and `LayoutOptionsService`: `header_style`, its default, its stylesheet with the light layout only.
- [x] 2.2 `LayoutController::save()`: the value, validated and audited.
- [x] 2.3 `HeaderUserService` and its call from `CssInjectionService` beside the stylesheet.

## 3. Admin page
- [x] 3.1 `templates/settings/admin.php`, `lib/Settings/Admin.php`: the control and the authorised key.
- [x] 3.2 `js/admin.js`: saved with the others, the layer synced without a reload.
- [x] 3.3 Strings in every locale, Dutch translated.

## 4. Stylesheet, script and the set
- [x] 4.1 `css/header-workplace.css`, `js/header-user.js`.
- [x] 4.2 `token-sets.json`: zuiddrecht names the workplace bar and the stripe on the login card.

## 5. Tests and docs
- [x] 5.1 PHPUnit: service, controller, injection, bundle, `HeaderUserServiceTest`.
- [x] 5.2 Vitest: `tests/vitest/headerUser.spec.js`; layout and zuiddrecht specs widened.
- [x] 5.3 `docs/features/toggles.md`, CHANGELOG.

## 6. Verify
- [x] 6.1 Live check on :8080: the bar on dossiq, pipelinq, decidiq, learniq, launchpad, Dashboard, Files and Settings; the role through `occ user:profile`.
