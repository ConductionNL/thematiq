# Tasks: delegated group house style

Tick a box when the work is merged to `development`.

## 1. Mapping model

- [ ] 1.1 `delegated` and `allowedTokenSets` on mapping entries, validated in `GroupThemingService::validateEntry()`; old entries read as not delegated. Verify: `tests/Unit/Service/GroupThemingServiceTest.php` for a delegated entry, an allowed list missing the current set (refused), and a stored mapping without the new fields.
- [ ] 1.2 `setDelegatedTokenSet(string $uid, string $group, string $tokenSet)`: subadmin check, allowed list check, generation bump, audit. Verify: unit tests for a subadmin of the group, a subadmin of another group (refused), a set outside the allowed list (refused), and a locked group (refused).

## 2. Endpoints

- [ ] 2.1 Admin endpoints carry the new fields. Verify: `SettingsController` tests.
- [ ] 2.2 `GET /api/my-groups/house-style` and `POST /api/my-groups/{group}/house-style`, `#[NoAdminRequired]`, per-group subadmin check. Verify: controller tests including a plain user (403) and the hydra no-admin-idor gate.

## 3. Screens

- [ ] 3.1 Admin: delegate toggle and allowed sets picker per mapping row, with a label for each control. Verify: Playwright scenario "An administrator delegates a group".
- [ ] 3.2 Personal section "House style of my groups" (`lib/Settings/Personal.php`, `templates/settings/personal.php`), shown only to subadmins of a delegated group, with the contrast result per allowed set. Verify: Playwright scenario "A subadmin picks the house style of their municipality".

## 4. Quality

- [ ] 4.1 l10n en and nl. Verify: `npm run test:l10n`.
- [ ] 4.2 Docs: "Let a group choose its own house style" in `docs/`. Verify: the docs build.
- [ ] 4.3 Check an allowed incomplete set and dark mode for a delegated group. Verify: manual check recorded in the PR.
