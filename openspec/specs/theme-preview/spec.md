# theme-preview Specification

## Purpose
TBD - created by archiving change theme-preview-workflow. Update Purpose after archive.

## Requirements

### Requirement: Per-User Preview State

The app MUST store theme preview state exclusively as IConfig **user** values for the previewing
admin — key `preview_token_set` (a token set id) and key `preview_expires_at` (unix timestamp) —
and MUST NOT store any preview state in app-level (instance-wide) configuration. Starting a
preview MUST set `preview_expires_at` to 24 hours from the start time. A preview MUST be managed
by a dedicated `ThemePreviewService` exposing start, get-active, clear, and publish operations;
starting a preview MUST validate the token set id via `TokenSetService::isValidTokenSet()` and
reject invalid ids.

#### Scenario: Starting a preview writes only user values

- GIVEN an admin with uid `admin` and active instance-wide token set `rijkshuisstijl`
- WHEN the admin starts a preview of `amsterdam`
- THEN the user values `preview_token_set = "amsterdam"` and `preview_expires_at ≈ now + 86400`
  MUST be written for uid `admin`
- AND the app value `token_set` MUST remain `rijkshuisstijl` unchanged

#### Scenario: Invalid token set id is rejected

- GIVEN a preview start request for id `does-not-exist`
- WHEN `ThemePreviewService::startPreview()` validates the id
- THEN the request MUST fail (HTTP 400 at the controller) and no user values MUST be written

#### Scenario: Preview auto-expires after 24 hours

- GIVEN a user value `preview_expires_at` in the past
- WHEN the active preview is resolved for that user
- THEN the preview MUST be treated as inactive (the active instance-wide set renders)
- AND the stale user values MAY be deleted lazily outside the boot path — the app MUST NOT add a
  background job for expiry

### Requirement: Preview Isolation

A theme preview MUST be visible ONLY in the previewing admin's own session. The CSS injection
layer — defined as the code path that resolves the active `token_set` and emits the
`Util::addStyle()` cascade, currently `Application::injectThemeCSS()` and, if change
`render-event-injection` lands, its render-event listener — MUST resolve an *effective* token
set per request: the requesting user's `preview_token_set` user value substitutes the active set
only when ALL hold: a user session exists, the user is currently an admin
(`IGroupManager::isAdmin()` re-checked at render time), `preview_expires_at` is in the future,
and the id still validates. Only the token set is substituted; custom overrides, hide-slogan,
menu-labels, and the per-app exclusion guard MUST keep their active behaviour. Any resolution
failure (no session, CLI/occ, cron, exception) MUST fall back to the active set.

#### Scenario: Non-admin users are never affected

- GIVEN an admin is actively previewing `amsterdam`
- AND a second, non-admin user browses the instance
- WHEN the second user's pages render
- THEN they MUST be styled with the active instance-wide token set
- AND no preview banner markup, script, or style MUST be present in their pages

#### Scenario: Previewing admin sees the previewed set on real pages

- GIVEN an admin has an active, non-expired preview of `amsterdam`
- WHEN that admin loads any themed page (Files, Dashboard, the settings area)
- THEN the injection layer MUST load `amsterdam`'s design-system stylesheets, token stylesheet,
  and contrast fixes exactly as if `amsterdam` were the active set
- AND custom overrides MUST still load last, unchanged

#### Scenario: Demoted user's preview value is ignored

- GIVEN a user holds preview user values but is no longer a member of the admin group
- WHEN their pages render
- THEN the active instance-wide set MUST render (the render-time admin check fails closed for
  preview, open for normal theming)

#### Scenario: Preview resolution never breaks boot

- GIVEN preview resolution throws (e.g. no user session in occ/cron context)
- WHEN the injection layer runs
- THEN it MUST fall back to the active token set and complete injection normally

### Requirement: Preview Banner

While a preview is active for the requesting user, every themed page MUST show a persistent
banner identifying the preview. The banner MUST be delivered by vanilla JS
(`js/preview-banner.js`) and CSS (`css/preview-banner.css`) with no build step, receive its
state via `IInitialState::provideInitialState()` / `OCP.InitialState.loadState()` (never DOM
data attributes), use `role="status"`, NC CSS variables only, and keyboard-operable controls. It
MUST show the previewed set's name, a statement that only the current user sees it, a
**Publish** control, and a **Discard** control. Banner assets MUST NOT be loaded for any request
without an active preview.

#### Scenario: Banner appears on all themed pages for the previewer

- GIVEN an admin with an active preview of "Gemeente Amsterdam"
- WHEN they load Files, Dashboard, or the settings area
- THEN a banner MUST be visible reading "Preview: Gemeente Amsterdam" plus an
  only-visible-to-you notice (localized, English source keys)
- AND the banner MUST expose Publish and Discard controls operable by keyboard

#### Scenario: Discard from the banner

- GIVEN the banner is shown
- WHEN the admin activates Discard
- THEN the client MUST call `DELETE /settings/preview` and reload
- AND after the reload the active instance-wide set MUST render with no banner

#### Scenario: No banner payload without a preview

- GIVEN a request by any user without an active preview
- WHEN the page renders
- THEN neither `preview-banner.js`, `preview-banner.css`, nor the preview initial-state payload
  MUST be included in the response

### Requirement: Preview Lifecycle Endpoints

The app MUST expose three admin-only endpoints, each annotated
`#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]`: `POST /settings/preview`
(start, body `tokenSet`), `DELETE /settings/preview` (discard), and
`POST /settings/preview/publish` (publish). The acting uid MUST be resolved from `IUserSession`,
never from request input. Publish MUST promote the caller's previewed id to the instance-wide
`token_set` app value and clear the caller's preview user values; publish with no active
(non-expired) preview MUST return HTTP 400 and change nothing.

#### Scenario: Endpoints are admin-only

- GIVEN an unauthenticated caller or an authenticated non-admin user
- WHEN any `/settings/preview*` endpoint is called
- THEN the request MUST be rejected by the `AuthorizedAdminSetting` check and no configuration
  MUST change

#### Scenario: Publish promotes preview to instance-wide active

- GIVEN admin `admin` has an active preview of `amsterdam` and the active set is
  `rijkshuisstijl`
- WHEN `POST /settings/preview/publish` is called by `admin`
- THEN the app value `token_set` MUST become `amsterdam`
- AND both preview user values for `admin` MUST be deleted
- AND every user's subsequent page loads MUST render `amsterdam`

#### Scenario: Publish without an active preview

- GIVEN the caller has no preview user values (or only expired ones)
- WHEN `POST /settings/preview/publish` is called
- THEN the response MUST be HTTP 400 and the app value `token_set` MUST be unchanged

### Requirement: Publish Runs The Existing Instance-Wide Dialogs
Publishing a preview from the UI MUST run the same guarded flow as a direct instance-wide token set
change; only confirmation MUST call `POST /settings/preview/publish`. The settings page's Publish
control MUST work for a preview started on that page without a reload, so it MUST be bound whenever
the control exists and read the current preview at click time, not at page load.

#### Scenario: Publish flows through apply and theming-sync dialogs
- GIVEN an active preview of a set with theming metadata
- WHEN the admin activates Publish on the banner
- THEN the browser MUST navigate to the nldesign settings panel with the preview detected
- AND the apply dialog MUST open for the previewed set, followed by the theming-sync dialog on
  confirm
- AND only after confirmation MUST the publish endpoint be called

#### Scenario: Publish from a preview started on the settings page
- GIVEN the admin started a preview on the settings page and did not reload
- WHEN they activate the page's Publish control
- THEN the apply dialog MUST open for the previewed set, exactly as after a navigation
- AND on confirm the page MUST end its preview state (panel hidden, `activePreview` cleared) and
  carry the published set

#### Scenario: Cancelling the publish dialogs keeps the preview
- GIVEN the apply dialog is open from a banner-initiated publish
- WHEN the admin cancels
- THEN no endpoint MUST be called, the preview user values MUST remain, and the banner MUST
  still show on subsequent pages

### Requirement: The Instrument Describes The Previewed Set
When a session preview is active, the component playground MUST describe the previewed set:
the token values it exports and the values its specimens are drawn with MUST be the
previewed set's, not the instance-wide one.

#### Scenario: The instrument follows the preview
- GIVEN an active session preview of a set other than the instance-wide one
- WHEN the admin opens the theming panel
- THEN the specimens MUST be drawn with the previewed set's values
- AND an export MUST serialise the previewed set

#### Scenario: A preview does not leak to other sessions
- GIVEN an admin previewing a set
- WHEN another user loads any page
- THEN that user MUST see the instance-wide set, exactly as before this change

### Requirement: Start And Discard Apply On The Settings Page
On the settings page, starting a preview MUST apply the previewed set's stylesheets to the page
and show the preview panel; discarding MUST apply the instance-wide set's stylesheets and hide the
panel. Neither MUST reload. Other pages keep the server-rendered banner, which reads the session
state on their next load.

#### Scenario: Start
- GIVEN `zwolle` is selected and `rijkshuisstijl` is active
- WHEN the admin clicks "Preview in my session" and `POST /settings/preview` answers `ok`
- THEN the page MUST swap to `zwolle`'s stylesheet run
- AND the preview panel MUST become visible, naming `zwolle`
- AND `token_set` MUST still be `rijkshuisstijl`

#### Scenario: Discard
- GIVEN a preview is active on the page
- WHEN the admin clicks Discard and `DELETE /settings/preview` returns
- THEN the page MUST swap back to the instance-wide set's run
- AND the panel MUST hide and the dropdown MUST show the instance-wide set

#### Scenario: Module absent
- GIVEN `js/lib/layerSwap.js` did not load
- WHEN a preview is started or discarded
- THEN the page MUST fall back to reloading, as before
