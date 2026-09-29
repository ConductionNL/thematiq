# config-portability Specification

## Purpose
TBD - created by archiving change theme-config-portability. Update Purpose after archive.
## Requirements
### Requirement: Complete Configuration Bundle

The app MUST be able to serialize its COMPLETE configuration into a single JSON bundle with
envelope fields `format: "nldesign-config-bundle"`, `bundleVersion: 1`, `exportedAt` (ISO 8601),
and `app: {id, version}` (informational), containing ALL of: the active token set id
(`token_set`), the hide-slogan toggle, the show-menu-labels toggle, the per-app exclusion list
(`disabled_apps`), the full `custom-overrides.css` content, and every custom token set with its
metadata (id, name, description, theming) and inline CSS content. The bundle MUST NOT contain:
operational counters (`theming_syncs_total` and similar telemetry), `installed_version`
(NC-managed), per-user preview state (session-scoped, see change `theme-preview-workflow`), or
Nextcloud core `theming` app values (owned by the theming app; the theming-sync dialog is the
supported path to re-apply them after import), or the server's declared environment (the
`thematiq.environment` system value, see capability `environment-marker`: it is the one value
that must differ between OTAP environments, and it lives in `config.php`, not in app config). Every future instance-wide nldesign configuration
value MUST be added to the bundle in the same change that introduces the value, with a
`bundleVersion` bump — a configuration value that exists but is not exported is a spec
violation, not an accepted gap.

@e2e exclude JSON bundle serialisation — the assertions are about envelope fields and which of the six configuration parts a serialised bundle contains, including that telemetry and platform-owned values are absent; that is a document's shape, not a rendered page; proven by tests/Unit/Service/ConfigBundleServiceTest.php.

#### Scenario: Export captures all six configuration parts

- GIVEN an instance with token set `amsterdam`, hide-slogan on, menu-labels off, two excluded
  apps, three token overrides, and one custom token set `custom-gemeente-x`
- WHEN the bundle is exported
- THEN the JSON MUST contain `config.tokenSet = "amsterdam"`, `config.hideSlogan = true`,
  `config.showMenuLabels = false`, `config.disabledApps` with both app ids,
  `customOverridesCss` equal to the current `custom-overrides.css` content, and one
  `customTokenSets` entry with the set's metadata and full CSS

#### Scenario: Telemetry and platform-owned values are excluded

- GIVEN `theming_syncs_total` is `7` and Nextcloud's `theming` app has a primary color set
- WHEN the bundle is exported
- THEN the bundle MUST NOT contain the sync counter, `installed_version`, any `preview_*` user
  value, or any `theming` app value

#### Scenario: The declared environment is not exported

- GIVEN a server with `thematiq.environment` set to `test`
- WHEN an administrator exports the bundle with `occ nldesign:config:export`
- THEN the bundle MUST NOT contain the environment value or any key naming it

### Requirement: All-Or-Nothing Validated Import

Import MUST run in two phases via a single shared `ConfigBundleService` implementation. Phase 1
MUST validate every section using the EXISTING validators — the bundle envelope
(recognised `format` and `bundleVersion`), toggle types, the exclusion list shape, each custom
token set via `CustomTokenSetValidator::validateDeclarations()` and the custom-id namespace
check, the overrides CSS via the CSS parser and the editable-token whitelist, and the token set
id via `TokenSetService::isValidTokenSet()` OR membership of the bundle's own custom sets. Any
hard validation failure MUST abort the ENTIRE import with a per-section error listing and ZERO
writes — partial application is forbidden. Unknown-but-well-formed override variables are NOT
hard errors: they MUST be skipped and counted, matching the `token-import-export` semantics.
Phase 2 MUST apply all sections and report per-section results. Import MUST be idempotent:
applying the same bundle twice MUST yield identical configuration state, files included.

@e2e exclude all-or-nothing import semantics — "one invalid section blocks every section" and idempotent re-application are assertions that NOTHING changed, which a browser cannot distinguish from an import that silently did nothing; proven by tests/Unit/Service/ConfigBundleServiceTest.php.

#### Scenario: One invalid section blocks every section

- GIVEN a bundle whose custom token set contains a forbidden declaration value
- AND whose other sections are valid
- WHEN the bundle is imported
- THEN NO app value, NO overrides file write, and NO custom-set file write MUST occur
- AND the result MUST list the failing section and the specific validator error

#### Scenario: Unknown override tokens are skipped, not fatal

- GIVEN the bundle's `customOverridesCss` contains one editable token and one unknown variable
- WHEN the bundle is imported
- THEN the import MUST proceed, writing the editable token
- AND the per-section result MUST report the unknown variable as skipped

#### Scenario: Token set resolvable from within the bundle

- GIVEN a bundle with `config.tokenSet = "custom-gemeente-x"` and a `customTokenSets` entry with
  that id
- WHEN the bundle is imported on an instance that has never seen that custom set
- THEN validation MUST pass (the id resolves against the bundle's own custom sets)
- AND after apply, the custom set MUST exist and be the active token set

#### Scenario: Nonexistent token set is a hard error

- GIVEN a bundle with `config.tokenSet = "atlantis"` that is neither shipped nor in the bundle
- WHEN the bundle is imported
- THEN the whole import MUST be refused with an error naming the unresolvable token set

#### Scenario: Import is idempotent

- GIVEN a valid bundle applied once
- WHEN the identical bundle is applied again
- THEN the resulting app values, `custom-overrides.css` bytes, and custom-set files/manifest
  MUST be identical to the state after the first apply

### Requirement: occ Commands For OTAP Automation

The app MUST register two occ commands in `appinfo/info.xml` (`<commands>`):
`nldesign:config:export [file]` MUST write the bundle JSON to the given file, or stdout when
omitted, and exit 0. `nldesign:config:import <file> [--dry-run]` MUST read and validate the
bundle; with `--dry-run` it MUST perform phase 1 only, print the per-section results, write
nothing, and exit 0 when valid; without `--dry-run` it MUST apply and exit 0; on any hard
validation failure (or unreadable/undecodable file) it MUST print the full error listing and
exit non-zero. Both commands MUST reuse `ConfigBundleService` — no second serialization or
validation path.

@e2e exclude occ command surface — export to stdout and import from a file are CLI invocations for OTAP pipelines; there is no browser in that path; proven by tests/Unit/Service/ConfigBundleServiceTest.php.

#### Scenario: Export to stdout for pipeline use

- GIVEN an operator runs `occ nldesign:config:export` with no argument
- WHEN the command completes
- THEN the bundle JSON MUST be written to stdout with exit code 0 (usable in OTAP pipelines via
  redirection)

#### Scenario: Dry-run validates without writing

- GIVEN a valid bundle file and a differing live configuration
- WHEN `occ nldesign:config:import bundle.json --dry-run` runs
- THEN it MUST print the sections that would change, exit 0, and change no configuration value
  or file

#### Scenario: Validation failure exits non-zero

- GIVEN a bundle file with a hard validation error
- WHEN `occ nldesign:config:import bundle.json` runs
- THEN it MUST exit non-zero, print every section error, and apply nothing (so an OTAP pipeline
  step fails loudly instead of half-configuring production)

### Requirement: Admin UI Bundle Endpoints

The app MUST expose the bundle over HTTP for the settings panel via a dedicated controller with
`#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]` on every method:
`GET /settings/config/export` MUST return the bundle as an attachment named
`nldesign-config.json` with Content-Type `application/json`; `POST /settings/config/import` MUST
accept a multipart upload (256 KB cap, HTTP 413 beyond), run the same two-phase import, and
return the per-section result as JSON — HTTP 400 with the error listing on validation failure.

@e2e exclude endpoint response assertions — Content-Disposition on the download, the multipart upload and the HTTP 400 error listing are network-layer facts requiring request interception, not DOM; proven by tests/Unit/Controller/ConfigBundleControllerTest.php. The panel's own download and upload controls are covered by the admin-settings spec.

#### Scenario: Bundle download

- GIVEN an admin clicks Download in the Configuration block
- WHEN `GET /settings/config/export` responds
- THEN the response MUST carry Content-Disposition `attachment; filename="nldesign-config.json"`
  and the exact `export()` bundle as body

#### Scenario: Upload of an invalid bundle reports and refuses

- GIVEN an admin uploads a bundle with a hard validation error
- WHEN `POST /settings/config/import` responds
- THEN the status MUST be 400 with the per-section error listing in the JSON body
- AND no configuration MUST have changed

#### Scenario: Endpoints are admin-only

- GIVEN an unauthenticated caller or a non-admin user
- WHEN either `/settings/config/*` endpoint is called
- THEN the request MUST be rejected and nothing MUST be exported or applied

