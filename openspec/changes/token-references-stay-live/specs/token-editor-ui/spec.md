# token-editor-ui Specification (delta)

## ADDED Requirements

### Requirement: A token can refer to another token
The token editor SHALL accept, as the value of any token row, a reference to another `--nldesign-*` token. The saved overrides SHALL keep the reference as `var(--nldesign-...)`, so a later edit of the target reaches every token that refers to it. The row SHALL show the reference and the value it resolves to. The editor SHALL refuse a reference to a token that does not exist and a reference that would form a cycle, and SHALL save nothing in that case. A DTCG import SHALL keep an alias whose target maps onto a `--nldesign-*` token as such a reference instead of writing the resolved value.

#### Scenario: An edit flows through a reference
- GIVEN `--nldesign-color-link` refers to `--nldesign-color-primary`
- WHEN the administrator changes `--nldesign-color-primary` to `#a00078` and saves
- THEN links on every page MUST use `#a00078`
- AND the row of `--nldesign-color-link` MUST still show the reference and `#a00078` beside it

#### Scenario: A cycle is refused
- GIVEN `--nldesign-color-link` refers to `--nldesign-color-primary`
- WHEN the administrator sets `--nldesign-color-primary` to refer to `--nldesign-color-link`
- THEN the editor MUST refuse the value and name both tokens
- AND nothing MUST be saved

#### Scenario: An imported alias stays a reference
- GIVEN a DTCG file whose `link` token is `{color.primary}` and both tokens map onto `--nldesign-*` tokens
- WHEN the administrator uploads it
- THEN the stored set MUST declare the link token as `var(--nldesign-color-primary)`
