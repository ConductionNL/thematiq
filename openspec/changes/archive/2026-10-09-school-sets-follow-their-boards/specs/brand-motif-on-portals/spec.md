## ADDED Requirements

### Requirement: The school sets name what their boards draw

The wilgenboom, vaartveld and esdoornveen sets MUST name the attention strip's background and
border from their boards, and every school set a 1px notice line. The esdoornveen and academy sets
MUST name a semibold hero search label, and the esdoornveen set the hero photo's clip path.

#### Scenario: Wilgenboom's "Let op"
@e2e exclude Token resolution in vitest: tests/vitest/publicBridgeRoleLayer.spec.js; measured on :8092
- GIVEN the wilgenboom set
- WHEN the home page renders its notice strip
- THEN it is `#FDF3D7` with a 1px `#ECD391` line under it
