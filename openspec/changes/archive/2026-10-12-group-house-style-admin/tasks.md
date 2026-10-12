# Tasks: House style of my groups moves to the administration settings

## 1. Spec
- [x] 1.1 Proposal and the per-group-theming delta.

## 2. Settings and endpoints
- [x] 2.1 `GroupHouseStyle` (`IDelegatedSettings`) in the Theming section; registered in info.xml.
- [x] 2.2 `Personal` and its info.xml entry removed.
- [x] 2.3 `MyGroupsController`: `#[AuthorizedAdminSetting(settings: GroupHouseStyle::class)]`.
- [x] 2.4 Script, template and test renamed to their admin names.

## 3. Tests and docs
- [x] 3.1 PHPUnit: the block and the endpoints' authorisation in `DelegatedGroupThemingServiceTest`.
- [x] 3.2 `docs/features/group-house-style.md`: the grant under Administration privileges.

## 4. Verify
- [x] 4.1 Live on :8080: the personal page has no thematiq block; the block and its name under Administration privileges.
