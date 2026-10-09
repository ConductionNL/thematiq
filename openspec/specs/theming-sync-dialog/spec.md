---
status: done
---

# Theming Sync Dialog Specification

## Purpose
After an admin selects a different token set in nldesign, offer to automatically update Nextcloud's built-in theming values (primary color, background color, logo, background image) to match the selected token set, preventing a split-brain theming state where CSS tokens and Nextcloud theming are out of sync.

## Requirements

### Requirement: Theming Metadata in Token Sets
The system MUST support an optional `theming` object on each token set entry in `token-sets.json`, with optional fields: `primary_color`, `background_color`, `logo`, and `background`.
@e2e exclude API response structure assertion (GET /settings/tokensets) — backend/JSON validation, not testable via browser UI.

#### Scenario: Token set with full theming metadata
- GIVEN a token set entry in `token-sets.json` has a `theming` object with `primary_color`, `background_color`, `logo`, and `background` fields
- WHEN the token sets are loaded via `GET /settings/tokensets`
- THEN all theming fields SHALL be included in the response for that token set

#### Scenario: Token set without theming metadata
- GIVEN a token set entry in `token-sets.json` has no `theming` object
- WHEN the token sets are loaded via `GET /settings/tokensets`
- THEN the `theming` field SHALL be absent from the response for that token set

#### Scenario: Token set with partial theming metadata
- GIVEN a token set entry has a `theming` object with only `primary_color` defined
- WHEN the token sets are loaded
- THEN only `primary_color` SHALL be included in the `theming` response
- AND absent fields SHALL NOT appear (no null values)

### Requirement: Get Current Theming Values Endpoint
The system MUST provide a `GET /settings/theming` endpoint that returns the current Nextcloud theming values for comparison in the dialog.
@e2e exclude API endpoint assertion (GET /settings/theming response structure, 403 for non-admin) — not testable via browser UI.

#### Scenario: Retrieve current theming values
- GIVEN the admin is authenticated
- WHEN `GET /settings/theming` is called
- THEN the response SHALL include `primary_color`, `background_color`, `logo_url`, `background_url`, `has_custom_logo`, and `has_custom_background`
- AND color values SHALL be hex strings
- AND image URLs SHALL be absolute paths to the current NC theming images

#### Scenario: Unauthenticated access denied
- GIVEN the requester is not an admin
- WHEN `GET /settings/theming` is called
- THEN the response SHALL be a 403 status

### Requirement: Update Theming Values Endpoint
The system MUST provide a `POST /settings/theming` endpoint that updates Nextcloud's built-in theming values.
@e2e exclude API endpoint assertions (POST /settings/theming, 400/413 error responses, file upload validation) — backend validation, not testable via browser UI; would mutate shared NC theming.

#### Scenario: Update colors only
- GIVEN the admin sends `{ "primary_color": "#003865", "background_color": "#003865" }`
- WHEN `POST /settings/theming` is called
- THEN Nextcloud's primary color and background color SHALL be updated to the provided values
- AND the theming cachebuster SHALL be incremented
- AND the response SHALL list `["primary_color", "background_color"]` in the `updated` array

#### Scenario: Update logo
- GIVEN the admin sends `{ "logo": "img/logos/vng.svg" }`
- WHEN `POST /settings/theming` is called
- THEN the file at the given path within nldesign's app directory SHALL be uploaded as Nextcloud's logo via ImageManager
- AND the response SHALL include `"logo"` in the `updated` array

#### Scenario: Update background image
- GIVEN the admin sends `{ "background": "img/backgrounds/vng.jpg" }`
- WHEN `POST /settings/theming` is called
- THEN the file at the given path within nldesign's app directory SHALL be uploaded as Nextcloud's background via ImageManager
- AND the response SHALL include `"background"` in the `updated` array

#### Scenario: Invalid hex color rejected
- GIVEN the admin sends `{ "primary_color": "not-a-color" }`
- WHEN `POST /settings/theming` is called
- THEN the response SHALL be a 400 status with an error message
- AND no theming values SHALL be changed

#### Scenario: Path traversal rejected
- GIVEN the admin sends `{ "logo": "../../etc/passwd" }`
- WHEN `POST /settings/theming` is called
- THEN the response SHALL be a 400 status with an error message
- AND no files SHALL be uploaded

#### Scenario: Non-existent image rejected
- GIVEN the admin sends `{ "logo": "img/logos/nonexistent.svg" }`
- WHEN `POST /settings/theming` is called
- THEN the response SHALL be a 400 status with an error message
- AND no files SHALL be uploaded

### Requirement: Confirmation Dialog After Token Set Change
The system MUST display the confirmation dialog after a token set with theming metadata has been
saved, whichever path saved it: the apply dialog's confirm (token diffs present), `saveTokenSet()`
(no token diffs), and publish mode for both. It MUST show only the fields that differ.

#### Scenario: Dialog reached after the apply dialog
- GIVEN the admin selects a set with theming metadata whose tokens differ from the current values
- WHEN the apply dialog opens and the admin confirms it
- THEN the overrides MUST be written, the set saved and applied to the page
- AND the theming-sync dialog MUST then open for the same set
- AND no page reload MUST occur between the two

#### Scenario: Dialog reached from publish
- GIVEN an active preview of a set with theming metadata
- WHEN the admin publishes and confirms the apply dialog
- THEN the theming-sync dialog MUST open for the published set

#### Scenario: Dialog shown for token set with theming metadata
- GIVEN the admin selects a token set that has a `theming` object and no token diffs
- WHEN the token set is saved successfully
- THEN a dialog SHALL appear showing the current and proposed theming values
- AND only fields that differ between current and proposed SHALL be displayed

#### Scenario: Dialog not shown for token set without theming metadata
@e2e exclude Requires selecting a token set AND verifying dialog absence — selection mutates IConfig token_set; non-appearance of dialog cannot be safely verified without saving.
- GIVEN the admin selects a token set without a `theming` object
- WHEN the token set is saved successfully
- THEN no dialog SHALL appear
- AND the token set change SHALL complete normally

#### Scenario: Dialog not shown when values already match
@e2e exclude Requires specific IConfig state where token-set theming already matches NC theming — not deterministic in shared env.
- GIVEN the admin selects a token set whose theming values already match Nextcloud's current values
- WHEN the token set is saved successfully
- THEN no dialog SHALL appear

### Requirement: Dialog Preview Boxes
The confirmation dialog MUST display Nextcloud-style theming preview boxes showing the visual effect of the proposed changes.
@e2e exclude Dialog-internal rendering — only verifiable after triggering the dialog via token-set save, which mutates IConfig and NC theming; safe-to-trigger path not available.

#### Scenario: Current preview reflects active theming
- GIVEN the dialog is displayed
- WHEN the "Current" preview box is rendered
- THEN it SHALL show Nextcloud's current background color as the box background
- AND if a custom background image exists, it SHALL be shown
- AND the current logo SHALL be overlaid in the center

#### Scenario: Proposed preview reflects token set theming
- GIVEN the dialog is displayed for a token set with theming metadata
- WHEN the "Proposed" preview box is rendered
- THEN it SHALL show the token set's `background_color` as the box background
- AND if the token set has a `background` image, it SHALL be shown
- AND if the token set has a `logo`, it SHALL be overlaid in the center

### Requirement: Dialog User Actions
The dialog MUST provide Cancel and Update actions. Confirming MUST refresh the page's styles in
place rather than reloading.

#### Scenario: User confirms update
- GIVEN the dialog is displayed
- WHEN the admin clicks "Update theming"
- THEN `POST /settings/theming` SHALL be called with all differing theming values
- AND the page SHALL re-request core theming's `/apps/theming/theme/*.css` stylesheets (a new `v=`)
  so the synced primary, background and logo take effect on the current page
- AND the core Theming panel's fields on the same page SHALL show the synced values (via core's
  `data-admin-theming-setting-primary-color`, `-background-color`, `-preview-logo` hooks)
- AND a success notification SHALL be shown
- AND the page MUST NOT reload

#### Scenario: User cancels update
- GIVEN the dialog is displayed
- WHEN the admin clicks "Cancel"
- THEN no theming values SHALL be changed
- AND the token set SHALL remain applied to the page (only Nextcloud theming is skipped)

### Requirement: Bundled Organization Images
Organization logos and background images MUST be stored as static files within the nldesign app directory.
@e2e exclude Filesystem assertions (file existence, valid image type) — not testable via browser UI.

#### Scenario: Logo file stored correctly
- GIVEN a token set has `"logo": "img/logos/vng.svg"` in its theming metadata
- WHEN the file path is resolved
- THEN the file SHALL exist at `nldesign/img/logos/vng.svg`
- AND it SHALL be a valid image file (SVG, PNG, JPG, or WebP)

#### Scenario: Background file stored correctly
- GIVEN a token set has `"background": "img/backgrounds/vng.jpg"` in its theming metadata
- WHEN the file path is resolved
- THEN the file SHALL exist at `nldesign/img/backgrounds/vng.jpg`
- AND it SHALL be a valid image file (PNG, JPG, WebP)

### Requirement: The Stock Set Offers A Reset, Not A Match
When the saved set's design system is `none` (stock Nextcloud), the dialog MUST propose
Nextcloud's defaults — `default_primary_color`, `default_background_color`, core's own logo — with
the title "Reset Nextcloud theming to its defaults?", MUST list only the values currently
customised, and its confirm MUST call `POST /settings/theming` with `reset=1`. It MUST NOT be shown when nothing
in core theming is customised. It MUST NOT show the current logo as the proposed one: the stock
set has no logo, and "unchanged" is wrong for stock.

#### Scenario: Switching from OpenWOO to stock
- GIVEN OpenWOO's colours and logo were synced and the admin selects `nextcloud`
- WHEN the apply dialog is confirmed
- THEN the reset dialog SHALL open, showing OpenWOO's wordmark under "Current" and Nextcloud's
  logo under "Proposed", with rows for primary colour, background colour and logo
- AND confirming SHALL undo all of them and re-request core's theme stylesheets, so the header
  shows Nextcloud's logo and `#00679e` without a reload

#### Scenario: Nothing to reset
- GIVEN core theming has no colours and no custom images
- WHEN the admin selects `nextcloud`
- THEN no dialog SHALL open

### Requirement: The Sync Rides On The Apply Confirm
When the apply dialog is shown, the theming sync MUST be a section of that dialog — a checkbox,
checked by default, reading "Also update Nextcloud theming (login page, e-mails, mobile apps)"
(or "Also reset Nextcloud theming to its defaults …" for the stock set) above the same
Setting / Current / Proposed rows the standalone dialog shows — and confirming the apply dialog
MUST run the sync when the box is checked. A second modal MUST NOT open after the apply dialog.
The standalone dialog MUST remain for the path where the apply dialog has no token changes to
show, where it is the only dialog. Both surfaces MUST derive their rows and their request from
one shared computation so they cannot disagree.

#### Scenario: Switching sets with token changes
- GIVEN the admin selects OpenWOO while core theming is stock
- WHEN the apply dialog opens
- THEN it SHALL show the token rows AND a checked "Also update Nextcloud theming" section listing
  primary colour, background colour and logo
- AND confirming SHALL write the overrides, save and swap the set, then `POST /settings/theming`
  with those values, then refresh core's stylesheets — with no further dialog

#### Scenario: Admin unticks the section
- GIVEN the apply dialog is open with the theming section checked
- WHEN the admin unticks it and confirms
- THEN the set SHALL be applied and core theming SHALL be left untouched

#### Scenario: Switching to stock with token changes
- GIVEN OpenWOO's colours and logo are synced and the admin selects `nextcloud`
- WHEN the apply dialog opens
- THEN the section SHALL read "Also reset Nextcloud theming to its defaults …" with rows for the
  customised values
- AND confirming SHALL call `POST /settings/theming` with `reset=1` after the swap

#### Scenario: No token changes
- GIVEN the token preview reports nothing to change
- WHEN the set is saved
- THEN the standalone sync (or reset) dialog SHALL open as before — the only dialog shown

### Requirement: Only Real Differences Are Offered
A value MUST appear in the sync only when applying it would change something. For colours that is
a case-insensitive comparison; for the two IMAGE slots core records only THAT a custom image
exists (`{key}Mime`), never which file, so the app MUST record the path it applied
(`synced_logo`, `synced_background` app values, cleared by a reset) and compare against that. When
nothing differs, no dialog and no section MUST appear.

#### Scenario: Re-applying a set that is already synced
- GIVEN a set whose primary, background and logo are already in core theming
- WHEN it is selected again
- THEN no theming dialog and no theming section MUST appear
- AND no request MUST be made to `POST /settings/theming`

#### Scenario: A set whose logo is not the one in place
- GIVEN `synced_logo` is `img/logos/amsterdam.svg` and the selected set ships
  `img/logos/custom-openwoo.svg`
- WHEN the set is applied
- THEN the logo MUST be offered

#### Scenario: A reset forgets what was synced
- GIVEN a logo was synced and `POST /settings/theming` with `reset=1` is called
- THEN `synced_logo` MUST be cleared, so the next set's logo is offered again

### Requirement: Core's Colour Pickers Show The Synced Colour
Writing synced values into core's Theming panel MUST change what the colour pickers DISPLAY, not
only their labels. Core's `ColorPickerField.vue` binds the button's `background-color` and `color`
through `v-bind()`, which its build compiles to hash-named custom properties written inline on the
field root; the app MUST rebind those properties, discovering their names at runtime (the hash
changes with every Nextcloud build), and MUST set the text colour by the same
perceived-brightness rule core uses, so a light colour does not leave white text on white.

#### Scenario: Primary picker after a sync
- GIVEN core's primary picker shows `#00679e` on a blue button
- WHEN `#23845c` is synced
- THEN the button's computed `background-color` MUST be `rgb(35, 132, 92)`
- AND its computed `color` MUST be white, and its label `#23845c`

#### Scenario: A light colour keeps its label legible
- GIVEN `#ffffff` is synced to the background picker
- THEN the button's computed `color` MUST be black

#### Scenario: After a reset
- GIVEN the values were deleted by a reset, so the snapshot returns empty strings
- THEN the pickers MUST show core's defaults (`default_primary_color`), not blank

### Requirement: Preview Logos Fit Their Box
The `Current` and `Proposed` preview images MUST be constrained to their preview box
(`max-height` and `max-width`, aspect preserved), so an SVG with a large intrinsic size cannot
fill the dialog.

#### Scenario: A wide wordmark stays inside its preview box
@e2e exclude A rule on one class in css/admin.css (`.nldesign-dialog-preview-logo`: max-width 70%, max-height 56px, object-fit contain); no assertion can tell a fitted logo from a cropped one without a visual baseline. Seen live on 9 Oct 2026 on a throwaway instance: switching from vng to leiden, the dialog drew the VNG logo inside its 56px box.
- GIVEN the current logo is an SVG with a 1369 by 436 viewBox
- WHEN the sync dialog shows it as `Current`
- THEN the image MUST be no taller than its box and no wider than 70% of it
- AND its aspect ratio MUST be kept
