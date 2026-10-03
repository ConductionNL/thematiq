# lasuite-stack: delta

Requirements added by the `lasuite-shell-geometry` change.

## ADDED Requirements

### Requirement: La Suite Shell Geometry Layer

The `lasuite` design system SHALL reshape the Nextcloud shell to La Suite Docs' geometry, measured
from the La Suite Docs source (suitenumerique/docs at 9de17b3f820a28ef159e29a372c84fecd6fc45eb):
a 64px header row (`HEADER_ROW_MIN_HEIGHT`), a 300px left panel and a full-width main area.
The geometry SHALL be set through Nextcloud's own `--header-height` variable, so the header, the
app menu triggers, the content offset and `--body-height` move together. The shared La Suite
overrides SHALL derive every header-dependent offset from `--header-height` rather than from a
fixed pixel value. The `cunningham` sibling and every other design system SHALL keep the stock
Nextcloud header height.

#### Scenario: On Nextcloud 35 the lasuite header row is 64px tall

- **GIVEN** the `lasuite` token set is active on a Nextcloud 35 server
- **WHEN** an admin opens any page
- **THEN** `#header` renders 64px tall

#### Scenario: The content starts directly under the taller header

- **GIVEN** the `lasuite` token set is active on a Nextcloud 35 server
- **WHEN** an admin opens the Files app
- **THEN** the top edge of `#content-vue` equals the bottom edge of `#header`, with no gap and no overlap

#### Scenario: The search field stays centred in the header

- **GIVEN** the `lasuite` token set is active
- **WHEN** an admin opens a page that shows the unified search field
- **THEN** the vertical centre of the search field equals the vertical centre of `#header`, within 1px
- **AND** the search field renders 34px tall, its 1px borders included

#### Scenario: The app navigation keeps La Suite Docs' 300px width

- **GIVEN** the `lasuite` token set is active
- **WHEN** an admin opens the Files app at a desktop viewport
- **THEN** the app navigation renders 300px wide

#### Scenario: The Cunningham sibling keeps the stock header height

- **GIVEN** the `cunningham` token set is active
- **WHEN** an admin opens any page
- **THEN** no shell geometry stylesheet is linked
- **AND** `#header` does not render 64px tall

### Requirement: Version-Scoped Design-System Stylesheets

A design system MAY declare `versioned_stylesheets` in `design-systems.json`: stylesheets keyed by
Nextcloud major version. Such a stylesheet SHALL load only when the running server reports that
major, directly after the design system's own stylesheets and before the token layer, and SHALL
appear in the stylesheet manifest the admin panel swaps without a reload. A server whose major is
not listed SHALL load none of them, so a new Nextcloud release falls back to stock geometry
instead of overrides written for different markup.

#### Scenario: The shell layer loads exactly on the Nextcloud majors it lists

- **GIVEN** the `lasuite` token set is active
- **WHEN** an admin opens any page
- **THEN** `css/systems/lasuite/shell-nc35.css` is linked when the server major is 35
- **AND** it is not linked on any other major
