# Tasks: living token reference

Tick a box when the work is merged to `development`.

## 1. Renderer

- [ ] 1.1 `lib/Service/TokenReferenceService.php` (Markdown and HTML) over `ShippedTokenSetAuditService::resolveDeclarations()` and the token registry. Verify: `tests/Unit/Service/TokenReferenceServiceTest.php` with a complete and an incomplete set, asserting "from defaults" rows.
- [ ] 1.2 Deterministic output (sorted, no timestamps). Verify: the test renders twice and compares.

## 2. Docs site

- [ ] 2.1 Generate `docs/reference/token-sets/<id>.md` and an index; `composer docs:token-reference`. Verify: a staleness test like `TokenSetContrastAuditTest` fails on an edited token file.
- [ ] 2.2 Sidebar category in `docs/sidebars.js`. Verify: the docs build.

## 3. In the app

- [ ] 3.1 `GET /api/token-sets/{id}/reference` on `CatalogController`, `#[NoAdminRequired]`, 404 for an unknown id. Verify: controller tests, including an anonymous request refused.
- [ ] 3.2 Reference link and download next to the dropdown and in the custom sets list. Verify: Playwright scenario "A supplier's developer opens the reference of a custom set".

## 4. Quality

- [ ] 4.1 Swatches carry the value as text, not colour alone; the HTML page passes axe. Verify: Playwright axe run.
- [ ] 4.2 l10n en and nl for the in-app labels. Verify: `npm run test:l10n`.
- [ ] 4.3 Dark variant values shown next to light values where a dark variant exists. Verify: unit test with a set that has `css/tokens/dark/<id>.css`.
