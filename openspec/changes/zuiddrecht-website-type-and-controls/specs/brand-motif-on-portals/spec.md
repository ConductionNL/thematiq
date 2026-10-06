# Spec: Brand motif and role layer on portals

## ADDED Requirements

### Requirement: The website type scale, controls and marks are vocabulary
A set MAY name, for its portal only, `--nldesign-website-heading-1-font-size`, `-2-font-size`
and `-3-font-size`; `--nldesign-website-control-border-width`;
`--nldesign-website-badge-border-radius`; `--nldesign-website-step-marker-size`,
`-step-done-color`, `-step-done-mark-color`, `-step-current-color` and
`-step-current-background-color`; `--nldesign-website-notice-background-color`, `-border-color`
and `-border-width`; and `--nldesign-website-tab-line-color` and `-tab-current-color`.
`css/public-bridge.css` MUST read each into the role it refines (the Utrecht heading sizes, button
and text box border widths, the plain alert, the Den Haag data badge radius and step marker roles,
and `--thematiq-tab-line-color` and `--thematiq-tab-current-color`), and no stylesheet an instance
page loads MUST read any of them. Every role that had a value MUST keep that value as its fallback;
the notice ground and border and the two tab roles MUST carry no fallback, so a consumer's own
fallback applies where a set names none, as does the data badge radius, which no pinned Den Haag
stylesheet reads. The step marker roles MUST be wrapped by the generator from the `website`
section of the mapping, never by hand.

#### Scenario: Zuiddrecht draws its boards
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js resolves the bridge and the set the way a page does
- GIVEN a portal on `zuiddrecht`
- WHEN the bridge and the set are resolved
- THEN the heading sizes MUST be 44px, 40px and 26px, the button and text box borders 2px, the
  data badge radius 14px, the step marker 36px, a done marker filled #3669A5 with a white tick and
  a blue line after it, the current marker white with a #CC0000 ring and number, the plain alert
  #EAF0F7 with a 1px #B9CBE2 border, and the tab roles #D3D8DF and #CC0000

#### Scenario: A set that names none keeps every value
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js (the control)
- GIVEN a portal on a school set or on `vng`
- WHEN the same roles are resolved
- THEN the heading sizes MUST be 36px, 32px and 24px, the button border 1px, the alert border 2px,
  the step marker 32px in the colours it had
- AND the plain alert ground and border and the two tab roles MUST resolve to nothing

#### Scenario: The generator refuses a website rule nothing reads
@e2e exclude Static file check: scripts/generate-denhaag-bridge.mjs --check
- GIVEN a `website` rule for a property no pinned component reads, or one naming a token outside `--nldesign-website-*`
- WHEN the section is built
- THEN the generator MUST report it as a problem
