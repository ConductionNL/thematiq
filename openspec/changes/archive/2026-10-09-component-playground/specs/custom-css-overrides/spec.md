# Spec delta: Custom CSS Overrides (component-playground)

The overrides file, its format, its place in the load order, its endpoint and its single
writer are all unchanged. This delta exists to say so: the component playground deliberately
adds no second writer.

## MODIFIED Requirements

<!--
  THIS BLOCK REPLACES THE REQUIREMENT WHOLESALE, so it has to carry the scenarios that are
  already live as well as the ones this change adds. The convention in this repository is
  full-block replacement: anything omitted here is DELETED from
  `openspec/specs/custom-css-overrides/spec.md` on sync.

  The three carried forward below — "Read current overrides", "Write new overrides" and
  "Write fails due to filesystem permissions" — are unchanged from the live spec. They are
  repeated verbatim rather than referenced, because a reference would not survive the
  replacement. Losing them would drop the atomic temp-file + rename guarantee and the
  documented failure mode (HTTP 500, file unchanged) as a side effect of a proposal about a
  playground, which is not a trade anyone chose.
-->

### Requirement: Read/Write PHP Endpoint
The backend MUST expose a PHP service that reads the current `custom-overrides.css` and writes a new version atomically. Direct file manipulation from Vue components MUST NOT be used.

The overrides endpoint remains the only way the client writes `custom-overrides.css`, and the
token editor remains its only caller. The component playground MUST reach it through that
editor's own rows and its own Save rather than through a path of its own, so there is one
implementation of "what an edit is" and it cannot disagree with itself.

#### Scenario: Read current overrides
- GIVEN `custom-overrides.css` exists with some overrides
- WHEN the admin settings panel loads
- THEN a GET request to `/settings/overrides` MUST return the list of currently overridden token names and values as JSON
- AND the response MUST include only tokens present in `custom-overrides.css` (not defaults or resolved values)

#### Scenario: Write new overrides
- GIVEN the admin clicks Save with a new set of token values
- WHEN a POST request is made to `/settings/overrides` with the token map
- THEN the backend MUST validate each token name against the editable token registry
- AND it MUST write the validated tokens to `custom-overrides.css` atomically (write to temp file, rename)
- AND it MUST return HTTP 200 with the final set of written tokens

#### Scenario: Write fails due to filesystem permissions
- GIVEN the CSS directory is not writable by the web server process
- WHEN the save endpoint is called
- THEN the server MUST return HTTP 500
- AND the error response MUST include a message indicating the file could not be written
- AND the existing `custom-overrides.css` MUST remain unchanged

#### Scenario: The playground saves through the editor, not beside it
- GIVEN an edit made under a component in the playground
- WHEN the admin saves
- THEN the request MUST be the one the token editor would have sent for the same edit
- AND the playground MUST NOT issue a write of its own

#### Scenario: An edit under a component is an edit in the editor
- GIVEN a component open in the playground
- WHEN one of its tokens is edited
- THEN the token editor MUST report an unsaved change, exactly as if the value had been typed
  in the full list
