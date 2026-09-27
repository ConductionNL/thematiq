# Tasks: approved mark in the AI assistant

Tick a box when the work is merged to `development`.

## 1. Setting and endpoint

- [ ] 1.1 `lib/Service/AssistantMarkService.php` with the three app config keys and their defaults. Verify: `tests/Unit/Service/AssistantMarkServiceTest.php` for off, on with defaults, on with an own name and logo, and refusing on with an empty name.
- [ ] 1.2 `GET /api/assistant-mark` (`#[NoAdminRequired]`) and `POST /settings/assistant-mark` (`#[AuthorizedAdminSetting]`). Verify: controller tests, anonymous refused, non-admin write 403, label in Dutch for a Dutch user.
- [ ] 1.3 The three keys in the configuration bundle. Verify: `ConfigBundleServiceTest` round trip.

## 2. Settings page

- [ ] 2.1 AI assistant block with toggle, name, logo and a preview of the mark. Verify: Playwright scenario "An administrator turns on the approved mark".

## 3. Sibling handover

- [ ] 3.1 Document the endpoint contract in `docs/reference/assistant-mark.md`. Verify: the docs build.
- [ ] 3.2 Open an issue in `ConductionNL/nextcloud-vue` for rendering the mark in `CnAiChatPanel` when thematiq is installed and the mark is on. Verify: issue link recorded here.

## 4. Quality

- [ ] 4.1 The logo in the preview has the alternative text "<organisation> logo"; the label meets 4.5:1 against the panel footer in light and dark mode. Verify: Playwright axe run on the settings block.
- [ ] 4.2 l10n en and nl for the label and the block. Verify: `npm run test:l10n`.
