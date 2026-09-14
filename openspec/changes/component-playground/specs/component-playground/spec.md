# Spec delta: Component Playground (component-playground)

A new capability. The playground is the visual surface for judging a theme: every component
Thematiq styles, rendered inside the real Nextcloud shell, with the variables it reads beside
it, editable and saveable through the file the token editor already writes.

## ADDED Requirements

### Requirement: The Playground Is An Admin-Only Page
The playground MUST be served as a page at `/apps/thematiq/playground`, guarded as an admin
setting, and MUST be reachable from the settings section and from the token-set apply dialog.
It MUST NOT be a modal, because the header, app navigation, app sidebar and login card are
the surfaces a theme changes most visibly and a modal can show none of them.

#### Scenario: An admin opens the playground
- GIVEN an authenticated administrator
- WHEN they open `/apps/thematiq/playground`
- THEN the page MUST render inside the normal Nextcloud shell
- AND the surrounding header and navigation MUST be painted by the active token set's own
  stylesheets, not by markup the page supplies

#### Scenario: A non-admin is refused
- GIVEN an authenticated user who is not an administrator
- WHEN they request `/apps/thematiq/playground`
- THEN the request MUST be refused the same way the admin settings section refuses it

### Requirement: Every Section Lists The Variables Its Component Reads
Each component section MUST be followed by a collapsed disclosure listing the variables that
component actually reads. Each entry MUST show the Nextcloud variable name, its current
computed value, and the `--nldesign-*` token it derives from; when the active set has a
conversion report, it MUST also show the source NLDS token.

The listing MUST come from data (`js/playground/components.json`), and every variable it
names MUST exist: Nextcloud variables in the token registry, `--nldesign-*` names in the
design system's defaults. A panel that names a variable which does not exist is a panel that
lies about what the component reads.

#### Scenario: A variable panel reflects the live page
- GIVEN the playground with any token set active
- WHEN the admin expands a component's variable panel
- THEN each listed variable MUST show the value currently computed on the page
- AND each MUST name the `--nldesign-*` token it comes from

#### Scenario: The inventory cannot name a variable that does not exist
- GIVEN the component inventory data
- WHEN the inventory test runs
- THEN every listed Nextcloud variable MUST be present in the token registry
- AND every listed `--nldesign-*` name MUST be declared in the design system defaults

### Requirement: Derived Variables Are Shown Locked, Not Editable
Variables Nextcloud computes from other values — the primary-element and primary-light
families, every `*-rgb` variable, and the main-background family — MUST be listed with their
computed value and a lock, and MUST NOT offer an editor. Where a reason code exists for why
the value cannot be taken from the theme, the panel MUST show it.

#### Scenario: A derived variable offers no editor
- GIVEN a component whose panel lists a Nextcloud-derived variable
- WHEN the admin expands that panel
- THEN the variable MUST render with its computed value and a lock
- AND no editor MUST be offered for it

### Requirement: Editing Applies To The Page Immediately
Editing a variable MUST set it inline on the document element with the `important` priority,
so the real component repaints at once over the design system's own `!important` rules. The
page MUST show how many edits are unsaved, and MUST offer Save and Discard. Discard MUST
remove the inline values and restore every editor to the computed value.

#### Scenario: An edit repaints the real component
- GIVEN the playground with a component section visible
- WHEN the admin changes one of that component's colour variables
- THEN the rendered component MUST take the new value without a reload
- AND the unsaved count MUST increase by one

#### Scenario: Discard returns the page to the saved theme
- GIVEN one or more unsaved edits
- WHEN the admin discards them
- THEN every inline value MUST be removed
- AND every editor MUST show the value computed from the active set again

### Requirement: Saving Writes The Same File As The Token Editor
Save MUST merge the edits into the custom overrides stylesheet through the existing overrides
endpoint, so the settings page's token editor and the playground write one file. After the
save the client MUST re-request that stylesheet and MUST remove the inline values only once
the new sheet has loaded, so the page never flashes the previous value. No reload.

#### Scenario: A saved edit survives navigation
- GIVEN an edit saved in the playground
- WHEN the admin opens any other page of the instance
- THEN the saved value MUST be in effect there

#### Scenario: Both surfaces edit one file
- GIVEN the same variable edited to the same value in the playground and in the settings
  page's token editor
- THEN both MUST produce the same request body to the overrides endpoint

### Requirement: The Playground Exports A Complete Token Set
The playground MUST offer the active set plus any unsaved edits as a downloadable flat
`:root { }` block of `--nldesign-*` declarations, sorted by name — the shape the shipped token
sets and the custom-set upload already accept. The export MUST be a complete set, not a diff,
so that the vocabulary audit can rate it.

#### Scenario: Exporting with no edits round-trips
- GIVEN a token set active and no unsaved edits
- WHEN the admin exports
- THEN the downloaded file MUST be rated by the vocabulary audit exactly as the active set is

### Requirement: Comparison Controls
The playground MUST carry the settings page's token-set switcher, with the same preview
semantics and the same stylesheet swap, and a dark-mode toggle for the page, so one set can
be judged in both colour schemes without leaving the page. A left rail MUST list the sections
with a filter and a toggle that narrows the list to sections the active set actually changes.

#### Scenario: Switching sets repaints without navigating
- GIVEN the playground open on one token set
- WHEN the admin selects another set and confirms
- THEN every rendered component MUST take the new set's values
- AND the browser MUST NOT navigate

### Requirement: Conversion Notes Appear Only When They Exist
When the active set has a conversion report, each section MUST show the reasons that apply to
its own variables. When the active set has no report, no notes MUST be shown. The playground
MUST NOT invent an explanation for a value it cannot trace.

#### Scenario: A set with no report shows no notes
- GIVEN a shipped set that was never produced by the converter
- WHEN the admin expands any section
- THEN no conversion note MUST be shown for it
