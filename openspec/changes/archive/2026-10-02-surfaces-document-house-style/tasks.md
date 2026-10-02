# Tasks: document house style

Tick a box when the work is merged to `development`.

## 1. Profile

- [x] 1.1 `lib/Service/DocumentStyleService.php::forUser()` from the resolved set, fonts and footer config. Verify: `tests/Unit/Service/DocumentStyleServiceTest.php` for the instance default, a group-mapped user, a set without a logo, and system fonts.
- [x] 1.2 `GET /api/document-style`, `#[NoAdminRequired]`. Verify: controller test, anonymous refused.

## 2. Settings

- [x] 2.1 Documents block: document logo, cover image, extra footer line, with a preview of the profile. Verify: Playwright scenario "An administrator sets a print logo for documents".
- [x] 2.2 Store assets in app data `documents/` with type and size checks; endpoints `#[AuthorizedAdminSetting]`. Verify: controller tests for an SVG with script refused, a too-large file refused, non-admin 403.
- [x] 2.3 Bundle: footer line as a value, assets as metadata. Verify: `ConfigBundleServiceTest` round trip.

## 3. Sibling handover

- [x] 3.1 Document the PHP and HTTP contract in `docs/reference/document-style.md` with the profile shape. Verify: the docs build.
- [x] 3.2 Open issues in filinq and OpenRegister naming their half (seed `huisstijl` from the profile; logo, fonts and footer in `exportToPdf()`). Verify: issue links recorded in this task.

## 4. Quality

- [x] 4.1 l10n en and nl. Verify: `npm run test:l10n`.
- [x] 4.2 Colours in the profile meet 4.5:1 for text on background, or the profile carries the warning. Verify: unit test.

## Notes at archive (2 Oct 2026)

- 1.1 `lib/Service/DocumentStyleService.php`, with the set per user in `lib/Service/UserTokenSetResolver.php` (the signed-in user through `GroupThemingService::resolveTokenSetForRequest()`, so a preview still wins; another user by walking the same group mapping; none means the instance default). `tests/Unit/Service/DocumentStyleServiceTest.php` on the REAL `TokenSetPreviewService`, `DesignSystemService` and `ContrastService` over the shipped sets: instance default (rijkshuisstijl colours and logo), a group-mapped user (utrecht), a set without a logo (westervoort), system fonts (family, no URL) and an uploaded font (family and URL).
- 1.2 `lib/Controller/DocumentStyleController.php`; HTTP and in-process return the same values (`testGroupMappedUserGetsTheGroupSet`); anonymous refusal is the absence of `#[PublicPage]` on a `#[NoAdminRequired]` route (`testEndpointAttributes`).
- 2.1 `js/admin-documents.js` with `tests/vitest/admin-documents.spec.js` (jsdom) instead of Playwright; a browser run is owed (live check in the PR body).
- 2.2 `lib/Service/DocumentAssetService.php`: PNG, JPEG, WebP or SVG by their first bytes, at most 2 MB; an SVG with script, event handlers, foreignObject, `javascript:` or entities is refused (`testUploadChecks`). Non-admin 403 is the `#[AuthorizedAdminSetting]` attribute, asserted as elsewhere in this repo. The images are served to signed-in users at `GET /api/document-style/{kind}` with `nosniff` and a locked-down Content-Security-Policy.
- 2.3 `tests/Unit/Service/ConfigBundleServiceTest.php::testDocumentStyleFooterLineSurvivesExportAndImport`. The images do not travel in the branding package of change governance-theme-as-code yet; that is a follow-up once both have landed.
- 3.1 `docs/reference/document-style.md`.
- 3.2 Opened: https://github.com/ConductionNL/filinq/issues/1340 and https://github.com/ConductionNL/openregister/issues/4255
- 4.2 `testPoorContrastCarriesAWarning` and `testInstanceDefaultProfile` (no warning for rijkshuisstijl).
