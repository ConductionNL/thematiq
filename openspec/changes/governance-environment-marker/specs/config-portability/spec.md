# Spec delta: configuration portability (config-portability)

The environment a server declares is excluded from the bundle, because it is the one value that must differ between OTAP environments.

## MODIFIED Requirements

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


