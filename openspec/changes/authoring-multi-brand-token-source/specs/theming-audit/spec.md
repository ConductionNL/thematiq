# Spec delta: theming audit (authoring-multi-brand-token-source)

## ADDED Requirements

### Requirement: Source updates are audited

The closed action vocabulary MUST include `custom_source_updated`. A successful source update MUST
write exactly one `custom_source_updated` entry with the source id, the old and new content hash,
and the lists of `updated`, `missing` and `new` brands. Importing brands MUST write one
`custom_set_uploaded` entry per brand set, each carrying the source id and brand key. A refused
import or update MUST write no entry.

#### Scenario: Updating a source writes one entry
@e2e exclude Audit record content, covered by PHPUnit on ThemingAuditService and the controller
- GIVEN the administrator `admin` updates the source "Gemeente Voorbeeld"
- WHEN the update succeeds
- THEN one `custom_source_updated` entry MUST be appended with actor `admin`
- AND it MUST carry the old and new content hash

#### Scenario: A refused update writes nothing
@e2e exclude Audit record content, covered by PHPUnit on the controller
- GIVEN a source update that fails validation with 422
- WHEN the request completes
- THEN no audit entry MUST have been written
