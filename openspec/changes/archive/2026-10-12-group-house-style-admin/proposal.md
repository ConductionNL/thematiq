---
kind: code
---

## Why

Ruben decided on 11 October 2026 that thematiq's block in the personal settings goes away. A
person's own appearance (theme, font, background) is Nextcloud's, as the TqPersoonlijk board
shows. "House style of my groups" is not a personal choice: a group subadmin decides it for every
member of the group. So it moves into the administration settings, as a delegated setting that an
administrator grants to the group admins who need it. Nothing depends on launchpad.

## What changes

- **`OCA\Thematiq\Settings\GroupHouseStyle`**, an `IDelegatedSettings` in the Theming section of
  the administration settings, beside thematiq's main page. It appears under Administration
  privileges as "House style of my groups", so an administrator can grant it to a group. It shows
  only to someone who is subadmin of at least one delegated group.
- **`OCA\Thematiq\Settings\Personal` is removed**, with its `<personal>` entry in info.xml. The
  personal Appearance and accessibility page keeps only Nextcloud's own settings.
- **`MyGroupsController`** endpoints need the delegated setting
  (`#[AuthorizedAdminSetting(settings: GroupHouseStyle::class)]`) instead of only a session. The
  subadmin and allowed-list checks stay on every call.
- The script and its test move from `personal-group-house-style` to `admin-group-house-style`;
  the template from `settings/personal` to `settings/group-house-style`.
- `docs/features/group-house-style.md` explains the grant.

**For operators:** a subadmin who used the block under personal settings needs the grant once,
under Administration privileges, before they can choose again.

## Capabilities

### Modified capabilities
- `per-group-theming`: the subadmin's choice lives in the administration settings, behind a delegated setting.

## Impact
- `lib/Settings/GroupHouseStyle.php` (new), `lib/Settings/Personal.php` (removed),
  `lib/Controller/MyGroupsController.php`, `appinfo/info.xml`, `templates/settings/group-house-style.php`
  (moved), `js/admin-group-house-style.js` (moved), `docs/features/group-house-style.md`.
