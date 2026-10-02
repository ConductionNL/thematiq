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

### Requirement: An app brand wins over the group mapping on that app's pages

On a page of an app that has a brand mapping, the effective token set MUST be the app's set, unless an admin theme preview is active for the session, which MUST still win. On every other page, resolution MUST follow the existing order (preview, group, instance default). Sessionless pages MUST keep resolving to the instance default.

#### Scenario: A branded app looks the same for every municipality

@e2e exclude needs a mapped group and the Collectives app on the shared CI instance; proven by tests/Unit/Service/AppBrandServiceTest.php::testBrandedAppLooksTheSameForEveryGroup

- GIVEN group `gemeente-a` mapped to `gemeente-a-huisstijl` and the Collectives app branded with `kennisbank`
- WHEN a member of `gemeente-a` opens Collectives
- THEN the page MUST render in `kennisbank`
- AND when they open Files, it MUST render in `gemeente-a-huisstijl`

#### Scenario: A preview still wins

@e2e exclude needs the Collectives app on the shared CI instance; proven by tests/Unit/Service/AppBrandServiceTest.php::testPreviewStillWins

- GIVEN an administrator with an active theme preview of `amsterdam`
- WHEN they open the branded Collectives app
- THEN the page MUST render in `amsterdam`
