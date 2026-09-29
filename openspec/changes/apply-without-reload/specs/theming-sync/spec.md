# Spec delta: Theming Sync (apply-without-reload)

One requirement is widened. A synced background colour used to be invisible: core paints its
default background image over the plain colour until `backgroundMime` says `backgroundColor`, which
is what the panel's own "Remove background image" action writes. The README told admins to press
that button by hand after a sync. The sync now writes it.

## MODIFIED Requirements

### Requirement: Apply Colors to Nextcloud Theming
The app MUST apply validated color values to Nextcloud's `ThemingDefaults` service, and when it
applied a `background_color` MUST also set `backgroundMime` to `backgroundColor` unless the same
request carries a `background` image (whose mime `applyImages()` writes afterwards).

#### Scenario: Primary color applied
- GIVEN a valid request with `primary_color: "#004699"`
- WHEN `applyColors()` is called
- THEN `ThemingDefaults::set('primary_color', '#004699')` MUST be called
- AND `"primary_color"` MUST appear in the list of updated fields

#### Scenario: Background color applied clears the default background image
- GIVEN a valid request with `background_color: "#FFFFFF"` and no `background`
- WHEN `applyColors()` is called
- THEN `ThemingDefaults::set('background_color', '#FFFFFF')` MUST be called
- AND `ThemingDefaults::set('backgroundMime', 'backgroundColor')` MUST be called
- AND `"background_color"` MUST appear in the list of updated fields

#### Scenario: Background color with a background image in the same request
- GIVEN a valid request with `background_color: "#FFFFFF"` and `background: "img/backgrounds/x.jpg"`
- WHEN `applyColors()` is called
- THEN `backgroundMime` MUST NOT be set to `backgroundColor` by `applyColors()`
- AND `applyImages()` MUST set it to the image's detected mime type

#### Scenario: Multiple colors applied simultaneously
- GIVEN a valid request with both `primary_color: "#004699"` and `background_color: "#FFFFFF"`
- WHEN `applyColors()` is called
- THEN both colors MUST be applied via `ThemingDefaults::set()`
- AND both keys MUST appear in the updated list

#### Scenario: Empty color ignored
- GIVEN a request where `primary_color` is empty or not set
- WHEN `applyColors()` is called
- THEN `ThemingDefaults::set()` MUST NOT be called for `primary_color`
- AND `"primary_color"` MUST NOT appear in the updated list

## ADDED Requirements

### Requirement: Stock Nextcloud Resets Core Theming
Selecting the stock set (design system `none`) MUST offer to reset Nextcloud theming to its
defaults, not to "match" the stock set's manifest values. The app MUST expose the reset as `POST /settings/theming` with `reset=1` (admin-only, the same
endpoint and therefore the same route entry — a new path or verb 404s/405s on any instance whose
route collection is still cached, which is an hour by default and was measured turning a
successful switch into a "failed to apply" toast). It calls `ThemingDefaults::undo()` for
`primary_color`, `background_color`, `logo` and `background` — the same call core's own undo
arrows make, which deletes the value and, for an image slot, the stored image and its `{key}Mime`
— and logs `theming_sync_reset`. The `GET /settings/theming` snapshot MUST carry
`default_primary_color` and `default_background_color` (core's `BackgroundService` constants, not
`getDefaultColorPrimary()`, which returns the admin-configured value a reset removes) so the dialog
can show what "default" is before applying it.

#### Scenario: Reset undoes a synced logo
- GIVEN OpenWOO was synced: `primary_color=#23845c`, `logoMime=image/svg+xml`, a stored logo
- WHEN `POST /settings/theming` is called with `reset=1`
- THEN `primary_color`, `background_color`, `logoMime`, `backgroundMime` MUST be unset
- AND `ImageManager::hasImage('logo')` MUST be false
- AND `/apps/theming/theme/default.css` MUST carry `--color-primary:#00679e`
- AND the response MUST be `{status: "ok", reset: ["primary_color","background_color","logo","background"]}`

#### Scenario: Reset is idempotent
- GIVEN nothing is configured in core theming
- WHEN `POST /settings/theming` is called with `reset=1`
- THEN the response MUST still be `ok` and nothing MUST fail
