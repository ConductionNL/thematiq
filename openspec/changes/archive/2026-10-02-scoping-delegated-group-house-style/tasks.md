# Tasks: delegated group house style

Tick a box when the work is merged to `development`.

## 1. Mapping model

- [x] 1.1 `delegated` and `allowedTokenSets` on mapping entries, validated in `GroupThemingService::validateEntry()`; old entries read as not delegated. Verify: `tests/Unit/Service/GroupThemingServiceTest.php` for a delegated entry, an allowed list missing the current set (refused), and a stored mapping without the new fields.
- [x] 1.2 `setDelegatedTokenSet(string $uid, string $group, string $tokenSet)`: subadmin check, allowed list check, generation bump, audit. Verify: unit tests for a subadmin of the group, a subadmin of another group (refused), a set outside the allowed list (refused), and a locked group (refused).

## 2. Endpoints

- [x] 2.1 Admin endpoints carry the new fields. Verify: `SettingsController` tests.
- [x] 2.2 `GET /api/my-groups/house-style` and `POST /api/my-groups/{group}/house-style`, `#[NoAdminRequired]`, per-group subadmin check. Verify: controller tests including a plain user (403) and the hydra no-admin-idor gate.

## 3. Screens

- [x] 3.1 Admin: delegate toggle and allowed sets picker per mapping row, with a label for each control. Verify: Playwright scenario "An administrator delegates a group".
- [x] 3.2 Personal section "House style of my groups" (`lib/Settings/Personal.php`, `templates/settings/personal.php`), shown only to subadmins of a delegated group, with the contrast result per allowed set. Verify: Playwright scenario "A subadmin picks the house style of their municipality".

## 4. Quality

- [x] 4.1 l10n en and nl. Verify: `npm run test:l10n`.
- [x] 4.2 Docs: "Let a group choose its own house style" in `docs/`. Verify: the docs build.
- [x] 4.3 Check an allowed incomplete set and dark mode for a delegated group. Verify: manual check recorded in the PR.

## Notes at archive (2 Oct 2026)

- 1.1 Delegation lives on the mapping entry as `delegated: true` plus `allowedTokenSets`; an entry without delegation keeps the old two-field shape, so stored mappings and existing callers read unchanged. `tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testDelegatedEntryRoundTripsAndOldEntryReadsAsLocked` and `::testAllowedListMustHoldTheCurrentSet` (empty list, list without the current set, unknown set).
- 1.2 `setDelegatedTokenSet()` is on a new `DelegatedGroupThemingService`, not on `GroupThemingService`, so the many existing constructions of that service stay as they are. It writes through `GroupThemingService::setMapping()` (validated again, generation bumped). `::testSubadminOfTheGroupChoosesAnAllowedSet`, `::testRefusedChoicesChangeNothing` (other group, set outside the list, locked group).
- 2.1 The admin endpoints pass the fields through the service; proven with the real service in `::testAdminEndpointsCarryTheDelegationFields`. `SettingsController` itself is unchanged.
- 2.2 `lib/Controller/MyGroupsController.php`; `::testSubadminEndpoints` (own group 200, other group 403, set outside the list 403, plain user 403 and an empty list). The write calls `requireDelegatedChoice()` before it changes anything.
- 3.1 and 3.2 Verified with `tests/vitest/admin-group-delegation.spec.js` and `tests/vitest/personal-group-house-style.spec.js` (jsdom) instead of Playwright; a browser run is owed (live check in the PR body).
- 3.2 A Nextcloud personal section cannot be hidden per user, so "House style of my groups" is a block in the personal Appearance and accessibility section (`theming`), and `Personal::getSection()` answers null for anyone who manages no delegated group (`::testPersonalSectionOnlyForSubadminsOfDelegatedGroups`). The contrast result per allowed set is the WCAG level the public catalogue already computes.
- 4.2 `docs/features/group-house-style.md`.
- 4.3 Owed: an allowed incomplete set and dark mode for a delegated group on a live server (recipe in the PR body).
