# upstream-freshness Specification

## Purpose
TBD - created by archiving change upstream-token-freshness. Update Purpose after archive.
## Requirements
### Requirement: Daily Freshness Background Job

The app MUST provide a background job `OCA\Thematiq\BackgroundJob\UpstreamFreshnessJob`
extending `OCP\BackgroundJob\TimedJob`, registered via `appinfo/info.xml`, with a 24-hour
interval and `TIME_INSENSITIVE` sensitivity. The job compares the upstream
`nl-design-system/themes` revision against the `upstreamRef` provenance recorded in
installed `token-sets.json` entries and stores review notices for the admin panel. The job
MUST NOT auto-apply, download, or modify any token set: it MUST never write to
`css/tokens/`, never change the active `token_set` config, and never alter any theming
output. Sets without an `upstreamRef` field and all `custom-*` sets MUST be excluded from
comparison.

@e2e exclude background-job behaviour — a TimedJob's registration, interval, sensitivity and no-op-on-apply are not reachable from a browser; proven by tests/Unit/BackgroundJob/UpstreamFreshnessJobTest.php (testDeclaresDailyIntervalAndTimeInsensitive, testRunDelegatesToService, testServiceThrowDoesNotEscapeRun) and tests/Unit/Service/UpstreamFreshnessServiceTest.php (test200WithNewShaAndSuccessfulCompareProducesPerSetNotice, testSetsWithoutUpstreamRefAndCustomSetsAreExcluded).

#### Scenario: Job is registered and scheduled

- GIVEN the app is installed on a Nextcloud 34 instance
- WHEN `occ background-job:list` is inspected
- THEN `OCA\Thematiq\BackgroundJob\UpstreamFreshnessJob` MUST be listed
- AND the job MUST declare a 24-hour interval and time-insensitive execution

#### Scenario: Upstream change produces a per-set notice

- GIVEN the check is enabled and the installed `utrecht` entry records
  `upstreamRef: <shaA>` and `upstreamVersion: 1.2.0`
- AND upstream's pinned branch head is `<shaB>` with changes under `proprietary/utrecht/`
- WHEN the job runs
- THEN a notice MUST be stored for `utrecht` including the new upstream version (or short
  SHA when no version is resolvable) and the detection timestamp
- AND no notice MUST be stored for installed sets whose upstream paths did not change

#### Scenario: Job never applies anything

- GIVEN a stored notice for `utrecht`
- WHEN the job runs any number of times
- THEN files under `css/tokens/` MUST be unmodified (content and mtime)
- AND the active `token_set` app config MUST be unchanged
- AND the rendered CSS stack MUST be byte-identical before and after the run

### Requirement: Opt-In Egress With Disclosure

The freshness check introduces the app's first outbound network request and MUST default to
disabled (`upstream_freshness_enabled` default off). The admin panel MUST present an opt-in
toggle whose label discloses the contacted host and cadence (daily request to the pinned
manifest URL's host, default `api.github.com`). While disabled, the job MUST perform zero
network requests and zero freshness processing. The pinned manifest URL MUST be
overridable via app config (`upstream_manifest_url`) so egress-filtered deployments can
point at an internal mirror. The outbound request MUST NOT carry any instance-identifying
payload (no instance URL, no version report, no telemetry — only standard HTTP request
headers plus the cached ETag).

@e2e exclude egress and authorisation assertions — "zero network requests while disabled" and "non-admin is denied" are observations about outbound HTTP and route attributes, neither of which a browser DOM can witness; proven by tests/Unit/Service/UpstreamFreshnessServiceTest.php (testDisabledPerformsZeroHttpCalls) and tests/Unit/Controller/SettingsControllerUpstreamFreshnessTest.php (testAllThreeEndpointsCarryAuthorizedAdminSetting, testToggleEnabledPersists). The toggle's presence and disclosure label remain covered by the admin-settings e2e suite.

#### Scenario: Default is off with no egress

- GIVEN a fresh install with no admin action taken
- WHEN cron executes the job
- THEN no outbound HTTP request MUST be made
- AND no notice state MUST be created

#### Scenario: Admin enables the check knowingly

- GIVEN an admin opens the nldesign settings panel
- WHEN they read the "Upstream token updates" block
- THEN the toggle label MUST state that enabling performs a daily request to the upstream
  host (default `api.github.com`)
- AND enabling via `POST /settings/upstream-freshness` MUST persist the flag and take
  effect on the next job run without further configuration

#### Scenario: Non-admin access denied

- GIVEN a non-admin authenticated user
- WHEN they call any `/settings/upstream-freshness*` endpoint
- THEN the request MUST be rejected by the `AuthorizedAdminSetting` posture

### Requirement: Conditional Fetch With Bounded Requests

When enabled, a job run MUST make a single conditional HTTP GET to the pinned manifest URL,
sending the previously stored ETag as `If-None-Match` and caching the response ETag and
head revision via IAppConfig. A `304 Not Modified` response MUST end the run after updating
the last-checked timestamp. Only when the fetched head revision differs from installed
provenance MAY the job make one additional compare request to attribute changed upstream
paths to installed token sets; if attribution fails for any reason, the job MUST degrade to
a single generic "upstream has updates" notice keyed by the head revision. A job run MUST
never exceed two outbound requests, each bounded by a 10-second timeout, issued through
`OCP\Http\Client\IClientService` (honoring instance proxy configuration), without
authentication credentials.

@e2e exclude HTTP-layer assertions — conditional GET, If-None-Match/ETag round-tripping and the two-request-per-run cap are counted at the IClientService boundary, which no browser DOM observes; proven by tests/Unit/Service/UpstreamFreshnessServiceTest.php (test304OnlyUpdatesCheckedAtAndProducesNoNotices, test200WithUnchangedHeadShaProducesNoNotices, testNeverMoreThanTwoRequestsPerRun).

#### Scenario: Steady state is one cheap request

- GIVEN the check is enabled and upstream has not changed since the last run
- WHEN the job runs
- THEN exactly one HTTP request MUST be made, carrying `If-None-Match` with the stored
  ETag
- AND the response 304 MUST result in only the last-checked timestamp being updated

#### Scenario: Request cap holds even on change

- GIVEN upstream has changed and attribution is needed
- WHEN the job runs
- THEN at most two HTTP requests total MUST be made (freshness GET + one compare GET)
- AND a failed compare MUST NOT trigger retries within the run — the stored result is the
  generic notice

### Requirement: Silent Degradation On Failure

The job MUST be failure-inert: network errors, timeouts, non-2xx/304 statuses, malformed
response bodies, and state-write failures MUST all be caught inside the job, logged at no
higher than info level, and MUST leave previously stored notice state untouched. A job
failure MUST never throw out of `run()`, never break or delay page theming, the admin
panel, cron processing, or any other app function. Offline and air-gapped instances that
enable the toggle anyway MUST experience nothing worse than an info-level log line per run.

@e2e exclude failure-inertness — the assertion is that a network error, timeout or malformed body produces NO visible effect, which a browser test cannot distinguish from the feature simply being off; proven by tests/Unit/Service/UpstreamFreshnessServiceTest.php (testFreshnessRequestThrowLeavesPriorNoticesUntouched, testMalformedHeadShaBodyIsDiscarded, testCompareFailureProducesGenericNoticeWithoutException) and tests/Unit/BackgroundJob/UpstreamFreshnessJobTest.php (testServiceThrowDoesNotEscapeRun).

#### Scenario: Unreachable upstream is a non-event

- GIVEN the check is enabled and the manifest URL host is unreachable (DNS failure or
  timeout)
- WHEN the job runs
- THEN no exception MUST escape the job
- AND the log MUST contain at most an info-level entry
- AND existing notices and ETag state MUST be unchanged
- AND themed pages MUST render identically to before the run

#### Scenario: Malformed upstream response is discarded

- GIVEN the freshness request returns 200 with an unparseable body
- WHEN the job processes it
- THEN no notice MUST be created or modified from the malformed data
- AND the stored ETag MUST NOT be updated to a value that would mask the next valid check

### Requirement: Admin Notice Surface With Per-Version Dismissal

The nldesign admin settings panel MUST display stored freshness notices as "Token set
{name} has upstream update {version} — review & apply" (falling back to the short revision
when no semver is known), together with the last-checked timestamp. Each notice MUST offer
a Dismiss action (`POST /settings/upstream-freshness/dismiss`) recording a per-set
dismissal marker for that specific upstream version/revision. A dismissed notice MUST stay
hidden for that version but MUST re-surface when a newer upstream version/revision is
detected for the same set. The panel MUST NOT offer any one-click apply of upstream tokens
(the update path remains the reviewed sync-workflow release; the notice links the README
sync documentation).

#### Scenario: Notice appears and reads correctly

- GIVEN a stored notice for `utrecht` with upstream version `1.3.0`
- WHEN the admin opens the settings panel
- THEN the block MUST show "Token set Gemeente Utrecht has upstream update 1.3.0 — review
  & apply" with a Dismiss control and no apply control

#### Scenario: Dismissal is per version and re-surfaces on newer updates

- GIVEN the admin dismissed the `utrecht` notice for version `1.3.0`
- WHEN subsequent job runs detect no newer upstream change
- THEN the panel MUST NOT show the `utrecht` notice
- AND WHEN a later run detects upstream version `1.4.0` for `utrecht`
- THEN the notice MUST re-appear for `1.4.0`

### Requirement: The settings hint describes every outbound request truthfully

The upstream token updates hint MUST NOT claim that the freshness check is the only outbound request the app makes. It MUST name its own host and cadence, and the settings page MUST name every opt-in outbound request the app can make (the freshness check and the gallery), each with its host.

#### Scenario: An administrator reads which hosts the app can contact

@e2e exclude static copy on the settings page; proven by the template text (templates/settings/admin.php, Upstream token updates and Theme gallery blocks) and tests/vitest/admin-gallery.spec.js 'is off on a fresh install, names the host and lists nothing'

- GIVEN an administrator on Settings > Administration > Theming
- WHEN they read the Upstream token updates block and the Gallery block
- THEN each block MUST name the host it contacts and that it is off by default
- AND no text on the page MUST claim a single outbound request
