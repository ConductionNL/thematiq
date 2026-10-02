---
status: done
reviewed_date: 2026-02-28
enriched_date: 2026-03-20
---

# Prometheus Metrics Endpoint

## Purpose
Expose application metrics in Prometheus text exposition format at `GET /api/metrics` for monitoring, alerting, and operational dashboards.

The app is a CSS-only theming layer with no database tables, so metrics focus on configuration state (active token set, custom overrides count, theming sync operations) and standard application health signals. The metric names keep their `nldesign_` prefix from before the app was renamed to thematiq.

## Requirements
### Requirement: Metrics Endpoint

The app MUST expose a Prometheus-compatible metrics endpoint that returns all app metrics in the
standard text exposition format, reachable only by an authenticated Nextcloud admin session (per
hydra ADR-006: `GET /api/metrics` is "Prometheus text, admin auth"). `MetricsController::index()`
MUST carry neither `#[PublicPage]` nor `#[NoAdminRequired]`, so Nextcloud's `SecurityMiddleware`
default (admin-only) applies. The endpoint MAY remain `#[NoCSRFRequired]` (or
`@NoCSRFRequired`) so an authenticated scraper is not blocked by CSRF token requirements — CSRF
exemption and authentication are independent properties, and this requirement narrows the
previous "publicly accessible without CSRF" wording, which incorrectly conflated the two.

#### Scenario: Metrics endpoint rejects unauthenticated requests

- GIVEN an anonymous (non-admin-authenticated) caller requests `GET /index.php/apps/thematiq/api/metrics`
- WHEN the request reaches `MetricsController::index()`
- THEN Nextcloud's `SecurityMiddleware` MUST reject the request (no session / non-admin session)
  because the method carries neither `#[PublicPage]` nor `#[NoAdminRequired]`
- AND the response MUST NOT contain `nldesign_info`, token-set counts, override counts, or any
  other metric value

#### Scenario: Metrics endpoint serves an authenticated admin without a CSRF token

- GIVEN an authenticated admin session (or an admin app-password via HTTP Basic, as configured for
  a Prometheus scrape target)
- WHEN `GET /index.php/apps/thematiq/api/metrics` is called without a CSRF token
- THEN the request MUST succeed (CSRF exemption still applies for admin-authenticated callers)
- AND the response MUST have content type `text/plain; version=0.0.4; charset=utf-8`

#### Scenario: Metrics endpoint returns all metric families for an authenticated admin

- GIVEN an authenticated admin session
- WHEN the metrics endpoint is called
- THEN it MUST contain HELP and TYPE lines for each metric family exactly as before this change
  (info, up, token sets total, active token set, custom overrides total, theming syncs total)
- AND each metric family MUST have at least one sample line

#### Scenario: Route registration is unchanged

- GIVEN the app's routes configuration
- WHEN routes are loaded from `appinfo/routes.php`
- THEN a GET route for `/api/metrics` MUST still be mapped to `metrics#index` (only the
  controller method's auth attributes change, not the route)

### Requirement: Application Info Metric
The app MUST expose an info gauge with version labels for identification.

#### Scenario: Info gauge with version labels
- GIVEN the metrics endpoint is called
- WHEN the info metric is generated
- THEN `nldesign_info` MUST be a gauge with value `1`
- AND it MUST have labels: `version` (app version from IConfig `installed_version`), `php_version` (PHP_VERSION), `nextcloud_version` (from system config `version`)

#### Scenario: Info gauge format
- GIVEN the app version is "1.2.3", PHP is "8.2.0", Nextcloud is "29.0.0"
- WHEN the info metric is output
- THEN the line MUST be: `nldesign_info{version="1.2.3",php_version="8.2.0",nextcloud_version="29.0.0"} 1`
- AND it MUST be preceded by `# HELP nldesign_info Application information`
- AND `# TYPE nldesign_info gauge`

#### Scenario: Versions read from correct sources
- GIVEN the metrics controller is initialized
- WHEN version values are collected
- THEN the app version MUST come from `IConfig::getAppValue('thematiq', 'installed_version', '0.0.0')`
- AND the PHP version MUST come from the `PHP_VERSION` constant
- AND the Nextcloud version MUST come from `IConfig::getSystemValueString('version', '0.0.0')`

### Requirement: Application Up Gauge
The app MUST expose an up gauge indicating overall application health.

#### Scenario: App is healthy
- GIVEN the metrics endpoint responds successfully
- WHEN the up metric is generated
- THEN `nldesign_up` MUST be a gauge with value `1`
- AND it MUST be preceded by HELP and TYPE lines

#### Scenario: Up gauge always 1 when endpoint responds
- GIVEN the metrics controller is operational
- WHEN the endpoint is called
- THEN `nldesign_up 1` MUST always be present
- AND if the endpoint itself fails to respond, Prometheus will treat the target as down

#### Scenario: Up gauge format
- GIVEN the metrics are generated
- THEN the output MUST include:
  - `# HELP nldesign_up Whether the application is up`
  - `# TYPE nldesign_up gauge`
  - `nldesign_up 1`

### Requirement: Token Sets Total Metric
The app MUST expose the total number of available token sets as a gauge.

#### Scenario: Token sets counted from filesystem
- GIVEN `css/tokens/` holds N CSS files (52 shipped sets in October 2026, plus any uploaded custom set)
- WHEN the token set metric is collected via `TokenSetService::getAvailableTokenSets()`
- THEN `nldesign_token_sets_total` MUST be a gauge with value N
- AND N MUST equal the number of entries in the public catalogue (`GET /api/token-sets`), which is built from the same scan

#### Scenario: Token set metric with HELP and TYPE
- GIVEN the metrics are generated
- THEN the output MUST include:
  - `# HELP nldesign_token_sets_total Total number of available token sets`
  - `# TYPE nldesign_token_sets_total gauge`
  - `nldesign_token_sets_total N`, where N is the count from the scenario above

#### Scenario: Token set count error handled gracefully
@e2e exclude a failing TokenSetService cannot be induced from a browser; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testTokenSetFailureFallsBackToZeroAndLogs asserts the 0 fallback, the warning and the intact response
- GIVEN `TokenSetService::getAvailableTokenSets()` throws an exception
- WHEN the metrics are collected
- THEN `nldesign_token_sets_total` MUST be reported as `0`
- AND a warning MUST be logged via the logger with the exception message
- AND the metrics endpoint MUST NOT fail entirely

### Requirement: Active Token Set Metric
The app MUST expose which token set is currently active as a labeled gauge.

#### Scenario: Active token set reported
- GIVEN the active token set is "amsterdam"
- WHEN the active set metric is collected
- THEN `nldesign_active_token_set{name="amsterdam"}` MUST be a gauge with value `1`

#### Scenario: Active token set with HELP and TYPE
- GIVEN the metrics are generated
- THEN the output MUST include:
  - `# HELP nldesign_active_token_set Currently active token set`
  - `# TYPE nldesign_active_token_set gauge`

#### Scenario: Default token set reported when not configured
@e2e exclude no endpoint unsets the token_set app value and the CI seed sets it; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testActiveTokenSetDefaultWhenUnset asserts the default and the gauge line
- GIVEN no token set has been explicitly configured
- WHEN the metric is collected from `IConfig::getAppValue('thematiq', 'token_set', 'rijkshuisstijl')`
- THEN `nldesign_active_token_set{name="rijkshuisstijl"}` MUST have value `1`

#### Scenario: Active token set in error recovery
@e2e exclude a failing TokenSetService cannot be induced from a browser; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testTokenSetFailureOmitsTheActiveTokenSetGauge asserts the gauge is omitted
- GIVEN the token set metrics collection fails
- WHEN the error is caught
- THEN the active token set metric MUST be omitted (it is inside the try block)
- AND only `nldesign_token_sets_total 0` MUST be reported as fallback

### Requirement: Custom Overrides Total Metric
The app MUST expose the number of admin-defined custom CSS overrides as a gauge.

#### Scenario: Custom overrides counted
- GIVEN the admin has defined 5 custom CSS overrides in `custom-overrides.css`
- WHEN the override metric is collected via `CustomOverridesService::read()`
- THEN `nldesign_custom_overrides_total` MUST be a gauge with value `5`

#### Scenario: No custom overrides
- GIVEN no custom overrides have been defined
- WHEN the metric is collected
- THEN `nldesign_custom_overrides_total` MUST be `0`

#### Scenario: Custom overrides with HELP and TYPE
- GIVEN the metrics are generated
- THEN the output MUST include:
  - `# HELP nldesign_custom_overrides_total Total custom CSS overrides`
  - `# TYPE nldesign_custom_overrides_total gauge`

#### Scenario: Override count error handled gracefully
@e2e exclude a failing CustomOverridesService cannot be induced from a browser; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testOverrideFailureFallsBackToZeroAndLogs asserts the 0 fallback, the warning and the intact response
- GIVEN `CustomOverridesService::read()` throws an exception
- WHEN the metrics are collected
- THEN `nldesign_custom_overrides_total` MUST be reported as `0`
- AND a warning MUST be logged
- AND the metrics endpoint MUST NOT fail entirely

### Requirement: Theming Syncs Counter
The app MUST expose the total number of theming sync operations as a counter.

#### Scenario: Theming syncs counter reported
- GIVEN the admin has performed 3 theming sync operations
- AND `IConfig::getAppValue('thematiq', 'theming_syncs_total', '0')` returns `'3'`
- WHEN the sync metric is collected
- THEN `nldesign_theming_syncs_total` MUST be a counter with value `3`

#### Scenario: No theming syncs performed
@e2e exclude a fresh-install state: the counter only goes up, so a browser cannot return it to unset; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testThemingSyncsCounterDefaultsToZeroAndIsCastToInt asserts 0 when unset
- GIVEN no theming sync has been performed
- WHEN the metric is collected
- THEN `nldesign_theming_syncs_total` MUST be `0`

#### Scenario: Theming syncs with HELP and TYPE
- GIVEN the metrics are generated
- THEN the output MUST include:
  - `# HELP nldesign_theming_syncs_total Total theming sync operations`
  - `# TYPE nldesign_theming_syncs_total counter`

#### Scenario: Syncs counter is read from IConfig
@e2e exclude the cast and the default are PHP-level reads; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testThemingSyncsCounterDefaultsToZeroAndIsCastToInt asserts the '0' default and the string '3' rendered as 3
- GIVEN the syncs counter is stored in IConfig
- WHEN the value is read
- THEN it MUST be cast to integer via `(int)` to handle string storage
- AND if the value is not set, the default MUST be `'0'`

### Requirement: Error Resilience
The metrics endpoint MUST be resilient to individual metric collection failures without failing the entire response.

#### Scenario: Token set metrics fail, other metrics succeed
@e2e exclude a failing TokenSetService cannot be induced from a browser; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testTokenSetFailureFallsBackToZeroAndLogs
- GIVEN the token set service throws an exception
- WHEN the metrics are collected
- THEN info, up, custom overrides, and theming syncs metrics MUST still be present
- AND token set metrics MUST fall back to 0
- AND a warning MUST be logged

#### Scenario: Custom overrides fail, other metrics succeed
@e2e exclude a failing CustomOverridesService cannot be induced from a browser; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testOverrideFailureFallsBackToZeroAndLogs
- GIVEN the custom overrides service throws an exception
- WHEN the metrics are collected
- THEN info, up, token sets, and theming syncs metrics MUST still be present
- AND custom overrides MUST fall back to 0
- AND a warning MUST be logged

#### Scenario: Multiple failures handled independently
@e2e exclude two failing services cannot be induced from a browser; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testBothFailuresAreHandledIndependently
- GIVEN both token set and custom overrides services throw exceptions
- WHEN the metrics are collected
- THEN info, up, and theming syncs MUST still be present
- AND both failing metrics MUST fall back to 0
- AND both warnings MUST be logged independently

### Requirement: Health Check Endpoint
The app MUST expose a public health check endpoint at `GET /api/health` for monitoring and load balancers. `OCA\Thematiq\Controller\HealthController` (route `health#index`) MUST run the checks through the OpenRegister AppHost observability engine (ADR-040) by composition: it resolves the engine's `ManifestLoader` and `HealthCheckExecutor` from the container by class-name string at dispatch time, and never extends or imports an OpenRegister class. The checks MUST be declared in `src/manifest.json` using only the OpenRegister-independent primitives (`database`, `filesystem`, `appEnabled`), never `orAvailable`, because thematiq has no OpenRegister dependency. The `appEnabled` check MUST name the app's own id, `thematiq`.

#### Scenario: Health check returns the canonical envelope
- GIVEN the app configuration is accessible and the database and filesystem are healthy
- WHEN `GET /index.php/apps/thematiq/api/health` is called
- THEN the response MUST be JSON with the ADR-006 envelope `{"status", "app", "version", "checks"}`
- AND `status` MUST be `"ok"` with `checks.database`, `checks.filesystem`, and `checks.thematiq` all `"ok"`
- AND the keys of `checks` MUST be exactly the check ids declared in `src/manifest.json`

#### Scenario: Critical check failure yields 503 under adr006 policy
@e2e exclude a failing database or appEnabled check cannot be induced from a browser; PHPUnit tests/Unit/Controller/HealthControllerEngineResultTest.php::testCriticalEngineFailureIsServedAs503 asserts the 503 envelope, and openregister tests/Unit/AppHost/HealthCheckExecutorTest.php::testCriticalFailureUnderAdr006Yields503 asserts the engine policy
- GIVEN a `severity: "critical"` check (database or appEnabled) fails
- WHEN the health endpoint is called
- THEN the response MUST be HTTP 503 with `status: "error"` and the failing check value starting with `failed`

#### Scenario: Degraded filesystem check does not error the overall status
@e2e exclude a failing filesystem check cannot be induced from a browser; PHPUnit tests/Unit/Controller/HealthControllerEngineResultTest.php::testDegradedFilesystemIsServedAs200Degraded asserts the 200 degraded envelope
- GIVEN the `filesystem` check (`severity: "degraded"`) fails while critical checks pass
- WHEN the health endpoint is called
- THEN the response MUST be HTTP 200 with `status: "degraded"` and `checks.filesystem` starting with `failed`

#### Scenario: Health endpoint is publicly accessible without CSRF
- GIVEN a monitoring system calls the health endpoint
- WHEN the request is made
- THEN the `#[PublicPage]` + `#[NoCSRFRequired]` attributes on `HealthController::index()` MUST allow access without a session or CSRF token

#### Scenario: Nextcloud boots when OpenRegister is absent
@e2e exclude the CI E2E instance always installs OpenRegister, so a browser there cannot reach this state; PHPUnit tests/Unit/Controller/HealthControllerEngineResultTest.php::testControllerNamesNoOpenRegisterClassInCode asserts the boot half and tests/Unit/Controller/HealthControllerEngineResultTest.php::testEngineAbsentDegradesTo200 asserts the response half
- GIVEN OpenRegister is disabled or not installed
- WHEN Nextcloud boots and `Application::register()` runs
- THEN no OpenRegister class MUST be loaded: `HealthController` extends only `OCP\AppFramework\Controller` and names the engine classes as strings, so thematiq still loads and themes
- AND `/api/health` MUST answer HTTP 200 with `status: "degraded"` and `checks.openregister: "unavailable"`

#### Scenario: Route registration
- GIVEN the app's routes configuration
- WHEN routes are loaded from `appinfo/routes.php`
- THEN a GET route for `/api/health` MUST be mapped to `health#index`

### Requirement: Prometheus Format Compliance
All metrics MUST strictly comply with the Prometheus text exposition format specification.

#### Scenario: HELP line format
- GIVEN any metric family
- WHEN the HELP line is output
- THEN it MUST follow the format: `# HELP <metric_name> <docstring>`
- AND each metric MUST have exactly one HELP line

#### Scenario: TYPE line format
- GIVEN any metric family
- WHEN the TYPE line is output
- THEN it MUST follow the format: `# TYPE <metric_name> <type>`
- AND type MUST be one of: `counter`, `gauge`, `histogram`, `summary`, `untyped`
- AND each metric MUST have exactly one TYPE line

#### Scenario: Label values properly escaped
- GIVEN the label values the endpoint emits: the active token set id and three version strings
- WHEN they are output
- THEN they are written unescaped, so none of them MAY contain a double quote, a backslash or a newline
- AND the token set id cannot: `TokenSetService::isValidTokenSet()` only accepts the basename of a file in `css/tokens/`, and uploaded custom set ids are slugged to `[a-z0-9-]`

### Requirement: Controller Dependencies
The MetricsController MUST receive all required dependencies via constructor injection.

#### Scenario: Dependencies injected
@e2e exclude constructor shape is a PHP property; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testDependenciesArePrivateReadonlyPromotedParameters
- GIVEN the MetricsController is constructed
- THEN it MUST receive: `IConfig` (for reading config values), `TokenSetService` (for counting token sets), `CustomOverridesService` (for counting overrides), `LoggerInterface` (for error logging)
- AND all dependencies MUST be declared as `private readonly` promoted constructor parameters

#### Scenario: No direct service instantiation
@e2e exclude a source invariant; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testSourceHasNoDirectServiceInstantiation asserts no `new ...Service`, `\OC::$server` or `Server::get(` in the controller
- GIVEN the MetricsController processes a request
- WHEN metrics are collected
- THEN it MUST use the injected services
- AND it MUST NOT use `new TokenSetService()` or similar direct instantiation

#### Scenario: Health controller is engine-owned
- GIVEN the health endpoint is dispatched with OpenRegister installed
- THEN the checks MUST be executed by the AppHost `HealthCheckExecutor` from the `observability.health` block of `src/manifest.json`, so the keys of `checks` are exactly the declared check ids
- AND the status and HTTP code MUST be the ones the engine's `statusCodePolicy` resolved
- AND thematiq owns only the `{status, app, version, checks}` envelope and the OpenRegister-absent fallback in `OCA\Thematiq\Controller\HealthController`; it MUST NOT hand-roll the checks

### Requirement: Audit Entries Counter Metric

The metrics endpoint MUST expose `nldesign_audit_entries_total` as a Prometheus counter of all
theming audit entries ever written. The value MUST be sourced from the monotonic IConfig app
value `audit_entries_total`, which `ThemingAuditService::log()` increments on every successful
append (same storage pattern as `theming_syncs_total`) — NOT from counting lines in the audit
file, so log rotation can never make the counter decrease. The metric MUST be emitted with HELP
and TYPE lines and default to `0` when the app value is unset, and it inherits the endpoint's
existing admin-auth posture and error-resilience requirements unchanged.

#### Scenario: Counter format

- GIVEN 12 audit entries have been written since installation
- WHEN an authenticated admin scrapes the metrics endpoint
- THEN the output MUST include:
  - `# HELP nldesign_audit_entries_total Total theming audit entries written`
  - `# TYPE nldesign_audit_entries_total counter`
  - `nldesign_audit_entries_total 12`

#### Scenario: Counter survives log rotation
@e2e exclude rotation needs a 1 MB audit file; PHPUnit tests/Unit/Service/ThemingAuditServiceTest.php::testCounterKeepsCountingAcrossRotation asserts the counter keeps counting while the fresh audit.jsonl is empty

- GIVEN the audit file has rotated and the current `audit.jsonl` holds fewer lines than the
  lifetime total
- WHEN the metric is collected
- THEN the value MUST equal the lifetime total from the `audit_entries_total` app value and MUST
  NOT decrease

#### Scenario: Counter defaults to zero
@e2e exclude a fresh-install state the counter cannot return to; PHPUnit tests/Unit/Controller/MetricsControllerTest.php::testAuditCounterDefaultsToZero

- GIVEN a fresh installation where no audit entry has been written
- WHEN the metric is collected from `IConfig::getAppValue('thematiq', 'audit_entries_total', '0')`
- THEN `nldesign_audit_entries_total 0` MUST be emitted (cast to int from string storage)

## Current Implementation Status

**Fully implemented:**
- MetricsController at `lib/Controller/MetricsController.php` carries neither `#[PublicPage]` nor
  `#[NoAdminRequired]`, so the Nextcloud `SecurityMiddleware` admin-only default applies
  (ADR-006); `@NoCSRFRequired` remains so a Prometheus scraper authenticating as an admin (e.g.
  via an app password) is not also required to present a CSRF token
- Info gauge: `nldesign_info` with version, php_version, nextcloud_version labels
- Up gauge: `nldesign_up` always 1
- Token sets total: `nldesign_token_sets_total` via `TokenSetService::getAvailableTokenSets()` with try/catch fallback to 0
- Active token set: `nldesign_active_token_set{name="..."}` from IConfig with default "rijkshuisstijl"
- Custom overrides total: `nldesign_custom_overrides_total` via `CustomOverridesService::read()` with try/catch fallback to 0
- Theming syncs counter: `nldesign_theming_syncs_total` from IConfig with cast to int
- Content-Type header: `text/plain; version=0.0.4; charset=utf-8`
- Error resilience: independent try/catch blocks for token set and override metrics
- Warning logging on metric collection failures
- HealthController at `lib/Controller/HealthController.php` extends only `OCP\AppFramework\Controller` and drives the OpenRegister AppHost engine by composition (ADR-040): it resolves `ManifestLoader` and `HealthCheckExecutor` by class-name string at dispatch time and carries `#[PublicPage]` + `#[NoCSRFRequired]` itself. Without OpenRegister it answers 200 `degraded` with `checks.openregister: unavailable`
- Health checks are declarative in `src/manifest.json` (`observability.health`): `database` (critical), `filesystem` (degraded), `appEnabled: thematiq` (critical), `adr006` status-code policy. OR-independent primitives only, no `orAvailable`, no OR-object metrics
- Health response envelope: ADR-006 `{status, app, version, checks}`, rendered by HealthController from the engine's result
- Routes: `/api/metrics` -> `metrics#index`, `/api/health` -> `health#index`
- Constructor injection of IConfig, TokenSetService, CustomOverridesService, LoggerInterface (promoted parameters with `private readonly`)

**Not yet implemented:**
- All requirements in this spec are fully implemented.
- Note: `nldesign_requests_total` and `nldesign_request_duration_seconds` (mentioned in original spec) are NOT implemented -- these require request-level instrumentation middleware which is not present. The implemented metrics focus on configuration state which is appropriate for a CSS-only theming app.

## Standards & References
- Prometheus text exposition format: https://prometheus.io/docs/instrumenting/exposition_formats/
- OpenMetrics specification: https://openmetrics.io/
- Nextcloud server monitoring patterns
- OpenRegister MetricsService and HeartbeatController as reference implementation
