# Spec: Brand motif and role layer on portals

## ADDED Requirements

### Requirement: The site's page title, lead, notice text and surface are vocabulary
A set MAY name, for its portal only, `--nldesign-website-page-title-size` and
`--nldesign-website-page-title-line-height` (a content page's title, drawn larger than the
heading 1 role), `--nldesign-website-lead-font-size` (the lead paragraph),
`--nldesign-website-notice-color` (the text on a plain notice) and `--nldesign-color-surface`
(the site's grey band and a boxed table's header row). `css/public-bridge.css` MUST read each:
the lead size into `--utrecht-paragraph-lead-font-size` with 20px as its fallback, the notice
text into `--utrecht-alert-color`, the page title into `--thematiq-page-title-font-size` and
`--thematiq-page-title-line-height`, and the surface into `--thematiq-surface-color`, the last
four without a fallback so a consumer's own fallback applies where a set names none. No
stylesheet an instance page loads MUST read any of them.

#### Scenario: Zuiddrecht draws its site
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js resolves the bridge and the set the way a page does
- GIVEN a portal on `zuiddrecht`
- WHEN the bridge and the set are resolved
- THEN the page title role MUST be 2.75rem on a 1.15 line, the lead 18px, the notice text
  #1A1A1A and the surface #F4F6F9

#### Scenario: A set that names none keeps every value
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js (the control)
- GIVEN a portal on a school set or on `vng`
- WHEN the same roles are resolved
- THEN the lead MUST be 20px
- AND the page title roles, the notice text and the surface MUST resolve to nothing

#### Scenario: Every pair on the new grounds reaches AA
@e2e exclude Computed from the files: tests/vitest/zuiddrechtTokenSet.spec.js measures the pairs in the light and in both dark scopes
- GIVEN the `zuiddrecht` tokens in the light scheme and in the generated dark one
- WHEN the notice text is measured on the notice ground, and the text, muted text, link and
  link hover on the surface
- THEN each pair MUST reach 4.5:1

### Requirement: The attention strip has names of its own
A set MAY name, for its portal only, `--nldesign-website-attention-background-color`,
`--nldesign-website-attention-border-color` and `--nldesign-website-attention-color` for an
attention strip ("Let op"), apart from the plain notice `--nldesign-website-notice-*` names.
`css/public-bridge.css` MUST read each into `--thematiq-attention-background-color`,
`--thematiq-attention-border-color` and `--thematiq-attention-color`, without a fallback.

#### Scenario: Zuiddrecht draws a yellow strip next to its blue notice
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js resolves the bridge and the set the way a page does
- GIVEN a portal on `zuiddrecht`
- WHEN the bridge and the set are resolved
- THEN the attention roles MUST be #FFF4DE, #E8C77D and #1A1A1A
- AND the plain notice ground MUST stay #EAF0F7

#### Scenario: A set that names none draws no strip colours
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js (the control)
- GIVEN a portal on a school set or on `vng`
- WHEN the attention roles are resolved
- THEN each MUST resolve to nothing

#### Scenario: The strip text reaches AA in both schemes
@e2e exclude Computed from the files: tests/vitest/zuiddrechtTokenSet.spec.js measures the pair in the light and in both dark scopes
- GIVEN the `zuiddrecht` tokens in the light scheme and in the generated dark one
- WHEN the attention text is measured on the attention ground
- THEN the pair MUST reach 4.5:1
