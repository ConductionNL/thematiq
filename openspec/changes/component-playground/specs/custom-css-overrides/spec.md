# Spec delta: Custom CSS Overrides (component-playground)

The overrides file, its format, its place in the load order and its endpoint are unchanged.
What changes is that it now has a second writer, and that both writers must agree.

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

The overrides endpoint remains the only way the client writes `custom-overrides.css`, and it
MUST serve both editing surfaces: the settings page's token editor and the component
playground. Neither surface may write the file by another route, and for the same set of
edits both MUST produce the same request body, so that a value edited in one place is
indistinguishable from the same value edited in the other.

A client that has saved MUST re-request the overrides stylesheet rather than reload the page,
and MUST keep any inline preview values in place until the new stylesheet has loaded.

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

#### Scenario: The playground saves through the same endpoint
- GIVEN unsaved edits in the component playground
- WHEN the admin saves them
- THEN the client MUST POST them to the overrides endpoint
- AND the resulting file MUST be the merge of the existing overrides with the edits

#### Scenario: The two surfaces agree
- GIVEN the same variable set to the same value in the token editor and in the playground
- THEN the request bodies the two surfaces send MUST be equal

#### Scenario: No flash between saving and the new stylesheet
- GIVEN a saved edit whose value is currently applied inline
- WHEN the overrides stylesheet is re-requested
- THEN the inline value MUST be removed only after the new stylesheet has loaded
