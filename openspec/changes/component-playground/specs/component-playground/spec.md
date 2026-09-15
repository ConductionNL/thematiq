# Spec delta: Component Playground (component-playground)

A new capability. The playground is the visual surface for judging a theme: it turns the
theming panel's token editor into a selector, a component stage and a filtered token list, so
a value can be found by looking at the thing it paints rather than by knowing its name.

## ADDED Requirements

### Requirement: The Playground Is Part Of The Theming Panel
The playground MUST be built into the admin theming panel's token editor, not served as a
page of its own. The editor's tab strip MUST become the selector, a row of component chips
MUST sit under it, and the preview MUST gain a component stage alongside its existing app and
login views. The panel MUST keep working when the playground does not build.

#### Scenario: An admin opens the theming panel
- GIVEN an authenticated administrator
- WHEN they open the admin theming settings
- THEN the token editor's tabs MUST appear above the preview as the selector
- AND each tab MUST offer a chip per component filed under it, plus a full view

#### Scenario: The instrument cannot break the panel it is built into
- GIVEN a component inventory that is missing or unreadable
- WHEN the panel renders
- THEN the token editor MUST still render its four tabs and its full token list
- AND Save MUST still write the overrides file

### Requirement: A Component Names The Tokens It Reads
Choosing a component MUST replace the tab's full token list with the tokens that component
reads, each row carrying what it paints and a number tying it to the state on the stage that
it paints. The full list MUST remain one click away.

The listing MUST come from data (`js/playground/components.json`), every token it names MUST
exist in the token registry, and the components MUST between them reach every token the
editor can write. A chip that names a token with no editor row is a chip that offers a
control which does nothing; a token no chip reaches is one an admin can only find by
scrolling.

#### Scenario: Choosing a component filters the editor
- GIVEN the theming panel with a tab open
- WHEN the admin picks a component chip
- THEN the editor MUST list only that component's tokens
- AND each row MUST name what it paints and carry its state's number
- AND a control MUST return the full list of the tab's tokens

#### Scenario: The inventory cannot name a token that does not exist
- GIVEN the component inventory data
- WHEN the inventory test runs
- THEN every listed token MUST be present in the token registry
- AND every token in the registry MUST be reachable through at least one component

### Requirement: Rows With No Token State The Reason
A component's look may depend on something that has no token at all — a fixed opacity, a
minimum clickable area, an asset. Each MUST be listed with what it is, no editor, and a
reason code from the conversion vocabulary, so the panel and an import report explain the same
fact in the same words.

#### Scenario: A token-less fact is explained, not hidden
- GIVEN a component whose list includes something with no token
- WHEN the admin opens that component
- THEN the row MUST state the fact and its reason
- AND it MUST carry a reason code the converter also uses

### Requirement: Editing Uses The Editor's Own Rows
A component's rows MUST be the token editor's own rows. An edit MUST reach the editor's
original input so the panel's dirty tracking, its reset control and its Save behave exactly as
they do in the full list. The playground MUST NOT keep a second copy of the pending edits and
MUST NOT write the overrides file itself.

#### Scenario: An edit made under a component is an edit in the panel
- GIVEN a component open in the instrument
- WHEN the admin changes one of its tokens
- THEN the panel MUST count it as an unsaved change
- AND saving MUST write it exactly as it would from the full token list

#### Scenario: The specimen repaints, the panel does not
- GIVEN a component open in the instrument
- WHEN the admin changes one of its colour tokens
- THEN the specimen on the stage MUST take the new value at once
- AND the settings page around it MUST NOT be repainted

### Requirement: The Playground Exports A Complete Token Set
The playground MUST offer the active set, with the saved overrides folded in, as a
downloadable flat `:root { }` block of `--nldesign-*` declarations sorted by name — the shape
the shipped token sets and the custom-set upload already accept. The export MUST be a complete
set, not a diff, so the vocabulary audit can rate it. An override that maps to no token in the
vocabulary MUST be reported rather than silently omitted.

#### Scenario: Exporting with nothing overridden round-trips
- GIVEN a token set active and no saved overrides
- WHEN the admin exports
- THEN the downloaded file MUST be rated by the vocabulary audit exactly as the active set is

#### Scenario: An override the vocabulary cannot carry is reported
- GIVEN a saved override of a variable that maps to no `--nldesign-*` token
- WHEN the admin exports
- THEN the file MUST NOT claim it
- AND the admin MUST be told which overrides were left out

### Requirement: The Selection Is Addressable
The open tab and component MUST be reflected in the URL hash, and a hash naming a tab and a
component this build has MUST reopen them. A hash naming a component the inventory does not
carry, or one filed under another tab, MUST be ignored rather than half-applied.

#### Scenario: A shared link reopens a component
- GIVEN a link carrying a tab and a component
- WHEN an admin opens it
- THEN that tab MUST be open and that component selected

#### Scenario: A stale link degrades to the panel
- GIVEN a link naming a component this build does not carry
- WHEN an admin opens it
- THEN the panel MUST render normally with no component selected
