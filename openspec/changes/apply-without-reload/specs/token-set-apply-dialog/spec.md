# Spec delta: Token Set Apply Dialog (apply-without-reload)

The dialog's contents, checkboxes and live preview are unchanged. What changes is what confirming
does after the overrides are written: the set is applied to the page the admin is on, and the
theming sync is offered — instead of a toast asking for a reload and a sync that never came.

## MODIFIED Requirements

### Requirement: Token Set Applied Together With Overrides
Clicking **Apply** MUST write the checked token values to `custom-overrides.css`, save the selected
token set as the new active base layer, apply that set to the current page, and then offer the
theming sync. It MUST NOT ask the admin to reload.

#### Scenario: Active token set in config after apply
- GIVEN the admin was on rijkshuisstijl and applied values from utrecht
- WHEN the apply dialog completes
- THEN the app config `token_set` value MUST be updated to "utrecht"
- AND the selected values from utrecht MUST be in `custom-overrides.css` as explicit overrides
- AND the token-set dropdown MUST reflect "utrecht" as the active selection

#### Scenario: The set is on the page when the dialog closes
- GIVEN the apply dialog is confirmed for `utrecht`
- WHEN the dialog closes
- THEN the page MUST carry `utrecht`'s stylesheet run (its token file, logo style, element
  overrides, dark variant, and the design-system files when coming from stock), obtained from
  `GET /settings/tokenset-stylesheets/utrecht`
- AND the previous set's run MUST be gone
- AND the `custom-overrides.css` link MUST have been re-requested so the just-written overrides apply
- AND the inline variables the dialog's live preview wrote on `<html>` MUST have been removed, so
  the real stylesheets decide

#### Scenario: Theming sync follows
- GIVEN the applied set carries a `theming` object
- WHEN the apply dialog has completed
- THEN the theming-sync dialog MUST open (theming-sync-dialog spec)

#### Scenario: Publish mode
- GIVEN the dialog was opened from Publish for an active preview
- WHEN it is confirmed
- THEN `POST /settings/preview/publish` MUST be called instead of `POST /settings/tokenset`
- AND the page MUST be left carrying the published set and no preview state
- AND the theming-sync dialog MUST follow as above

#### Scenario: Module absent
- GIVEN `js/lib/layerSwap.js` did not load
- WHEN the dialog is confirmed
- THEN the overrides and the set MUST still be saved
- AND the notification MUST fall back to asking for a reload, never claim the page was updated
