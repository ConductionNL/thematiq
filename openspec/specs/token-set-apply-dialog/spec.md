---
status: done
---

# Token-Set Apply Dialog Specification

## Purpose
Defines the modal dialog shown when an admin selects a new NL Design token set. The dialog shows which Nextcloud CSS variable values would change (resolved current value vs the value from the new token set), lets the admin check or uncheck individual changes, and writes only the checked values to `custom-overrides.css`. The NL Design token set CSS file itself is never applied directly.

## Requirements

### Requirement: Dialog Trigger
Selecting a different NL Design token set from the selector MUST open the apply dialog instead of immediately switching themes.

#### Scenario: Admin selects a new token set
- GIVEN the current token set is "rijkshuisstijl"
- WHEN the admin selects "utrecht" from the token-set dropdown
- THEN the apply dialog MUST open before any CSS change takes effect
- AND the dropdown MUST NOT visually change to "utrecht" until the admin confirms or cancels

#### Scenario: Admin selects the same token set that is already active
@e2e exclude Browser <select> onChange does not fire when the same option is re-selected — the behaviour is inherent to the browser and does not require a UI assertion.
- GIVEN the current token set is "utrecht"
- WHEN the admin selects "utrecht" again
- THEN the apply dialog MUST NOT open
- AND no change MUST occur

### Requirement: Resolved Value Comparison
The dialog MUST compare the resolved current values — what the browser is actually rendering — against the values the new token set would contribute. Only tokens where the values differ MUST be shown.

#### Scenario: Dialog shows only changed tokens
- GIVEN switching from rijkshuisstijl to utrecht would change 8 out of 44 tokens
- WHEN the apply dialog opens
- THEN exactly 8 token rows MUST be shown
- AND the 36 unchanged tokens MUST NOT appear in the dialog

#### Scenario: Current value is from custom-overrides.css
@e2e exclude Requires known custom-overrides.css content — environment state not guaranteed.
- GIVEN `custom-overrides.css` sets `--color-primary: #AA0000`
- AND the new token set (utrecht) would contribute `--color-primary: #CC0000` (via the --nldesign-* mapping chain)
- WHEN the dialog opens
- THEN the current column MUST show `#AA0000` (the actual resolved value)
- AND the new column MUST show `#CC0000`

#### Scenario: Resolved value is obtained from CSS custom property API
@e2e exclude Internal JS implementation detail (getComputedStyle) — not testable via DOM.
- GIVEN the admin has the settings page open
- WHEN the dialog opens
- THEN the "current" values MUST be read using `getComputedStyle(document.documentElement).getPropertyValue('--color-X')`
- AND NOT from `custom-overrides.css` or any server-side source

### Requirement: Checkbox Selection
Every token row in the dialog MUST have a checkbox. All checkboxes MUST be checked by default. The admin can uncheck rows they do not want to apply.

#### Scenario: All changes selected by default
- GIVEN the dialog opens with 8 changed tokens
- WHEN the dialog first renders
- THEN all 8 checkboxes MUST be checked

#### Scenario: Admin unchecks a row
@e2e exclude Requires dialog + live preview interaction and verifying custom-overrides.css content — covered by dialog-cancel flow in token-set-apply-dialog spec-coverage.
- GIVEN the dialog is open
- WHEN the admin unchecks the row for `--color-primary`
- THEN `--color-primary` MUST be excluded from the values written to `custom-overrides.css`
- AND the browser preview MUST NOT change for `--color-primary` (it keeps its current value)

#### Scenario: Select all / Deselect all
- GIVEN the dialog is open with multiple rows
- WHEN the admin clicks a "Select all" or "Deselect all" toggle
- THEN all checkboxes MUST be set to checked or unchecked respectively

### Requirement: Live Preview in Dialog
Checked rows MUST update the live page preview immediately as the admin checks and unchecks them, so they can see the effect before confirming.
@e2e exclude Live preview via inline style injection — requires specific token values to assert; checking/unchecking interactions covered indirectly by checkbox-presence tests.

#### Scenario: Admin checks a row
- GIVEN the dialog is open and `--color-primary` row is checked
- WHEN the admin previews the effect
- THEN the page behind the dialog MUST show `--color-primary` in the new token-set color
- AND this preview MUST be applied via inline style injection, not by writing `custom-overrides.css`

#### Scenario: Admin unchecks a row
- GIVEN `--color-primary` was previewing the new value
- WHEN the admin unchecks the `--color-primary` row
- THEN the live preview MUST revert `--color-primary` to its current resolved value

#### Scenario: Dialog closed with Cancel
- GIVEN the dialog is open with some checked rows and live preview applied
- WHEN the admin clicks Cancel
- THEN ALL preview style injections MUST be removed
- AND the page MUST return to its previous appearance
- AND `custom-overrides.css` MUST remain unchanged
- AND the token-set dropdown MUST remain on its previous value

### Requirement: Apply Action
Clicking **Apply** MUST write only the checked token values to `custom-overrides.css` and close the dialog.
@e2e exclude Clicking Apply POSTs to /api/overrides and changes IConfig token_set — mutates shared env custom-overrides.css and active token set.

#### Scenario: Admin applies selected changes
- GIVEN 8 tokens are shown and 6 are checked
- WHEN the admin clicks Apply
- THEN a POST to `/api/overrides` MUST be made with the 6 checked token/value pairs merged with any existing `custom-overrides.css` entries
- AND the server MUST write the merged result to `custom-overrides.css`
- AND the dialog MUST close
- AND the token editor forms MUST reflect the newly written values

#### Scenario: Applied values appear in editor forms
- GIVEN the admin applied values from the utrecht token set
- WHEN the token editor tabs are viewed after the dialog closes
- THEN each applied token MUST show the new value in its input
- AND each applied token MUST show the "customized" indicator

#### Scenario: Apply merges with existing custom overrides
- GIVEN `custom-overrides.css` already contains `--color-error: #b30000`
- AND the apply dialog writes `--color-primary: #CC0000`
- WHEN the backend writes the new `custom-overrides.css`
- THEN the file MUST contain BOTH `--color-error: #b30000` AND `--color-primary: #CC0000`
- AND no pre-existing custom override MUST be lost

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
