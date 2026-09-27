# Tasks: document house style

Tick a box when the work is merged to `development`.

## 1. Profile

- [ ] 1.1 `lib/Service/DocumentStyleService.php::forUser()` from the resolved set, fonts and footer config. Verify: `tests/Unit/Service/DocumentStyleServiceTest.php` for the instance default, a group-mapped user, a set without a logo, and system fonts.
- [ ] 1.2 `GET /api/document-style`, `#[NoAdminRequired]`. Verify: controller test, anonymous refused.

## 2. Settings

- [ ] 2.1 Documents block: document logo, cover image, extra footer line, with a preview of the profile. Verify: Playwright scenario "An administrator sets a print logo for documents".
- [ ] 2.2 Store assets in app data `documents/` with type and size checks; endpoints `#[AuthorizedAdminSetting]`. Verify: controller tests for an SVG with script refused, a too-large file refused, non-admin 403.
- [ ] 2.3 Bundle: footer line as a value, assets as metadata. Verify: `ConfigBundleServiceTest` round trip.

## 3. Sibling handover

- [ ] 3.1 Document the PHP and HTTP contract in `docs/reference/document-style.md` with the profile shape. Verify: the docs build.
- [ ] 3.2 Open issues in filinq and OpenRegister naming their half (seed `huisstijl` from the profile; logo, fonts and footer in `exportToPdf()`). Verify: issue links recorded in this task.

## 4. Quality

- [ ] 4.1 l10n en and nl. Verify: `npm run test:l10n`.
- [ ] 4.2 Colours in the profile meet 4.5:1 for text on background, or the profile carries the warning. Verify: unit test.
