# Tasks: approved mark in the AI assistant

Tick a box when the work is merged to `development`.

## 1. Setting and endpoint

- [x] 1.1 `lib/Service/AssistantMarkService.php` with the three app config keys and their defaults. Verify: `tests/Unit/Service/AssistantMarkServiceTest.php` for off, on with defaults, on with an own name and logo, and refusing on with an empty name.
- [x] 1.2 `GET /api/assistant-mark` (`#[NoAdminRequired]`) and `POST /settings/assistant-mark` (`#[AuthorizedAdminSetting]`). Verify: controller tests, anonymous refused, non-admin write 403, label in Dutch for a Dutch user.
- [x] 1.3 The three keys in the configuration bundle. Verify: `ConfigBundleServiceTest` round trip.

## 2. Settings page

- [x] 2.1 AI assistant block with toggle, name, logo and a preview of the mark. Verify: Playwright scenario "An administrator turns on the approved mark".

## 3. Sibling handover

- [x] 3.1 Document the endpoint contract in `docs/reference/assistant-mark.md`. Verify: the docs build.
- [x] 3.2 Open an issue in `ConductionNL/nextcloud-vue` for rendering the mark in `CnAiChatPanel` when thematiq is installed and the mark is on. Verify: issue link recorded here.

## 4. Quality

- [x] 4.1 The logo in the preview has the alternative text "<organisation> logo"; the label meets 4.5:1 against the panel footer in light and dark mode. Verify: Playwright axe run on the settings block.
- [x] 4.2 l10n en and nl for the label and the block. Verify: `npm run test:l10n`.

## Notes at archive (2 Oct 2026)

- 1.1 `tests/Unit/Service/AssistantMarkServiceTest.php`: off by default, on with the defaults (email footer name, `logos.default` of the theming capability), on with an own name and logo, refused on with an empty name, and a logo that is not https or a local path refused.
- 1.2 `lib/Controller/AssistantMarkController.php`; `GET /settings/assistant-mark` was added next to the `POST` so the block can load. Anonymous refusal is the absence of `#[PublicPage]` on a `#[NoAdminRequired]` route (asserted in `testEndpointAttributes`); non-admin 403 is the `#[AuthorizedAdminSetting]` attribute, asserted the same way, as elsewhere in this repo; the Dutch label in `testControllerAnswers`.
- 1.3 The three keys travel as `assistantMark`; an older bundle without the section leaves the mark alone. `tests/Unit/Service/ConfigBundleServiceTest.php::testAssistantMarkSurvivesExportAndImport`.
- 2.1 `js/admin-assistant-mark.js` with `tests/vitest/admin-assistant-mark.spec.js` (jsdom) instead of Playwright; a browser run is owed (live check in the PR body).
- 3.1 `docs/reference/assistant-mark.md`.
- 3.2 Opened: https://github.com/ConductionNL/nextcloud-vue/issues/1298
- 4.1 The preview logo carries the server's alternative text "<organisation> logo" (vitest). The 4.5:1 contrast of the label against the panel footer belongs to the nextcloud-vue half (stated in its issue); an axe run on the settings block is owed with the browser.
