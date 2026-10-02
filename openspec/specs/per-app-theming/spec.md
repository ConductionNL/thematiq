---
status: done
---

# per-app-theming Specification

## Purpose
Let an administrator exclude individual Nextcloud apps from NL Design theming. Requests rendering an excluded app's pages receive no nldesign CSS at all (stock Nextcloud styling, header included), while everything else — login, settings, and every non-excluded app — stays themed. Theming stays on by default (empty exclusion list reproduces global injection); admins opt specific apps out to roll a municipal huisstijl out incrementally or quarantine an app that breaks under theming. Backs GOVERNMENT-FEATURES F-15.
## Requirements
### Requirement: Per-App Exclusion List Storage
The set of theming-excluded apps MUST be stored in the `nldesign` appconfig key `disabled_apps` as a JSON array of app id strings, defaulting to an empty array. An empty or absent list MUST reproduce today's behavior exactly (theming injected globally).

#### Scenario: Fresh install / upgrade has no exclusions
@e2e exclude default-config branch — PHPUnit on AppThemingService
- GIVEN the `disabled_apps` appconfig key is absent
- WHEN any page is rendered
- THEN all nldesign CSS MUST be injected exactly as before this change
- AND `AppThemingService::getDisabledApps()` MUST return an empty array

#### Scenario: Unknown app ids self-heal on save
@e2e exclude validation branch — PHPUnit on AppThemingService
- GIVEN a saved list containing `files` and `uninstalled-app`
- AND `uninstalled-app` is not an installed app
- WHEN the admin saves the exclusion list again
- THEN `uninstalled-app` MUST be dropped from the persisted list without an error
- AND `files` MUST be retained

### Requirement: Request-Scoped Boot Guard

The per-app exclusion guard MUST run inside the `ThemeInjectionListener` when handling
`BeforeTemplateRenderedEvent`, before any style injection (historical requirement name retained
for continuity — the guard now runs at render-event time, not boot time). The app id being rendered MUST be
resolved primarily from the event's `TemplateResponse::getApp()`; when that value is empty or
`core`, the listener MUST fall back to
`AppThemingService::resolveAppIdFromPath(IRequest::getPathInfo())` (pattern
`^/apps/([a-zA-Z0-9_]+)`, working with and without the `index.php` prefix). When the resolved app
id is in the exclusion list, ALL nldesign style injection MUST be skipped for that render
(design-system stylesheets, token set CSS, `icon-contrast`/`error-contrast`,
`custom-overrides.css`, `hide-slogan.css`, `show-menu-labels.css`). The guard MUST NOT be applied
when handling `BeforeLoginTemplateRenderedEvent` (login is never an app page). Resolution
failures MUST fail open to themed (theming is presentation, not security).

#### Scenario: Excluded app renders without any nldesign CSS
- GIVEN the active token set is `rijkshuisstijl`
- AND `calendar` is in the exclusion list
- WHEN a user opens `/apps/calendar/` and its `TemplateResponse` (app id `calendar`) dispatches
  `BeforeTemplateRenderedEvent`
- THEN no stylesheet from the `nldesign` app MUST be present in the page head
- AND the page MUST render with stock Nextcloud styling (including the header on that page)

#### Scenario: Non-excluded app stays fully themed
- GIVEN the active token set is `rijkshuisstijl`
- AND `calendar` is in the exclusion list
- WHEN a user opens `/apps/files/`
- THEN all nldesign stylesheets MUST be injected exactly as without any exclusion
- AND the resolved `--nldesign-color-primary` MUST equal the rijkshuisstijl value

#### Scenario: Response app id takes precedence over the URL
@e2e exclude resolver-precedence branch — PHPUnit on the listener with a mocked TemplateResponse
- GIVEN `calendar` is in the exclusion list
- AND a `BeforeTemplateRenderedEvent` whose response reports app id `calendar`
- WHEN the guard resolves the app id
- THEN it MUST use the response's app id without consulting the request path
- AND injection MUST be skipped

#### Scenario: Empty or core response app falls back to path resolution
@e2e exclude fallback branch — PHPUnit on the listener with both URL forms
- GIVEN `calendar` is in the exclusion list
- AND a `BeforeTemplateRenderedEvent` whose response reports an empty app id or `core`
- WHEN the request path is `/apps/calendar/` or `/index.php/apps/calendar/`
- THEN the fallback resolver MUST yield `calendar` for both URL forms
- AND injection MUST be skipped identically for both

#### Scenario: Login and settings pages are always themed
- GIVEN any non-empty exclusion list
- WHEN the login page (`/login`, via `BeforeLoginTemplateRenderedEvent`) or an admin settings
  page (`/settings/admin/theming`) is rendered
- THEN nldesign CSS MUST be injected normally
- AND the guard MUST NOT run on the login event at all
- AND the exclusion list MUST have no effect on pages that resolve to no `/apps/{appid}` app

#### Scenario: Resolution failure fails open to themed
@e2e exclude error-path branch — PHPUnit with a throwing request/response mock
- GIVEN app id resolution throws, or both the response app id and the request path yield no app
  id
- WHEN the listener handles the render event
- THEN the guard MUST treat the app id as unresolved (null)
- AND theming MUST be injected normally without raising an error

### Requirement: App Theming Settings API
The app MUST expose `GET /settings/app-theming` returning the enabled user-facing apps with their themed state (`{ id, name, themed }`), and `POST /settings/app-theming` accepting `{ disabledApps: [...] }` to replace the exclusion list. Both endpoints MUST be admin-only (no `NoAdminRequired`) and CSRF-protected (no `NoCSRFRequired`). The ids `nldesign`, `settings`, and `theming` MUST never be listed nor accepted into the exclusion list.

#### Scenario: Admin reads the per-app theming state
@e2e exclude API contract — Newman collection covers request/response shape
- GIVEN `calendar` is in the exclusion list
- WHEN the admin calls `GET /settings/app-theming`
- THEN the response MUST list each enabled user-facing app with `id`, display `name` (from IAppManager), and `themed` boolean
- AND the entry for `calendar` MUST have `themed: false`
- AND no entry for `nldesign`, `settings`, or `theming` MUST be present

#### Scenario: Posting an exclusion for a protected id is ignored
@e2e exclude guard branch — PHPUnit on the controller
- GIVEN an admin POST with `disabledApps: ["calendar", "nldesign"]`
- WHEN the request is processed
- THEN `calendar` MUST be persisted as excluded
- AND `nldesign` MUST be silently dropped from the persisted list

#### Scenario: Non-admin cannot change the exclusion list
@e2e exclude auth-posture assertion — Newman verifies middleware rejection
- GIVEN a non-admin authenticated user
- WHEN they call `POST /settings/app-theming`
- THEN Nextcloud's SecurityMiddleware MUST reject the request

### Requirement: Per-App Toggle Admin UI
The NL Design settings panel MUST show a "Theming per app" section listing each enabled user-facing app as a labelled checkbox (checked = themed), sorted by display name, with a save action, saved/error feedback, and a localized hint explaining that excluded apps render with stock Nextcloud styling including the header on those pages. The UI MUST use standard Nextcloud form markup and CSS variables only (no hardcoded colors) and MUST be keyboard- and screen-reader-accessible (every checkbox has an associated label, WCAG 2.1 AA).

#### Scenario: Admin excludes an app via the panel
- GIVEN the admin opens the NL Design settings panel with an active token set
- WHEN the admin unchecks "Calendar" in the Theming per app section and saves
- THEN a success message MUST appear
- AND after reloading `/apps/calendar/` the page MUST render without nldesign CSS
- AND after reloading `/apps/files/` the page MUST remain themed

#### Scenario: Admin re-enables theming for an app
- GIVEN `calendar` is excluded
- WHEN the admin checks "Calendar" again and saves
- THEN `/apps/calendar/` MUST render with the active token set after reload

#### Scenario: Checkboxes are accessible
- GIVEN the Theming per app section is rendered
- WHEN inspected for accessibility
- THEN every checkbox MUST be associated with a visible label naming the app
- AND the section MUST be operable by keyboard alone
- AND focus states MUST be visible via the standard token-driven focus indicators

### Requirement: An administrator gives an app its own brand

Settings > Administration > Theming MUST offer a Brand per app block in which an administrator maps an installed app to a token set and, optionally, a large and a small logo. The app MUST NOT be excluded from theming and MUST NOT be one of the protected ids (`thematiq`, `settings`, `theming`). The block MUST state that the app's name stays Nextcloud's. The endpoints MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]`, and logo uploads MUST be checked for type and size.

#### Scenario: An administrator gives the knowledge base its own brand

@e2e exclude needs the Collectives app on the shared CI instance; proven by tests/Unit/Service/AppBrandServiceTest.php::testBrandedAppLooksTheSameForEveryGroup and tests/vitest/admin-app-brands.spec.js

- GIVEN an administrator on Settings > Administration > Theming with the Collectives app installed
- WHEN they map Collectives to set `kennisbank` with a large and a small logo and save
- THEN a user who opens Collectives MUST see the `kennisbank` colours and the large logo in the header
- AND a user who opens Files MUST see the house style as before

#### Scenario: A protected app cannot be branded

@e2e exclude API refusal; proven by tests/Unit/Service/AppBrandServiceTest.php::testRefusals and ::testEndpoints

- GIVEN an administrator in the Brand per app block
- WHEN they try to map the `settings` app
- THEN the save MUST fail with a message that the settings pages always follow the house style

### Requirement: The small logo is used on narrow screens

For a branded app with a small logo, the header MUST show the small logo below Nextcloud's narrow-screen breakpoint and the large logo above it. Without a small logo the large logo MUST be used at every width.

#### Scenario: A phone shows the small logo

@e2e exclude needs the Collectives app and a narrow viewport on the shared CI instance; proven by tests/Unit/Service/AppBrandServiceTest.php::testLogoLayerUsesLargeAndSmall

- GIVEN Collectives branded with a large and a small logo
- WHEN a user opens Collectives on a 360 px wide screen
- THEN the header MUST show the small logo
