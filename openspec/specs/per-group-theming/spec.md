# per-group-theming Specification

## Purpose
TBD - created by archiving change per-group-theming. Update Purpose after archive.
## Requirements
### Requirement: Group-to-Token-Set Mapping Storage

The app MUST store the group theming mapping in the `nldesign` appconfig key
`group_token_sets` as a JSON array of `{"group": string, "tokenSet": string}` entries, where
array order IS the priority order (index 0 = highest). On save, every `group` MUST be validated
to exist via `IGroupManager`, every `tokenSet` MUST be an available token-set id (shipped
`token-sets.json` or the `custom_token_sets` manifest), and at most one entry per group is
allowed; violations MUST be rejected with HTTP 422 naming the offending entry, persisting
nothing. A companion appconfig integer `group_token_sets_generation` MUST be incremented on
every successful mapping write. An absent or empty mapping MUST reproduce today's behavior
exactly (instance default set for everyone, no group lookups performed).

#### Scenario: Valid mapping is stored in priority order

@e2e exclude persistence branch — PHPUnit on the service
- GIVEN groups `gemeente-a` and `gemeente-b` exist and token sets `amsterdam` and `utrecht`
  are available
- WHEN the admin saves the mapping `[{gemeente-a → amsterdam}, {gemeente-b → utrecht}]`
- THEN `group_token_sets` MUST contain exactly those entries in that order
- AND `group_token_sets_generation` MUST have been incremented

#### Scenario: Unknown group or set is rejected without partial writes

@e2e exclude validation branch — PHPUnit on the service
- GIVEN a mapping payload containing an entry for a group id that does not exist in
  `IGroupManager` (or a token set id that is not available)
- WHEN the admin saves it
- THEN the save MUST be rejected with HTTP 422 identifying the offending entry and reason
- AND the previously stored mapping and generation counter MUST be unchanged

#### Scenario: Duplicate group entries are rejected

@e2e exclude validation branch — PHPUnit on the service
- GIVEN a payload with two entries for group `gemeente-a`
- WHEN the admin saves it
- THEN the save MUST be rejected with HTTP 422 stating one entry per group is allowed

### Requirement: Deterministic Resolution Precedence

For every rendered request the app MUST resolve the effective token set deterministically, in
this order of authority:

1. an active admin theme preview for the requesting session (change `theme-preview-workflow`)
   MUST win over any group mapping for that session;
2. for a logged-in user, the FIRST entry of `group_token_sets` (in stored order) whose group
   contains the user — a user in multiple mapped groups gets the earliest entry (explicit
   tie-break rule); entries whose token set no longer exists MUST be skipped and resolution
   MUST continue with later entries;
3. otherwise the instance default `token_set` app value.

Anonymous, public-share, login, and error pages (no user session) MUST always resolve to the
instance default set via an explicit no-session branch. Any resolution failure (group backend
error, cache error, malformed stored mapping) MUST fall back to the instance default set
without throwing — presentation, never security. Per-group theming applies ONLY to which
token-set CSS stack is injected; it MUST NOT alter the injection order, the custom-overrides
layer, or the conditional stylesheets.

#### Scenario: User in a mapped group gets the mapped set

- GIVEN the mapping `[{gemeente-a → amsterdam}]` and user `anna` in group `gemeente-a`
- WHEN `anna` loads any themed page
- THEN the injected token-set stylesheet MUST be `tokens/amsterdam`
- AND the design-system stack loaded MUST be the one `amsterdam` declares

#### Scenario: Multi-group user gets the highest-priority entry

@e2e exclude precedence matrix — PHPUnit on the service
- GIVEN the mapping `[{gemeente-a → amsterdam}, {gemeente-b → utrecht}]`
- AND user `bob` is a member of BOTH `gemeente-a` and `gemeente-b`
- WHEN `bob`'s request resolves
- THEN the resolved set MUST be `amsterdam` (earliest matching entry)

#### Scenario: Mapping to a deleted set is skipped, not fatal

@e2e exclude precedence matrix — PHPUnit on the service
- GIVEN the mapping `[{gemeente-a → custom-gone}, {gemeente-a-fallbackgroup → utrecht}]` where
  `custom-gone` has been deleted, and a user in both groups
- WHEN the request resolves
- THEN entry 1 MUST be skipped and the resolved set MUST be `utrecht`
- AND a user matching only the dead entry MUST resolve to the instance default set

#### Scenario: Unmapped user and anonymous pages get the instance default

- GIVEN a non-empty mapping and user `carol` in no mapped group
- WHEN `carol` loads a page, and separately the login page is rendered with no session
- THEN both requests MUST inject the instance default `token_set` stack
- AND the no-session branch MUST NOT perform any group lookup

#### Scenario: Resolution failure fails open to the default set

@e2e exclude failure injection — PHPUnit with a throwing group-manager double
- GIVEN the group backend throws during membership resolution
- WHEN a themed page renders
- THEN the instance default set MUST be injected
- AND no exception MUST escape `Application::boot()`

### Requirement: Core Theming Remains Instance-Global

Per-group theming MUST cover the token-set CSS layer ONLY. Nextcloud core theming values —
`ThemingDefaults` primary color, logo, background images, and everything the theming-sync
feature pushes — remain instance-global: theming-sync MUST continue to operate on the instance
default set only and MUST NOT be offered for group-mapped sets. The admin UI section MUST state
this limitation in its hint text (logo and mail branding follow the instance default, not the
group set).

#### Scenario: Theming-sync is not triggered by group mappings

@e2e exclude service boundary — PHPUnit asserts no ThemingService interaction
- GIVEN a mapping assigning `amsterdam` to a group while the instance default set is
  `rijkshuisstijl`
- WHEN the mapping is saved and group users load pages
- THEN no `ThemingService` sync MUST be invoked with `amsterdam` values
- AND Nextcloud core theming MUST continue to reflect the instance default set

#### Scenario: Limitation is stated in the admin UI

- GIVEN the admin opens the group theming section
- WHEN the section renders
- THEN a localized hint MUST state that logo, mail templates, and Nextcloud core branding
  follow the instance default set and are not per-group

### Requirement: Per-App Exclusions Stay Orthogonal

The per-app theming exclusion (`per-app-theming` spec, `disabled_apps`) MUST keep suppressing
ALL nldesign style injection before any token-set resolution: for an excluded app's pages no
stack is injected for any user, mapped or not. For non-excluded apps, both features apply —
exclusion decides WHETHER injection happens, group mapping decides WHICH set is injected.

#### Scenario: Excluded app is unthemed for a group-mapped user

@e2e exclude combination matrix — PHPUnit on the boot guard order
- GIVEN app `files` is in `disabled_apps` and user `anna` is group-mapped to `amsterdam`
- WHEN `anna` opens a `files` page and then a non-excluded app's page
- THEN the `files` page MUST receive no nldesign CSS at all
- AND the other page MUST receive the full `amsterdam` stack

### Requirement: O(1)-ish Cached Resolution

Per-request resolution MUST NOT perform a group-membership lookup on every request: the
resolved set id MUST be cached per user via `ICacheFactory` under a key containing the user id
and the current `group_token_sets_generation`, with a bounded TTL (≤ 1 hour). A mapping write
MUST invalidate all cached resolutions in O(1) by bumping the generation (old keys become
unreachable; no cache enumeration). An empty mapping MUST short-circuit before any cache or
group access. Group-membership changes MUST take effect no later than TTL expiry (documented
staleness bound); mapping changes MUST take effect on the next request.

#### Scenario: Second request hits the cache

@e2e exclude caching internals — PHPUnit with a cache spy
- GIVEN user `anna` resolved to `amsterdam` on her previous request
- WHEN her next request resolves under the same mapping generation
- THEN the set MUST come from the cache
- AND `IGroupManager` MUST NOT be queried

#### Scenario: Saving the mapping takes effect immediately

- GIVEN cached resolutions exist for several users
- WHEN the admin saves a changed mapping (generation bump)
- THEN the very next request of any user MUST resolve against the new mapping
- AND stale cached entries from the old generation MUST never be served

### Requirement: Group Theming Admin Endpoints

`SettingsController` MUST expose `GET /settings/group-theming` (returns the ordered mapping,
the available groups `{id, displayName}` for the picker, and the available token sets) and
`POST /settings/group-theming` (replaces the full ordered mapping after validation). Both MUST
carry the `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)` posture of all
`/settings/*` endpoints — admin-only, CSRF-protected, no `#[PublicPage]`, no
`#[NoAdminRequired]`, no `#[NoCSRFRequired]`.

#### Scenario: Admin reads the current mapping

- GIVEN a stored mapping
- WHEN the admin GETs `/apps/nldesign/settings/group-theming`
- THEN the response MUST contain the entries in priority order plus the group and token-set
  option lists

#### Scenario: Non-admin cannot read or write mappings

@e2e exclude auth posture — PHPUnit/middleware, single-admin test env
- GIVEN a non-admin authenticated user
- WHEN they call either group-theming endpoint
- THEN SecurityMiddleware MUST reject the request and nothing MUST be persisted

### Requirement: An administrator delegates a group mapping

A group mapping entry MUST be able to carry `delegated` and `allowedTokenSets`. An administrator MUST be able to mark an entry as delegated on Settings > Administration > Theming and choose its allowed token sets. The allowed list MUST NOT be empty, MUST contain the entry's current set and MUST name only available sets. Entries stored before this change MUST read as not delegated. Delegation MUST NOT change the resolution order.

#### Scenario: An administrator delegates a group

@e2e exclude needs a group with a subadmin on the shared CI instance; proven by tests/vitest/admin-group-delegation.spec.js and tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testAdminEndpointsCarryTheDelegationFields

- GIVEN an administrator on Settings > Administration > Theming with a mapping for group `gemeente-a` to `rijkshuisstijl`
- WHEN they mark it as delegated with allowed sets `rijkshuisstijl` and `gemeente-a-huisstijl` and save
- THEN the mapping MUST show the row as delegated with both allowed sets
- AND members of `gemeente-a` MUST still see `rijkshuisstijl` until a choice is made

#### Scenario: Undelegated groups stay locked

@e2e exclude needs a subadmin account on the shared CI instance; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testRefusedChoicesChangeNothing and ::testPersonalSectionOnlyForSubadminsOfDelegatedGroups

- GIVEN a mapping for group `concern` that is not delegated
- WHEN a subadmin of `concern` opens their personal settings
- THEN they MUST NOT see a house style choice for `concern`

### Requirement: A group subadmin chooses the house style of a delegated group

A subadmin of a delegated group MUST be able to choose that group's token set from its allowed list in the personal settings section "House style of my groups". The section MUST appear only for users who are subadmin of at least one delegated group. The endpoints MUST be `#[NoAdminRequired]` and MUST check, on every call, that the user is subadmin of the named group and that the set is on its allowed list, answering 403 otherwise. A choice MUST bump the mapping generation so the group sees it on its next page load.

#### Scenario: A subadmin picks the house style of their municipality

@e2e exclude needs a subadmin account on the shared CI instance; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminOfTheGroupChoosesAnAllowedSet and tests/vitest/personal-group-house-style.spec.js

- GIVEN group `gemeente-a` delegated with allowed sets `rijkshuisstijl` and `gemeente-a-huisstijl`
- AND a user who is subadmin of `gemeente-a`
- WHEN the subadmin opens Settings > Personal > House style of my groups and chooses `gemeente-a-huisstijl`
- THEN a member of `gemeente-a` who opens the dashboard next MUST see the `gemeente-a-huisstijl` colours

#### Scenario: A subadmin cannot change another group

@e2e exclude API authorisation; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminEndpoints

- GIVEN a subadmin of `gemeente-a` only
- WHEN they call `POST /apps/thematiq/api/my-groups/gemeente-b/house-style`
- THEN the response MUST be 403
- AND the mapping of `gemeente-b` MUST be unchanged

#### Scenario: A set outside the allowed list is refused

@e2e exclude API authorisation; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminEndpoints and ::testRefusedChoicesChangeNothing

- GIVEN a subadmin of delegated group `gemeente-a` whose allowed list does not contain `amsterdam`
- WHEN they request `amsterdam` for `gemeente-a`
- THEN the response MUST be 403 and the mapping MUST be unchanged

### Requirement: Delegated choices are audited

Every delegated choice MUST write a `token_set_changed` audit entry with the subadmin's user id as actor, the old and new set, and the group in its context.

#### Scenario: The brand owner sees who changed a group's look

@e2e exclude audit entry content; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminOfTheGroupChoosesAnAllowedSet

- GIVEN a subadmin of `gemeente-a` changed the group's set
- WHEN an administrator opens the theming audit log
- THEN the entry MUST show the subadmin as user, the old and new set, and the group `gemeente-a`

### Requirement: Locking a group back keeps its current set

Turning delegation off MUST keep the entry's current token set and MUST remove the group from the subadmin's section.

#### Scenario: An administrator locks a group again

@e2e exclude needs a subadmin account on the shared CI instance; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testLockingBackKeepsTheSetAndHidesTheGroup

- GIVEN delegated group `gemeente-a` currently on `gemeente-a-huisstijl`
- WHEN an administrator turns delegation off for it
- THEN members of `gemeente-a` MUST keep seeing `gemeente-a-huisstijl`
- AND the subadmin's personal section MUST no longer offer `gemeente-a`
