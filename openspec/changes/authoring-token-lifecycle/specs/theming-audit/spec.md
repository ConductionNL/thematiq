# Spec delta: theming audit (authoring-token-lifecycle)

## ADDED Requirements

### Requirement: Own tokens and deprecations are audited

The closed action vocabulary MUST include `own_token_changed` and `token_deprecation_changed`.
Adding, editing or removing an own token MUST write one `own_token_changed` entry with the token
name and the old and new value. Adding, editing or removing a deprecation MUST write one
`token_deprecation_changed` entry with the token name and the old and new record. A refused
request MUST write no entry.

#### Scenario: Adding an own token is recorded
@e2e exclude Audit record content, covered by PHPUnit on OwnTokenController
- GIVEN the administrator `admin` adds `--nldesign-org-brand-accent`
- WHEN the request succeeds
- THEN one `own_token_changed` entry MUST be appended with actor `admin` and the new value

#### Scenario: A refused deprecation writes nothing
@e2e exclude Audit record content, covered by PHPUnit on the controller
- GIVEN a deprecation request refused with 400
- WHEN the request completes
- THEN no audit entry MUST have been written
