# Tasks: per-app brand

Tick a box when the work is merged to `development`.

## 1. Model and resolution

- [ ] 1.1 `app_brands` storage and validation in `AppThemingService` (installed app, not excluded, not protected, available set). Verify: `tests/Unit/Service/AppThemingServiceTest.php`.
- [ ] 1.2 Pass the resolved app id from `ThemeInjectionListener` into `CssInjectionService::inject()`; resolution order preview, app brand, group, default. Verify: `tests/Unit/Service/CssInjectionServiceTest.php` for each step and for the login page.
- [ ] 1.3 Logo layer uses the app's large and small logo. Verify: unit test on the inline layer output.

## 2. Settings

- [ ] 2.1 Brand per app block: app picker, set picker, logo uploads, contrast result, and the note that the app name stays Nextcloud's. Verify: Playwright scenario "An administrator gives the knowledge base its own brand".
- [ ] 2.2 Endpoints `#[AuthorizedAdminSetting]`, logo type and size checks. Verify: controller tests, non-admin 403, SVG with script refused.
- [ ] 2.3 Bundle: `appBrands` with logos as metadata, `bundleVersion` bump. Verify: `ConfigBundleServiceTest` round trip.

## 3. Quality

- [ ] 3.1 l10n en and nl. Verify: `npm run test:l10n`.
- [ ] 3.2 Docs: "Give an app its own brand" in `docs/`. Verify: the docs build.
- [ ] 3.3 Check a branded app in dark mode and with an incomplete set. Verify: manual check recorded in the PR.
