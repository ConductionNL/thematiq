# Spec delta: shell tokens (shell-tokens-endpoint)

Another product reads the active house style as eighteen shell roles and a logo, so it can wear the
same shell as this Nextcloud.

## ADDED Requirements

### Requirement: Another product reads the active shell tokens

`GET /apps/thematiq/api/shell-tokens` MUST answer 200 with a JSON document of exactly these fields:
`version` (the integer `1`), `id` and `name` of the instance's active token set, `revision`,
`tokens` and `logo`. The route MUST be readable without a session and without a CSRF token
(`#[PublicPage]`, `#[NoCSRFRequired]`), and MUST limit a caller without a session to 120 requests a
minute (`#[AnonRateLimit]`). The document MUST describe the instance-wide active set, never a
group's set or a set in preview, and the route MUST take no parameter. The document MUST hold theme
values only: no user, no group and no setting. When the configured set no longer exists, the
document MUST describe the stock `nextcloud` set.

#### Scenario: A server without a session reads the active set
@e2e exclude API contract, covered by PHPUnit (ShellTokenControllerTest, ShellTokenServiceTest) and the Newman collection
- GIVEN the active token set is Gemeente Zuiddrecht
- AND no session
- WHEN a request is made to `GET /apps/thematiq/api/shell-tokens`
- THEN the response MUST be 200
- AND `id` MUST be `zuiddrecht` and `name` MUST be `Gemeente Zuiddrecht`
- AND `tokens.primary` MUST be `#3669A5`

#### Scenario: The configured set was removed
@e2e exclude API fallback branch, covered by PHPUnit on ShellTokenService
- GIVEN the configured token set is an uploaded set that was deleted
- WHEN a request is made to `GET /apps/thematiq/api/shell-tokens`
- THEN the response MUST be 200
- AND `id` MUST be `nextcloud`
- AND every token MUST have a value

#### Scenario: The document holds no user data
@e2e exclude API contract, covered by PHPUnit on ShellTokenService
- GIVEN any active token set
- WHEN a request is made to `GET /apps/thematiq/api/shell-tokens`
- THEN the document MUST hold only `version`, `id`, `name`, `revision`, `tokens` and `logo`

### Requirement: Every shell role always has a value

`tokens` MUST hold exactly these eighteen roles, each a non-empty string: `primary`, `primary-deep`,
`primary-light`, `action`, `action-deep`, `link`, `accent`, `ink`, `text`, `muted`, `line`, `soft`,
`ground`, `chip`, `field`, `radius`, `radius-lg` and `font-family`. The colour roles MUST be CSS
colours, the two radii CSS lengths, and `font-family` a CSS font stack. A role MUST be read from the
administrator's own value of the Nextcloud variable first, then from the active set's token. A role
the set does not fill MUST be derived from another role or take the stock Nextcloud value. A role
MUST NOT be null. `primary-deep` MUST NOT be lighter than `primary`, and `action-deep` MUST NOT be
lighter than `action`. A value that could leave the declaration it is written into (`;`, a brace, a
comment, an escape, `url()`) or a `var()` that did not resolve MUST NOT be returned. `ground` MUST
NOT be the set's Nextcloud login background.

#### Scenario: A set that declares only its primary colour
@e2e exclude Mapping rule, covered by PHPUnit on ShellRoleMapper
- GIVEN a token set that declares `--nldesign-color-primary: #3669a5` and nothing else
- WHEN its roles are mapped
- THEN `action` and `link` MUST be `#3669a5`
- AND all eighteen roles MUST have a value

#### Scenario: A hover colour lighter than the primary colour
@e2e exclude Mapping rule, covered by PHPUnit on ShellRoleMapper
- GIVEN a token set with primary `#154273` and primary hover `#1d5499`
- WHEN its roles are mapped
- THEN `primary-deep` MUST be darker than `#154273`

#### Scenario: An administrator set a colour in the token editor
@e2e exclude Mapping rule, covered by PHPUnit on ShellRoleMapper and ShellTokenService
- GIVEN the active set's primary colour is `#3669A5`
- AND the administrator set `--color-primary` to `#aa0000`
- WHEN a request is made to `GET /apps/thematiq/api/shell-tokens`
- THEN `tokens.primary` MUST be `#aa0000`

#### Scenario: Every shipped token set
@e2e exclude Mapping rule over every shipped set, covered by PHPUnit on ShellRoleMapper
- GIVEN any token set this app ships
- WHEN its roles are mapped
- THEN all eighteen roles MUST have a usable value

### Requirement: The document names the logo of the active set

`logo` MUST hold `url` and `inverse_url`, each an absolute URL or null. `url` MUST be the active
set's own logo, or the logo uploaded in Nextcloud's theming settings when the set has none.
`inverse_url` MUST be the logo the set draws on a dark surface, or null when it has none. The stock
Nextcloud logo MUST NOT be returned.

#### Scenario: A set with a light and a dark logo
@e2e exclude API contract, covered by PHPUnit on ShellTokenService
- GIVEN the active token set is Gemeente Zuiddrecht
- WHEN a request is made to `GET /apps/thematiq/api/shell-tokens`
- THEN `logo.url` MUST end in `img/logos/zuiddrecht.svg`
- AND `logo.inverse_url` MUST end in `img/logos/zuiddrecht-dark.svg`

#### Scenario: A set without a logo and no upload
@e2e exclude API contract, covered by PHPUnit on ShellTokenService
- GIVEN the active token set ships no logo
- AND no logo was uploaded in Nextcloud's theming settings
- WHEN a request is made to `GET /apps/thematiq/api/shell-tokens`
- THEN `logo.url` and `logo.inverse_url` MUST be null

### Requirement: An unchanged document is answered with 304

`revision` MUST change whenever `id`, `name`, any token or any logo URL changes, and MUST NOT change
otherwise. The response MUST carry the revision as its `ETag` and MUST allow a reader to keep the
answer and revalidate it (`Cache-Control: no-cache`). A request whose `If-None-Match` names the
current revision, quoted, weak, in a list or as `*`, MUST be answered 304 with no body and the same
`ETag`. Any other request MUST be answered 200 with the document.

#### Scenario: The reader holds the current revision
@e2e exclude Conditional request, covered by PHPUnit on ShellTokenController and the Newman collection
- GIVEN a reader that read the document and kept its `revision`
- AND nothing changed since
- WHEN it requests `GET /apps/thematiq/api/shell-tokens` with `If-None-Match` set to that revision
- THEN the response MUST be 304 with no body

#### Scenario: The administrator changed a colour
@e2e exclude Conditional request, covered by PHPUnit on ShellTokenController and ShellTokenService
- GIVEN a reader that kept an earlier `revision`
- AND the administrator changed a colour since
- WHEN it requests `GET /apps/thematiq/api/shell-tokens` with `If-None-Match` set to the earlier revision
- THEN the response MUST be 200
- AND `revision` MUST differ from the earlier one
