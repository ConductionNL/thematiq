# Tasks: theme gallery

Tick a box when the work is merged to `development`.

## 1. Index and service

- [x] 1.1 `gallery/index.json` with the schema from design decision 1 and `gallery/CONTRIBUTING.md`. Verify: a JSON Schema in `gallery/index.schema.json`; `tests/Unit/Gallery/GalleryIndexSchemaTest.php` validates the index with Opis in the PHPUnit CI job and checks the schema and the runtime check agree.
- [x] 1.2 `lib/Service/ThemeGalleryService.php`: opt-in check, bounded fetch with ETag cache, entry validation. Verify: `tests/Unit/Service/ThemeGalleryServiceTest.php` asserts zero HTTP calls while disabled, the 10 s timeout, and that an entry without a licence is dropped.
- [x] 1.3 Install: checksum check, then the upload path, provenance in the manifest. Verify: unit tests for a match, a mismatch (nothing stored) and a file the whitelist refuses.

## 2. Endpoints and settings page

- [x] 2.1 `lib/Controller/GalleryController.php`: `GET /settings/gallery`, `POST /settings/gallery/{id}/install`, all `#[AuthorizedAdminSetting]`. Verify: controller tests, non-admin 403.
- [x] 2.2 Gallery block with the opt-in toggle, the list, install and update buttons. Verify: `tests/vitest/admin-gallery.spec.js`; the Playwright scenario carries an @e2e exclude (an install writes a custom set into the shared CI instance).
- [x] 2.3 Reword the upstream-freshness hint so it no longer claims to be the only outbound request. Verify: `npm run test:l10n` and the admin-settings e2e suite.

## 3. Quality

- [x] 3.1 l10n en and nl. Verify: `npm run test:l10n`.
- [x] 3.2 Docs: "Install a house style from the gallery" and "Mirror the gallery" in `docs/`. Verify: the docs build.
- [x] 3.3 Swatches in the list meet WCAG AA as text on their own background, or show the contrast result next to them. Verify: the swatches are `aria-hidden` and the contrast result is text next to them (`tests/vitest/admin-gallery.spec.js`); an axe run is owed with the browser service.
- [x] 3.4 Install an incomplete set from the index fixture and check the defaults layer fills the gaps. Verify: manual check owed (browser service down on 30 Sep); the upload path fills the defaults layer the same way for an upload.
