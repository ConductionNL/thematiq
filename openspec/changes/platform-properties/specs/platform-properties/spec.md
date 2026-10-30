# platform-properties Specification (delta)

## Purpose

What Nextcloud server provides around thematiq, and how thematiq composes with it: the core image slots, the domain, the legal links, the product name, the personal theme choice, admin delegation and the app store. Covers rows ast-upload-logo, ast-upload-background, ast-favicon, sur-custom-domain, sur-footer-links, sur-web-app-name, sco-user-choice, gov-delegated-role and gov-app-store-install.

## ADDED Requirements

### Requirement: An uploaded core logo wins over the set's logo (REQ-TPLAT-001)

Nextcloud core's Theming page SHALL remain the place where an administrator uploads a logo. thematiq SHALL NOT offer its own logo upload for the workspace header. When core holds an uploaded logo (`theming.logoMime` or `theming.logoheaderMime` is set), thematiq SHALL show that logo as it is and SHALL NOT replace it with the active token set's logo.

#### Scenario: An administrator uploaded a logo in core
- GIVEN an administrator uploaded a logo on core's Theming page
- AND the active token set ships a logo of its own
- WHEN a user opens any page
- THEN the header shows the uploaded logo

#### Scenario: No logo was uploaded
- GIVEN core holds no uploaded logo
- WHEN a user opens any page with a token set that ships a logo
- THEN the header shows the token set's logo

### Requirement: The login background is a core image slot (REQ-TPLAT-002)

Nextcloud core SHALL own the background image slot and its upload. thematiq SHALL write a background into that slot only through core's image manager, and only when an administrator applies a set through the theming sync. A reset to stock Nextcloud SHALL undo the background through core's own undo.

#### Scenario: A set with a background is synced
- GIVEN the active token set ships a background image
- WHEN the administrator confirms the theming sync
- THEN core's background slot holds that image and core's Theming page shows it

#### Scenario: An administrator resets to stock Nextcloud
- GIVEN thematiq wrote a background into core
- WHEN the administrator resets to stock Nextcloud
- THEN core's background returns to its default

### Requirement: The favicon is a core image slot the theming sync may fill (REQ-TPLAT-003)

Nextcloud core SHALL own the favicon slot, its upload and the icons it serves from it. thematiq SHALL write a favicon into that slot only through core's image manager, during a theming sync the administrator confirms in the dialog drawn by TqThemingBijwerken. thematiq SHALL NOT serve a favicon of its own.

#### Scenario: The sync dialog names the favicon
- GIVEN the active token set ships a favicon
- WHEN the administrator opens the theming sync dialog
- THEN the favicon is listed among the images handed to core

#### Scenario: A favicon uploaded in core stays
- GIVEN an administrator uploaded a favicon in core
- AND no theming sync runs
- WHEN a user opens any page
- THEN the browser tab shows the uploaded favicon

### Requirement: The workspace domain is server configuration (REQ-TPLAT-004)

The domain the workspace answers on SHALL be set in Nextcloud's `config.php` (`trusted_domains`, `overwrite.cli.url`) and in hosting, not in thematiq. Every URL thematiq writes into a page, a stylesheet or an email SHALL be built through Nextcloud's `IURLGenerator`, so it carries the configured domain.

#### Scenario: The workspace moves to the organisation's domain
- GIVEN an administrator serves the workspace on `werkplek.example.nl`
- WHEN a user opens a page with a token set that has a logo
- THEN every URL thematiq emitted on that page points at `werkplek.example.nl`

### Requirement: Legal links are core Theming fields (REQ-TPLAT-005)

The imprint and privacy statement links in the footer and on the login page SHALL be core Theming fields. thematiq SHALL NOT hide or restyle them out of view. The email footer links thematiq adds (privacy and accessibility statement) SHALL be thematiq settings and SHALL NOT change core's fields.

#### Scenario: A privacy link is set in core
- GIVEN an administrator set a privacy statement URL on core's Theming page
- WHEN a visitor opens the login page with any thematiq token set active
- THEN the privacy statement link is visible

### Requirement: Name, slogan and web address are core Theming fields (REQ-TPLAT-006)

The product name, slogan and web address SHALL be set on core's Theming page. thematiq SHALL NOT change their values. thematiq's own slogan setting SHALL only hide the slogan on the login page and SHALL leave core's stored slogan unchanged.

#### Scenario: The slogan is hidden
- GIVEN core holds the slogan "Samen werken"
- WHEN the administrator turns on hiding the slogan in thematiq
- THEN the login page shows no slogan
- AND core's Theming page still shows "Samen werken"

### Requirement: Each user's theme choice comes from core (REQ-TPLAT-007)

Each user SHALL choose light, dark or high contrast in core's personal Appearance settings. thematiq SHALL follow that choice: its dark values SHALL apply when the user chose a dark theme, or chose none and the operating system asks for dark. thematiq SHALL NOT add a personal theme picker of its own.

#### Scenario: A user picks dark
- GIVEN the active token set has dark values
- WHEN a user chooses the dark theme in core's Appearance settings
- THEN that user's pages use the set's dark values
- AND other users keep their own choice

### Requirement: Theming can be delegated through core's admin delegation (REQ-TPLAT-008)

thematiq's administration section SHALL implement Nextcloud's `IDelegatedSettings`, so an administrator can grant a group access to it through core's "Administration privileges". The delegated section SHALL write only the app config keys `Admin::getAuthorizedAppConfig()` lists, and SHALL NOT list keys that only background jobs write.

#### Scenario: A brand manager edits the theme
- GIVEN an administrator delegated thematiq's section to the group "Communicatie"
- WHEN a member of "Communicatie" who is not an administrator picks another token set
- THEN the choice is saved and applies to every user

#### Scenario: A delegated user cannot write a job-internal key
- GIVEN a delegated user
- WHEN that user posts a value for an upstream freshness ETag key
- THEN Nextcloud refuses the write

### Requirement: thematiq installs and updates from the Nextcloud app store (REQ-TPLAT-009)

thematiq SHALL be published in the Nextcloud app store under the id `thematiq`, in the category `customization`, under the EUPL-1.2 licence, with the Nextcloud versions it supports declared in `appinfo/info.xml`. An administrator SHALL be able to install and update it from the Apps page without a shell.

#### Scenario: An administrator installs thematiq
- GIVEN a Nextcloud server within the declared version range with app store access
- WHEN an administrator clicks "Download and enable" for thematiq on the Apps page
- THEN thematiq is enabled and its section appears on the Theming administration page
