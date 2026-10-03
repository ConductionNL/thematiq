# token-deprecations Specification

## Purpose
TBD - created by archiving change authoring-token-lifecycle. Update Purpose after archive.

## Requirements

### Requirement: An administrator deprecates a token

An administrator MUST be able to deprecate any `--nldesign-*` token, own or shipped, from its row in
the token editor or from the deprecations list. A deprecation MUST hold a severity (`info`,
`warning` or `critical`), and MAY hold a replacement token, a removal date and a message. The
replacement MUST be an existing token name. The removal date MUST NOT be in the past when it is set.
A deprecation MUST NOT change the token's value. `POST /apps/thematiq/settings/tokens/deprecations`
MUST be admin-only and MUST write a `token_deprecation_changed` audit entry.

#### Scenario: An administrator deprecates an own token with a replacement and a date
- GIVEN an administrator in the token editor
- AND the own tokens `--nldesign-org-old-accent` and `--nldesign-org-brand-accent`
- WHEN the administrator deprecates `--nldesign-org-old-accent` with severity warning, replacement
  `--nldesign-org-brand-accent`, removal date 2027-03-01 and message "Use the new brand accent"
- THEN the row MUST show a deprecation badge with the severity
- AND `--nldesign-org-old-accent` MUST still render its old value

#### Scenario: A replacement that does not exist is refused
@e2e exclude API validation branch, covered by PHPUnit on TokenDeprecationService
- GIVEN an administrator
- WHEN they deprecate a token with replacement `--nldesign-org-missing`
- THEN the response MUST be 400 naming the replacement

#### Scenario: A shipped token is deprecated as a notice
- GIVEN the active set declares `--nldesign-color-primary-light`
- WHEN the administrator deprecates it with severity info
- THEN the deprecations list MUST label it "Notice only: the value comes from the token set"
- AND no remove action MUST be offered for it

### Requirement: Consuming apps can read every deprecation

`GET /apps/thematiq/api/token-deprecations` MUST return every deprecation as
`{token, severity, replacement, removalDate, message, deprecatedAt, due, state}` to any logged-in
user (`#[NoAdminRequired]`), and MUST refuse a request without a session. `due` MUST be true when
the removal date has passed. The response MUST hold no user data.

#### Scenario: A portal developer reads the deprecations
@e2e exclude API contract, covered by the Newman collection
- GIVEN a logged-in user who is not an administrator
- AND `--nldesign-org-old-accent` is deprecated
- WHEN the user requests `GET /apps/thematiq/api/token-deprecations`
- THEN the response MUST be 200 and list `--nldesign-org-old-accent` with its severity, replacement and date

#### Scenario: An anonymous request is refused
@e2e exclude Auth posture, covered by the Newman collection
- GIVEN no session
- WHEN a request is made to `GET /apps/thematiq/api/token-deprecations`
- THEN the request MUST be refused

### Requirement: The served stylesheet names each deprecated own token

`custom-overrides.css` MUST write a comment directly above each deprecated own token, naming the
severity, the replacement and the removal date when they exist.

#### Scenario: A developer inspects a deprecated token
@e2e exclude Generated file content, covered by PHPUnit on CustomOverridesService
- GIVEN `--nldesign-org-old-accent` is deprecated with severity warning, replacement
  `--nldesign-org-brand-accent` and removal date 2027-03-01
- WHEN `custom-overrides.css` is written
- THEN the line above its declaration MUST read
  `/* deprecated (warning): use --nldesign-org-brand-accent, removal 2027-03-01 */`

### Requirement: A passed removal date is flagged, never acted on

When a deprecated own token's removal date has passed, the token editor and the deprecations list
MUST mark it "Due for removal". Thematiq MUST NOT remove or change the token by itself.

#### Scenario: A due token keeps working
- GIVEN `--nldesign-org-old-accent` has removal date 2026-10-01 and today is later
- WHEN the administrator opens the token editor
- THEN the row MUST show "Due for removal"
- AND `var(--nldesign-org-old-accent)` MUST still resolve to its value on every page

### Requirement: Imported deprecation notices can be recorded

When an upload returns `importWarnings`, the result MUST offer "Record as deprecations". Choosing it
MUST create one deprecation per notice with source `import`, severity `warning` and the notice text
as message. Nothing MUST be recorded without that choice.

#### Scenario: An administrator keeps the notices from an upload
- GIVEN an administrator uploads a DTCG file in which `color.primary` carries `"$deprecated": "Use color.brand.primary instead"`
- WHEN the administrator clicks "Record as deprecations" in the result
- THEN the deprecations list MUST hold `--nldesign-color-primary` with severity warning and that message
