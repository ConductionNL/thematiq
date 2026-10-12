## ADDED Requirements

### Requirement: The school sets name the type scale of their boards

Each school set MUST name its boards' page title size (44px for wilgenboom and esdoornveen, 48px for
vaartveld and warmtepompacademie) as the website's level 1 heading and page title, 17px as the
content text and 21px as the lead. The warmtepompacademie set MUST name capitals for the month of a
date tile; the bridge MUST pass it on without a fallback.

#### Scenario: De Wilgenboom's content page
@e2e exclude Token resolution in vitest: tests/vitest/publicBridgeRoleLayer.spec.js; measured on :8092 in the PR
- GIVEN the wilgenboom set on a public portal
- WHEN "Uw kind afwezig melden" renders
- THEN its title is 44px and its paragraphs 17px

#### Scenario: The academy's course days
@e2e exclude Token resolution in vitest: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN the warmtepompacademie set
- WHEN the bridge resolves `--thematiq-date-month-text-transform`
- THEN it is `uppercase`, and nothing for every other set
