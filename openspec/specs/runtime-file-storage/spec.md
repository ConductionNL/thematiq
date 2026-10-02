# runtime-file-storage Specification

## Purpose
Keep what thematiq writes at runtime out of its app directory, so Nextcloud's code integrity check passes and an app update loses nothing an admin made.

## Requirements

### Requirement: The app directory stays as shipped
thematiq MUST NOT create, change or delete any file inside its own app directory during install, upgrade, a web request or an `occ` command.

@e2e exclude Asserted by hashing the app directory before and after a full admin session in `tests/integration/AppDirectoryUnchangedTest.php`; a browser cannot observe file hashes.

#### Scenario: A custom house style leaves the directory unchanged
- GIVEN the hash of every file in the app directory is recorded
- WHEN an admin uploads a token set with a logo and a background, applies it, saves token overrides and saves custom CSS
- THEN the hash of every file in the app directory MUST be unchanged
- AND no file MUST have been added or removed

#### Scenario: Rendering a page writes nothing
- GIVEN no overrides were ever saved
- WHEN a themed page is rendered
- THEN no file MUST be created in the app directory

#### Scenario: Install and upgrade write nothing
- GIVEN a fresh copy of the release
- WHEN the app is enabled and every repair step runs
- THEN the app directory MUST match `appinfo/signature.json`

### Requirement: Runtime files live in app data
Every file thematiq writes at runtime MUST be stored in Nextcloud's app data for thematiq.

@e2e exclude Storage location is a server-side fact; the visible result is covered by "An uploaded logo is shown".

#### Scenario: An uploaded set is stored in app data
- GIVEN an admin uploads token set `gemeente-voorbeeld`
- WHEN the upload succeeds
- THEN its stylesheet MUST be stored in thematiq's app data
- AND `css/tokens/gemeente-voorbeeld.css` MUST NOT exist in the app directory

#### Scenario: Overrides survive an app update
- GIVEN an admin saved token overrides
- WHEN the app directory is replaced by a newer release
- THEN the overrides MUST still apply on the next page render

### Requirement: Runtime files are served by route
Stylesheets and images stored in app data MUST be served by public routes that carry a revision in the URL and a long-lived cache header.

#### Scenario: An uploaded logo is shown
- GIVEN an admin applied a set with an uploaded logo
- WHEN any user opens the Files app
- THEN the header MUST show that logo
- AND the browser MUST have loaded it from thematiq's runtime file route

#### Scenario: A change gets a new URL
- GIVEN a page links the overrides stylesheet with revision 4
- WHEN the admin saves new overrides
- THEN the next page MUST link it with a different revision

#### Scenario: An unknown name is refused
@e2e exclude Path validation of a public route; asserted in PHPUnit with `../` and absolute names.
- GIVEN a request for a runtime file whose name is not one thematiq writes, such as `../../config/config.php`
- WHEN the route handles it
- THEN it MUST return 404 without reading anything outside thematiq's app data

### Requirement: Existing runtime files are moved on upgrade
An upgrade MUST move every runtime file found in the app directory into app data and remove it from the app directory.

@e2e exclude Repair-step behaviour; asserted in PHPUnit and by the integrity scenario above.

#### Scenario: An install from before this change
- GIVEN the app directory holds `css/custom-overrides.css` and `img/logos/gemeente-voorbeeld.png` from an older version
- WHEN the upgrade's repair steps run
- THEN both MUST be readable from app data
- AND neither MUST remain in the app directory

#### Scenario: A file that is already in app data is not overwritten
- GIVEN app data already holds a newer `custom-overrides.css`
- WHEN the repair step finds an older copy in the app directory
- THEN the app data copy MUST be kept
- AND the app directory copy MUST be removed

### Requirement: Shipped dark variants are never regenerated at runtime
The dark variant of a shipped token set MUST come from the release, and a test MUST fail when a committed dark variant is stale for its source.

@e2e exclude Build-time freshness check.

#### Scenario: The generator changes
- GIVEN the dark palette generator changes how it derives a colour
- WHEN the unit suite runs without regenerating the committed dark files
- THEN the freshness test MUST fail and name the stale sets

#### Scenario: Forcing regeneration touches only uploaded sets
- GIVEN an admin forces dark-variant regeneration from the command line
- WHEN it finishes
- THEN only uploaded sets MUST have new dark variants, in app data
- AND no shipped dark file MUST have changed
