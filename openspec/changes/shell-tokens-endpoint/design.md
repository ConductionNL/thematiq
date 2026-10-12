# Design: let another product read the active shell tokens

## Where it fits (development `632bf693`)

- **Which set is active** is `ActiveTokenSetService::getActive()` (app config `token_set`, default
  `nextcloud`).
- **What a set declares** is resolved by `ShippedTokenSetAuditService::resolveDeclarations()`:
  `css/systems/nldesign/defaults.css`, then `css/tokens/<id>.css` from the release or the runtime
  store, with `var()` chains resolved. The contrast audit and the capabilities document already
  read sets through it.
- **The stock set** is read from the running instance by `StockTokensService::getTokens()`, because
  its shipped file is a snapshot of one Nextcloud version. `CssInjectionService` does the same for
  the page.
- **An administrator's own values** are `CustomOverridesService::read()`, keyed by Nextcloud
  variable (`--color-primary`, `--border-radius`).
- **Which token a Nextcloud variable reads** is `css/systems/nldesign/overrides.css`
  (`--color-main-text: var(--nldesign-color-text)`).
- **The logo** of a set is `img/logos/<id>.<ext>`, found by `LogoLayerService::shippedLogo()`, with
  the logo uploaded in Nextcloud's theming settings as the fallback (`LogoLayerService::layer()`).
- **Public routes** in this app carry `#[PublicPage]`, `#[NoCSRFRequired]` and `#[AnonRateLimit]`
  (`FontController`, `IconController`, `RuntimeFileController`, `HealthController`).

## Decision 1: the route is public, with a ceiling

The reader is a server. It has no Nextcloud session and no CSRF token. Three options:

| Option | What the other server holds | Verdict |
|---|---|---|
| `#[NoAdminRequired]`, read with an app password | A Nextcloud account that can read that user's files, mail and calendar | Too much for eighteen values |
| A shared secret of our own in a header | A new credential to issue, store, rotate and leak | A lock on a door that is already open |
| `#[PublicPage]` + `#[NoCSRFRequired]` + `#[AnonRateLimit]` | Nothing | Chosen |

The values are public already. Every visitor of the login page receives the active set's
stylesheet, its logo and its font before signing in. The route gives the same values a name each.

`CatalogController` stays signed-in for a reason that does not apply here: it lists every set,
among them uploaded sets that are not in use. This route answers for the one set the instance
wears, never for a group's set or a set in preview, and it takes no parameter, so a caller cannot
ask about another set.

The response is built from an allow-list: `version`, `id`, `name`, `revision`, eighteen `tokens`,
two `logo` URLs. No user, group or setting can reach it.

The ceiling is 120 requests a minute for a caller without a session, the same as the health route.
There is nothing to guess, so there is no brute-force counter.

## Decision 2: the roles are read where a themed Nextcloud reads them

`ShellRoleMapper` holds one table: per role, the names to try, first hit wins.

1. The Nextcloud variable, when the administrator set it in the token editor.
2. The Summer Breeze name, for the one set that keeps its own vocabulary.
3. The `--nldesign-*` token that Nextcloud variable reads in `overrides.css`.

| Role | Read from | When nothing fills it |
|---|---|---|
| `primary` | `--color-primary`, `--nldesign-color-primary` | `#00679e` |
| `primary-deep` | `--color-primary-hover`, `--nldesign-color-primary-hover`, only when darker than `primary` | `primary` with 30% black |
| `primary-light` | `--color-primary-light`, `--nldesign-color-primary-light` | `primary` at 10% on white |
| `action` | `--color-primary-element`, `--nldesign-color-primary` | `primary` |
| `action-deep` | `--color-primary-element-hover`, `--nldesign-color-primary-hover`, only when darker than `action` | `action` with 30% black |
| `link` | `--nldesign-color-link` | `primary` |
| `accent` | `--nldesign-color-accent`, then `--color-error`, `--nldesign-color-error` | `#d32f2f` |
| `ink` | `--color-main-text`, `--nldesign-color-text` | `#222222` |
| `text` | always derived: halfway between `ink` and `muted` | `ink` |
| `muted` | `--color-text-maxcontrast`, `--nldesign-color-text-muted` | `#767676` |
| `line` | `--color-border`, `--nldesign-color-border` | `#d9d9d9` |
| `soft` | `--color-background-dark`, `--nldesign-color-background-dark` | `#ededed` |
| `ground` | `--nldesign-component-content-surface-background-color`, `--nldesign-color-background` | `#ffffff` |
| `chip` | `--color-background-hover`, `--nldesign-color-background-hover` | `#f5f5f5` |
| `field` | `--color-border-maxcontrast`, `--nldesign-color-border-dark` | `line`, then `#767676` |
| `radius` | `--border-radius`, `--nldesign-border-radius` | `8px` |
| `radius-lg` | `--border-radius-large`, `--nldesign-border-radius-large` | `radius`, then `8px` |
| `font-family` | `--font-face`, `--nldesign-font-family` | the system font stack |

The last column is the stock Nextcloud look, as `css/tokens/nextcloud.css` records it.

Four choices need a reason:

- **`action` is the primary colour.** Nextcloud's buttons wear `--color-primary-element`, which
  `overrides.css` reads from `--nldesign-color-primary`. `--nldesign-color-button-primary-background`
  is not read: `defaults.css` gives it Rijkshuisstijl blue, so a set that declares only its primary
  would get another organisation's button colour.
- **The deep roles must be darker.** A set's hover colour is often lighter than its primary (stock
  Nextcloud and Rijkshuisstijl both are). A lighter candidate is passed over and the role is derived.
- **`text` has no token.** No set declares a body colour between ink and muted. It is derived for
  every set, so it is the same rule everywhere.
- **`ground` never takes the login background.** `resolveDeclarations()` can fall back to a set's
  `theming.background_color`, which is the Nextcloud login background (vng's is a saturated blue).
  The service passes no theming block, so a set without a page colour gets white, which is what
  Nextcloud draws.

A value is passed over when a consumer could not write it into a stylesheet: empty, a `var()` that
did not resolve, or one holding `;`, a brace, a comment, an escape or `url()`. A token set can be
uploaded, and the reader writes these values into its own CSS.

## Decision 3: the logo

`logo.url` is the set's own logo, then the logo uploaded in Nextcloud's theming settings, then
`null`. That is the order the header is drawn in. `logo.inverse_url` is the logo the set draws on a
dark surface (`img/logos/<id>-dark.<ext>`), or `null`. The stock Nextcloud mark is not offered as
anyone's logo. Both are absolute URLs on the host the request came in on.

## Decision 4: the revision

`revision` is the first sixteen hex characters of the SHA-256 of the document without it. It moves
when any value moves and stays when none does. It needs no stored state and no invalidation.

The response sets `ETag` to the revision and `Cache-Control: no-cache`. Nextcloud's default
(`no-store`) would forbid a reader to keep the answer, which defeats a conditional request.
`If-None-Match` may be a list, may be weak (`W/`), and may be `*`.

## Decision 5: getting a new path live

Nextcloud caches the compiled route collection per host for an hour
(`lib/private/Route/CachingRouter.php`, key `<host>#<base url>#rootCollection`, three seconds when
`debug` is on). A path that is new answers 404 until that entry is renewed.

Read in Nextcloud 35.0.1, not measured on a running instance:

- **A release needs nothing.** The cache prefix holds every enabled app's installed version
  (`lib/private/Memcache/Factory.php`, `getGlobalPrefix()`). An update moves thematiq's version, so
  the old entry is never read again and the route answers on the first request.
- **A code change without a version change** (a hotfix copied in, a development mount) waits out
  the hour. Restarting the PHP workers (`apachectl graceful`, or reloading php-fpm) empties the
  local cache and makes it immediate.
- **Disabling and enabling the app does not help.** The prefix returns to what it was, and the old
  entry is still there.

## Not in this change

- No per-group or per-user answer. A server reads the instance's house style.
- No dark values. The shell roles are one scheme; a dark set of roles is a later change with its
  own fields.
- No `openspec/parity/capabilities.json` row. The route is plumbing for the plugin, not a
  capability an administrator compares products on.
