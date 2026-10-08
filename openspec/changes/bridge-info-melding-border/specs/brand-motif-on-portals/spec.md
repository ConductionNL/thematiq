## ADDED Requirements

### Requirement: A set may draw an info melding without a line

The public bridge MUST carry `--nldesign-website-alert-info-border-width` into
`--utrecht-alert-info-border-width`. When a set does not name it, the info border width MUST equal
the width every melding has.

#### Scenario: A school set
@e2e exclude Token resolution checked in vitest: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN the wilgenboom set, which names `--nldesign-website-alert-info-border-width: 0`
- WHEN a portal renders an info melding
- THEN it has no line, and a warning melding keeps its line

#### Scenario: A set names nothing
@e2e exclude Token resolution checked in vitest: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN the zuiddrecht set
- WHEN a portal renders an info melding
- THEN its line is 1px, as every melding on that set
