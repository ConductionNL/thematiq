## MODIFIED Requirements

### Requirement: La Suite Element Overrides Layer

The element-overrides layer MUST adjust Nextcloud chrome to the La Suite look: a white header
surface with no visible box-shadow and no visible bottom border rule (the border-bottom stays
declared at `1px` but transparent, matching the measured live chrome), horizontal header padding of
`18px`, and zero visible gap between the bottom of `#header` and the top of `#content-vue` (the
header MUST NOT have its `position` forced into normal document flow — it keeps Nextcloud's own
out-of-flow positioning so the layout's existing top margin closes the gap exactly, as it does on
stock Nextcloud). The content shell (`#content-vue`) MUST paint a grey canvas
(`gray-025` in light mode), and the app content (`#app-content`/`.app-content`) MUST render on it as
a white (`gray-000` in light mode), `border-radius: var(--lasuite-border-radius)`, no-shadow card. The content shell (`#content-vue`) MUST render full-bleed (`border-radius: 0`,
`margin: 0`), and the sidebar (`#app-navigation-vue`/`.app-navigation`) MUST render full-bleed
(`border-radius: 0`) with its depth expressed via `box-shadow: 10px 0 10px rgba(0,0,0,.05)` in
addition to its existing hairline `border-right`. The layer MUST also keep flat navigation
surfaces, 4px control radii on buttons/inputs, and font application via body inheritance
(ADR-CSS-001 — no universal-selector `!important` font forcing). The layer MUST keep WCAG 2.1 AA
contrast on all adjusted element pairs. Nextcloud's 50px `#header` height, Nextcloud component row
heights, nav-item font sizes, and list/detail pane counts are unaffected by this requirement — they
are Nextcloud component metrics and information architecture, not design-system theming.

#### Scenario: Controls carry La Suite radii

@e2e exclude partly asserted by tests/e2e/spec-coverage/lasuite-parity.spec.ts ('primary button' row: brand fill, brand-050 text, 4px radius) and lasuite-radius-scale.spec.ts; the hover step is static CSS in css/systems/lasuite/element-overrides.css, verified by the live side-by-side capture of task 5.3 (2026-07-30)

- GIVEN the lasuite system is active
- WHEN a primary button renders
- THEN its background MUST be the brand color, its text white, and its border-radius `4px`
- AND its hover state MUST use a darker brand-scale step

#### Scenario: Icon fonts survive

- GIVEN the element-overrides layer applies the Inter-based stack
- WHEN a Material Design Icons glyph or code-editor monospace block renders
- THEN its own font-family MUST be preserved (no universal `!important` font rule exists)

#### Scenario: Header sits flush against content with no visible chrome

@e2e exclude partly asserted by tests/e2e/spec-coverage/lasuite-parity.spec.ts ('header bar' row: white surface, 1px solid bottom border); the 0px gap, 18px padding and no position override are static CSS in css/systems/lasuite/element-overrides.css, verified by the live side-by-side capture of task 5.3 (2026-07-30)

- GIVEN the lasuite system is active
- WHEN `#header` and `#content-vue` render
- THEN `#header` MUST NOT declare an overriding `position` value (it remains Nextcloud's own
  out-of-flow positioning)
- AND the visible gap between the bottom of `#header` and the top of `#content-vue` MUST be `0px`
- AND `#header` MUST have a white background in light mode, `box-shadow: none`, a `border-bottom` at
  `1px` with a transparent colour (no visible rule), and horizontal padding of `18px`

#### Scenario: Grey canvas separates the main area from a white content card

@e2e exclude static CSS in css/systems/lasuite/element-overrides.css, verified by the live side-by-side capture of task 5.3 (2026-07-30)

- GIVEN the lasuite system is active
- WHEN the main app area renders
- THEN the shell (`#content-vue`) MUST paint the grey canvas (`gray-025` in light mode)
- AND the app content (`#app-content`/`#app-content-vue`/`.app-content`) MUST render on it as a
  white (`gray-000` in light mode) card with `border-radius: var(--lasuite-border-radius)` and no
  box-shadow

#### Scenario: Shell and sidebar render full-bleed

@e2e exclude partly asserted by tests/e2e/spec-coverage/lasuite-parity.spec.ts ('app navigation sidebar' row: white surface, radius 0); the shadow and the shell margins are static CSS in css/systems/lasuite/element-overrides.css, verified by the live side-by-side capture of task 5.3 (2026-07-30)

- GIVEN the lasuite system is active
- WHEN `#content-vue` and the sidebar render
- THEN `#content-vue` MUST have `border-radius: 0` and `margin: 0`
- AND the sidebar (`#app-navigation-vue`/`.app-navigation`) MUST have `border-radius: 0`
- AND the sidebar MUST have `box-shadow: 10px 0 10px rgba(0,0,0,.05)` in addition to its existing
  hairline `border-right`

### Requirement: Visual Parity Verification

The change MUST be verified by visual comparison: a side-by-side capture of a real La Suite app
page (La Suite Docs) and the lasuite-themed Nextcloud on the dev instance, checked for parity of
typeface rendering, primary interactive color, control radii, header treatment (including the
zero-gap header/content boundary and the absence of a visible header shadow or bottom border), the
grey-canvas/white-card content surface, the full-bleed shell and sidebar geometry, and greyscale
surface tones. The comparison artifact MUST be produced as part of verification, and the
acceptance bar is pixel-adjacent (same visual family at a glance), not pixel-identical.

#### Scenario: Side-by-side capture passes the parity checklist

@e2e exclude a manual visual comparison against a live La Suite app, recorded in the change (task 5.3); not a repeatable page assertion

- GIVEN the lasuite token set is active on the 8080 dev instance
- WHEN Playwright captures the themed Files view and login page next to a La Suite Docs page
- THEN the composite MUST show: Inter-rendered type, `#4844AD` primary interactive elements, 4px
  control radii, a white header with no visible shadow or bottom border and 0px gap to the content
  area, a grey main canvas with a white content card, a full-bleed content shell and sidebar, and
  Cunningham-greyscale surfaces
- AND any checklist miss MUST be fixed before the change is archived
