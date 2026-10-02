# Tasks: per-app brand

Tick a box when the work is merged to `development`.

## 1. Model and resolution

- [x] 1.1 `app_brands` storage and validation in `AppThemingService` (installed app, not excluded, not protected, available set). Verify: `tests/Unit/Service/AppThemingServiceTest.php`.
- [x] 1.2 Pass the resolved app id from `ThemeInjectionListener` into `CssInjectionService::inject()`; resolution order preview, app brand, group, default. Verify: `tests/Unit/Service/CssInjectionServiceTest.php` for each step and for the login page.
- [x] 1.3 Logo layer uses the app's large and small logo. Verify: unit test on the inline layer output.

## 2. Settings

- [x] 2.1 Brand per app block: app picker, set picker, logo uploads, contrast result, and the note that the app name stays Nextcloud's. Verify: Playwright scenario "An administrator gives the knowledge base its own brand".
- [x] 2.2 Endpoints `#[AuthorizedAdminSetting]`, logo type and size checks. Verify: controller tests, non-admin 403, SVG with script refused.
- [x] 2.3 Bundle: `appBrands` with logos as metadata, `bundleVersion` bump. Verify: `ConfigBundleServiceTest` round trip.

## 3. Quality

- [x] 3.1 l10n en and nl. Verify: `npm run test:l10n`.
- [x] 3.2 Docs: "Give an app its own brand" in `docs/`. Verify: the docs build.
- [x] 3.3 Check a branded app in dark mode and with an incomplete set. Verify: manual check recorded in the PR.

## Notes at archive (2 Oct 2026)

- 1.1 The brands live in a new `lib/Service/AppBrandService.php` next to `AppThemingService` rather than inside it, so its existing constructions stay as they are; `AppThemingService::PROTECTED_IDS` became public for it. `tests/Unit/Service/AppBrandServiceTest.php::testRefusals` (protected, excluded, not installed, unknown set) and `::testStaleBrandIsIgnored`.
- 1.2 `ThemeInjectionListener` passes the resolved app id to `CssInjectionService::inject()`, which passes the brand's set to `GroupThemingService::resolveTokenSetForRequest()`; the brand step sits after the preview and the no-session branch and before the group mapping. Proven on the REAL `GroupThemingService` inside a captured `CssInjectionService` in `AppBrandServiceTest` (`testBrandedAppLooksTheSameForEveryGroup`, `testPreviewStillWins`, `testSessionlessAndLoginKeepTheDefault`) rather than in `CssInjectionServiceTest`, whose harness cannot capture inline styles and which carries 7 environmental failures in a bare checkout.
- 1.3 `testLogoLayerUsesLargeAndSmall`: the large logo, and the small one below 1024 px.
- 2.1 `js/admin-app-brands.js` with `tests/vitest/admin-app-brands.spec.js` (jsdom) instead of Playwright; a browser run is owed (live check in the PR body).
- 2.2 `lib/Controller/AppBrandController.php`; `testLogoChecks` (SVG with script, too large, not an image) and `testEndpoints` (admin attributes, protected app 422). Non-admin 403 is the `#[AuthorizedAdminSetting]` attribute, asserted as elsewhere in this repo. Logos are checked by `lib/Service/ImageSniffer.php` (type from the first bytes).
- 2.3 `tests/Unit/Service/ConfigBundleServiceTest.php::testAppBrandsSurviveExportAndImport`; bundle version 3, and a version 2 bundle leaves the brands alone.
- 3.2 `docs/features/brand-per-app.md`.
- 3.3 Owed: a branded app in dark mode and with an incomplete set on a live server (recipe in the PR body).
