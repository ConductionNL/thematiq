# Spec delta: Custom CSS Overrides (component-playground)

The overrides file, its format, its place in the load order and its endpoint are unchanged.
What changes is that it now has a second writer, and that both writers must agree.

## MODIFIED Requirements

### Requirement: Read/Write PHP Endpoint
The overrides endpoint remains the only way the client writes `custom-overrides.css`, and it
MUST serve both editing surfaces: the settings page's token editor and the component
playground. Neither surface may write the file by another route, and for the same set of
edits both MUST produce the same request body, so that a value edited in one place is
indistinguishable from the same value edited in the other.

A client that has saved MUST re-request the overrides stylesheet rather than reload the page,
and MUST keep any inline preview values in place until the new stylesheet has loaded.

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
