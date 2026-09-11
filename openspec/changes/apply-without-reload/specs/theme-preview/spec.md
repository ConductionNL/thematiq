# Spec delta: Theme Preview (apply-without-reload)

The preview state, its endpoints and its isolation are unchanged. What changes is the settings
page's own reaction to starting and discarding a preview: it applies the previewed set to itself
and shows or hides its preview panel, instead of reloading.

## MODIFIED Requirements

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

## ADDED Requirements

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
