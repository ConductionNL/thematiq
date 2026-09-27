# Design: delegated group house style

## Where it fits (development b4e7568)

- `lib/Service/GroupThemingService.php` keeps the ordered mapping in app config `group_token_sets` (`:66`) with a generation counter (`:73`) that invalidates the resolution cache; `setMapping()` (`:172`) validates every entry before writing (`:177-181`), `validateEntry()` (`:189-200`) returns `{group, tokenSet}`.
- `openspec/specs/per-group-theming/spec.md:43-60`: resolution order is admin preview, first matching group entry, instance default; sessionless pages always get the default (`:56`).
- `lib/Controller/SettingsController.php:906-908` `getGroupTheming()` and `:933-935` `setGroupTheming()`, both `#[AuthorizedAdminSetting(Admin::class)]` as the spec requires (`openspec/specs/per-group-theming/spec.md:167-175`).
- `templates/settings/admin.php:338-360` renders the group theming rows; `js/admin.js` builds them.
- `lib/Settings/Admin.php` implements `IDelegatedSettings`, so the admin section can already be delegated to a group by Nextcloud's admin delegation. That delegates the whole theming section, not one group's choice, which is why it does not answer this row.
- `lib/Service/ThemingAuditService.php` is the single audit write path.

## Decisions

### 1. Delegation is a property of a mapping entry

An entry becomes `{group, tokenSet, delegated: bool, allowedTokenSets: string[]}`. Only a group that has an entry can be delegated, so the administrator decides its priority in the ordered list, and the precedence rules stay unchanged. `allowedTokenSets` must be non-empty, contain the entry's current set, and name only available sets.

### 2. The subadmin is the group's owner

Nextcloud already has an owner role per group: the group subadmin (`OCP\Group\ISubAdmin::isSubAdminOfGroup()`). A subadmin of a delegated group may change that entry's `tokenSet`, and nothing else: not the priority, not the allowed list, not another group.

Rejected: Nextcloud Teams (circles) owners. Teams do not drive the group mapping, and a second ownership model would need its own resolution.

### 3. A personal settings section, guarded per group

Subadmins do not see admin settings, so the choice lives in a personal section "House style of my groups", shown only when the user is subadmin of at least one delegated group. Its endpoints are `#[NoAdminRequired]` and every call checks `isSubAdminOfGroup()` for the group in the request and the allowed list for the set, answering 403 otherwise. That per-object check is what keeps a `#[NoAdminRequired]` endpoint from being an IDOR.

### 4. Locking back keeps the current set

Turning `delegated` off leaves `tokenSet` as the subadmin set it, so locking never flips a group's look by surprise. The administrator changes it afterwards if needed.

### 5. Audited like any change

A delegated choice writes `token_set_changed` with actor the subadmin's uid and `{group}` in context, and bumps the generation counter so the group sees the change on its next page load.

## Risks

- A subadmin picks a set that later fails contrast. The warn-only contrast policy applies as for administrators; the section shows the contrast result of each allowed set.
- Deleting an allowed set: validation on read drops it from the allowed list, and resolution already skips entries whose set is gone (`per-group-theming` rule 2).

## Out of scope

- Delegating uploads of new sets to subadmins.
- Delegation for sessionless pages, which stay on the instance default by `per-group-theming` (`:56`).
