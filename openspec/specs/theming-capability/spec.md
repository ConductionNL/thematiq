# theming-capability Specification

## Purpose
TBD - created by archiving change theming-capability-api. Update Purpose after archive.
## Requirements
### Requirement: Public Theming Capability Registration

The app MUST expose a `nldesign` entry in the Nextcloud capabilities document via a
`Capabilities` class implementing `OCP\Capabilities\IPublicCapability`, registered in
`Application::register()` with `IRegistrationContext::registerCapability()`. Implementing
`IPublicCapability` (not merely `ICapability`) is REQUIRED so unauthenticated clients — login
pages, portals, mobile/desktop clients pre-session — can read the huisstijl, mirroring core
`apps/theming`'s public capability. The capability MUST NOT require any new route, controller,
or authentication surface.

#### Scenario: Capability appears for authenticated clients
- GIVEN the nldesign app is enabled
- WHEN an authenticated client requests `GET /ocs/v2.php/cloud/capabilities` with the
  `OCS-APIRequest: true` header
- THEN the response `capabilities` object MUST contain an `nldesign` key
- AND its value MUST be the payload defined by the Theming Capability Payload requirement

#### Scenario: Capability appears without authentication
@e2e exclude OCS surface — verified by unauthenticated curl against the dev instance (tasks 4.3)
- GIVEN the nldesign app is enabled
- WHEN an unauthenticated client requests `GET /ocs/v2.php/cloud/capabilities` with the
  `OCS-APIRequest: true` header
- THEN the `nldesign` capability MUST be present with the same payload as for authenticated
  clients (IPublicCapability contract)

#### Scenario: Disabled app publishes no capability
@e2e exclude negative-availability statement — verified manually by disabling the app
- GIVEN the nldesign app is disabled
- WHEN the capabilities document is requested
- THEN it MUST NOT contain an `nldesign` key

### Requirement: Theming Capability Payload

The `nldesign` capability value MUST be an object with exactly these keys:

- `version` (string) — the installed nldesign app version;
- `tokenSet` (object) — the active token set: `id` (string; appconfig `token_set`, default
  `"nextcloud"`), `name` (string; the manifest display name, falling back to the id when the
  active set has no manifest entry), `version` (string|null; the manifest's optional version
  field, `null` when undeclared);
- `designSystem` (string) — the resolved design-system id for the active set (via
  `DesignSystemService::getTokenSetMeta()`; `"none"` for stock Nextcloud);
- `iconPacks` (array of strings) — the resolved ordered icon-pack list for the active set, via
  `DesignSystemService::resolveActiveIconPacks()` (`openspec/specs/icon-packs/spec.md`); an empty
  array when no pack is active;
- `wcagLevel` (string|null) — the audited contrast conformance of the active set per the WCAG
  Audit Level requirement;
- `logos` (object) — available logo variant web paths for the active set, keyed by variant name (`default` from `theming.logo`; `emblem` when the set ships `img/logos/<id>-emblem.svg`, the mark without the wordmark, for an app that draws the set's mark small);
  `default` maps to the web path of the manifest's `theming.logo` asset; an empty object when the
  set declares no logo. Additional variants (e.g. `dark`) are reserved for future changes
  (dark-mode-token-variants) — consumers MUST treat unknown variant keys as additive;
- `hideSlogan` (bool) — whether the `hide_slogan` toggle is active;
- `showMenuLabels` (bool) — whether the `show_menu_labels` toggle is active.

The payload MUST reflect the live appconfig state (no stale snapshot across a token-set change)
and MUST contain only branding facts already observable by anyone loading the themed login page —
never admin configuration such as the per-app exclusion list, custom override CSS content, or
filesystem paths outside web asset paths.

#### Scenario: Payload for an active shipped set
- GIVEN the active token set is `rijkshuisstijl` with a manifest entry declaring name
  "Rijkshuisstijl" and `theming.logo: img/logos/rijkshuisstijl.svg`
- AND `hide_slogan` is `'1'` and `show_menu_labels` is `'0'`
- WHEN the capability payload is computed
- THEN `tokenSet` MUST equal `{ "id": "rijkshuisstijl", "name": "Rijkshuisstijl", "version": null }`
  (no version declared in the manifest today)
- AND `logos.default` MUST be the web path resolving to `img/logos/rijkshuisstijl.svg`
- AND `logos` MUST carry no `emblem` key, because no `img/logos/rijkshuisstijl-emblem.svg` is shipped

#### Scenario: A set that ships an emblem exposes it
@e2e exclude PHPUnit on Capabilities (CapabilitiesTest::testEmblemIsExposedWhenShipped)
- GIVEN the active token set is `zuiddrecht`, whose manifest entry declares `theming.logo: img/logos/zuiddrecht.svg`
- AND the app ships `img/logos/zuiddrecht-emblem.svg`
- WHEN the capability payload is computed
- THEN `logos.emblem` MUST be the web path resolving to `img/logos/zuiddrecht-emblem.svg`
- AND `logos.default` MUST still be the web path resolving to `img/logos/zuiddrecht.svg`
- AND `hideSlogan` MUST be `true` and `showMenuLabels` MUST be `false`
- AND `iconPacks` MUST equal the resolved pack list for `rijkshuisstijl`'s design system
  (`["rvo", "open-gemeenten", "den-haag"]`)

#### Scenario: Payload for stock Nextcloud
@e2e exclude default-config branch — PHPUnit on Capabilities
- GIVEN no `token_set` appconfig value is set
- WHEN the capability payload is computed
- THEN `tokenSet.id` MUST be `"nextcloud"`
- AND `designSystem` MUST be `"none"`
- AND `iconPacks` MUST be an empty array
- AND `logos` MUST be an empty object

#### Scenario: Payload for a custom or unknown set degrades, never lies
@e2e exclude fallback branch — PHPUnit on Capabilities
- GIVEN the active token set id has no entry in `token-sets.json` (e.g. an admin-uploaded custom
  set)
- WHEN the capability payload is computed
- THEN `tokenSet.name` MUST fall back to the id and `tokenSet.version` MUST be `null`
- AND `wcagLevel` MUST be `null` (unaudited — the capability MUST NOT fabricate a conformance
  claim)
- AND `iconPacks` MUST be an empty array (no design system to resolve a pack from)

#### Scenario: No admin-only data leaks
@e2e exclude security assertion — PHPUnit asserts payload key allowlist
- GIVEN any configuration state
- WHEN the capability payload is serialized
- THEN it MUST contain only the eight specified keys
- AND it MUST NOT contain the per-app theming exclusion list, custom override CSS, custom
  token-set contents, or any server filesystem path

### Requirement: WCAG Audit Level in Capability

`wcagLevel` MUST be derived from the app's existing contrast audit
(`ShippedTokenSetAuditService`) for the active set: `"AAA"` when the set declares
`contrast_level: "AAA"` and passes the AAA-threshold audit; otherwise `"AA"` when the set passes
the AA-threshold audit; otherwise `"fail"` for a shipped set that fails its audit; `null` for
sets the audit cannot cover (custom/unknown sets). The computed level MUST be cached (distributed
cache via `ICacheFactory`, TTL at most one hour, keyed by the active token set id) so that
serving the capabilities document never re-parses token CSS per request; changing the active
token set MUST yield the new set's level on the next capabilities read (new cache key).

#### Scenario: AA shipped set reports AA
@e2e exclude audit derivation — PHPUnit with mocked audit service
- GIVEN the active set is a shipped set without `contrast_level: "AAA"` that passes the AA audit
- WHEN `wcagLevel` is computed
- THEN it MUST be `"AA"`

#### Scenario: High-contrast set reports AAA
@e2e exclude audit derivation — PHPUnit with mocked audit service
- GIVEN the active set declares `contrast_level: "AAA"` and passes the AAA-threshold audit
- WHEN `wcagLevel` is computed
- THEN it MUST be `"AAA"`

#### Scenario: Audit result is cached across requests
@e2e exclude caching branch — PHPUnit asserts single audit invocation
- GIVEN two capability computations for the same active set within the cache TTL
- WHEN both requests are served
- THEN the contrast audit MUST run at most once
- AND both responses MUST report the same `wcagLevel`

#### Scenario: Token set change is reflected
- GIVEN the admin switches the active token set from an AA set to the high-contrast AAA set
- WHEN the capabilities document is requested after the change
- THEN `wcagLevel` MUST report the new set's level (the cache key follows the active set id)

### Requirement: Capability Robustness

`Capabilities::getCapabilities()` MUST never throw: the Nextcloud capabilities endpoint
aggregates every app's capability, so one throwing provider breaks the document for all clients.
On any internal failure (unreadable manifest, throwing service), the method MUST degrade to a
minimal payload — `version` and `tokenSet.id` from raw appconfig, with `name` falling back to the
id and the remaining fields `null`, `{}`, `[]`, or `false` — and MUST NOT expose exception details
in the payload.

#### Scenario: Throwing dependency degrades gracefully
@e2e exclude error-path branch — PHPUnit with throwing service mocks
- GIVEN an injected service throws during payload computation
- WHEN `getCapabilities()` is called
- THEN it MUST return the `nldesign` key with the minimal payload
- AND `iconPacks` MUST be an empty array
- AND no exception MUST propagate to the OCS layer
- AND no exception message MUST appear in the payload

