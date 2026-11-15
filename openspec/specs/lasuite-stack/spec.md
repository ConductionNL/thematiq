# lasuite-stack Specification

## Purpose
The lasuite design system: La Suite numérique's fonts, Cunningham tokens, a bridge onto the Nextcloud variables and the element overrides that give Nextcloud the La Suite look. Created by archiving change lasuite-design-stack.

## Requirements

### Requirement: La Suite Design System Bundle

The app MUST ship a `lasuite` design system: an entry in `design-systems.json` whose
`stylesheets` array declares, in exact order, `systems/lasuite/fonts`,
`systems/lasuite/defaults`, `systems/lasuite/brand-override`, `systems/lasuite/bridge`,
`systems/lasuite/element-overrides`, with all five files living under `css/systems/lasuite/`. The
`brand-override` layer MUST load immediately after `defaults` (see the lasuite-parity spec's sourced
violet override requirement). The bundle's goal is a **pixel-adjacent match to La Suite chrome** — the
type ramp, palette, radii, and surface tones of La Suite numérique apps (Docs/Meet/Chat, Cunningham
design system) — so a Nextcloud core inside a MijnBureau/EDIC sovereign-workplace bundle shows no
visual seam against the surrounding La Suite apps.

#### Scenario: Bundle resolves and loads in declared order

- GIVEN the active token set has `design_system: "lasuite"`
- WHEN `Application::injectThemeCSS()` runs
- THEN `DesignSystemService` MUST resolve the `lasuite` entry from `design-systems.json`
- AND the five stylesheets MUST be added via `\OCP\Util::addStyle()` in the declared order
  (fonts → defaults → brand-override → bridge → element-overrides)
- AND `tokens/lasuite` MUST load after them, followed by `custom-overrides` (standard layering)

#### Scenario: Deactivating lasuite leaves no residue

- GIVEN the admin switches from the `lasuite` set to any other set
- WHEN the next page renders
- THEN no `systems/lasuite/*` stylesheet MUST be loaded
- AND no `--lasuite-*` or `--lasuite--*` custom property MUST be defined

### Requirement: La Suite Fonts Layer With Open Fallback

The fonts layer MUST self-host **Inter** (SIL Open Font License 1.1) as the fallback typeface —
the first open font in La Suite's own configured stack (`Marianne, Inter, Roboto Flex Variable,
sans-serif`) — with @font-face rules for weights 400/500/600/700 (plus 400/700 italic) using
woff2 sources and `font-display: swap`. The layer MUST define
`--lasuite-font-family: Marianne, Inter, sans-serif`. The app MAY bundle **Marianne** (the
French-state typeface) self-hosted from `@gouvfr/dsfr@1.15.1` under Etalab Open Licence 2.0, but
its activation MUST be gated per the `marianne-font` capability: the real self-hosted
`@font-face Marianne` declarations live in a separate `css/systems/lasuite/marianne.css`
stylesheet that is emitted ONLY when the active design system is `lasuite` AND the admin
acknowledgement flag `marianne_enabled` is `'1'`. When the gate is off, no `url()` source for
Marianne exists at runtime, so Inter renders. Inter also renders as the fallback for any glyph
Marianne does not cover. The OFL license text MUST ship alongside the Inter files, and the
Etalab licence + Marianne restriction MUST ship alongside the Marianne files (see
`marianne-font`). This requirement no longer forbids bundling Marianne; silent, ungated
activation is what is forbidden.

#### Scenario: Inter served by default, Marianne gated

- GIVEN the lasuite fonts layer is loaded AND the `marianne_enabled` gate is off (the default)
- WHEN the browser resolves `--lasuite-font-family`
- THEN text MUST render in the bundled Inter
- AND no HTTP request for any Marianne resource MUST occur (the gated `marianne.css` is not
  emitted)

#### Scenario: Marianne renders when the gate is on

- GIVEN the `lasuite` system is active AND an admin has enabled `marianne_enabled`
- WHEN the font stack resolves
- THEN Marianne MUST be used (first family in the stack, now self-hosted from the app)
- AND the `Marianne-*.woff2` files MUST load from the app's own directory with an app-relative
  URL (no external host)
- AND Inter MUST remain the fallback for any glyph Marianne does not cover

#### Scenario: License artifacts present

- GIVEN the shipped app package
- WHEN `css/systems/lasuite/fonts/` is inspected
- THEN it MUST contain the Inter woff2 files and an `OFL.txt` license text
- AND the bundled Marianne woff2 files under `css/systems/lasuite/fonts/marianne/` MUST travel
  with their Etalab Open Licence 2.0 notice and the Marianne restriction (see `marianne-font`)

### Requirement: Cunningham Token Defaults Layer

The defaults layer MUST be **generated**, not hand-transcribed. It MUST be produced by
`scripts/generate-lasuite-tokens.mjs` reading `@openfun/cunningham-tokens` (MIT, a `devDependency`)
and MUST define **all 1167** Cunningham tokens as `--lasuite--*` custom properties on `:root`, using
the reversible `--c--` ⇄ `--lasuite--` prefix-swap mapping (double-dash separators preserved). The
generated base MUST be the published Cunningham **blue** base (`--lasuite--globals--colors--brand-600:
#0659C5`); the deployed **violet** values live in the separate `brand-override` layer, never in this
file. The file MUST carry a provenance header attributing Cunningham, its MIT licence, and the source
package version/token-count/generation-date, and MUST include a closed compatibility-alias block for
the short `--lasuite-*` names the bridge and element-overrides layers consume. The generated output
MUST be committed and guarded against drift (see the lasuite-parity spec). The full requirements for
generation, naming, aliases, and drift live in the lasuite-parity spec.

#### Scenario: Brand base matches the published Cunningham blue base

- GIVEN the generated defaults layer is loaded on its own (no brand override)
- WHEN `--lasuite--globals--colors--brand-600` is resolved
- THEN it MUST equal `#0659C5` (published Cunningham blue base)
- AND the deployed violet `#4844AD` MUST come only from the `brand-override` layer, not this file

#### Scenario: Attribution and generation present

- GIVEN `css/systems/lasuite/defaults.css`
- WHEN its header is read
- THEN it MUST name Cunningham, the MIT licence, the source package (`@openfun/cunningham-tokens`), its
  version, the token count (1167), and the `--c--`→`--lasuite--` mapping rule
- AND the file MUST be re-derivable by re-running `scripts/generate-lasuite-tokens.mjs`

### Requirement: La Suite Bridge Layer

The bridge layer MUST map `--lasuite-*` tokens onto the `--nldesign-*` namespace (primary
family, status colors with `-rgb` variants, text/muted-text, border, focus, font-family,
border-radius, and the `--nldesign-component-*` tokens the shared theme machinery consumes) and
onto Nextcloud `--color-*` variables, honouring the css-architecture invariants: `!important`
only where Nextcloud's own equal-specificity assignments must be beaten (ADR-CSS-002);
`--color-main-background`, `--color-main-background-rgb`, `--color-main-background-translucent`,
`--color-background-plain`, `--background-invert-if-dark`, and `--background-invert-if-bright`
MUST NOT be overridden (REQ-CSS-007 dark-mode compatibility); no circular `var()` references
(REQ-CSS-005).

#### Scenario: Primary maps to La Suite brand

- GIVEN the lasuite system is active
- WHEN `--color-primary` is resolved on a rendered page
- THEN it MUST resolve to `#4844AD` via `--nldesign-color-primary` ← `--lasuite-color-brand-650`
- AND `--color-primary-text` MUST resolve to a value with ≥ 4.5:1 contrast against it

#### Scenario: Dark-compatibility variables untouched

- GIVEN the bridge layer is loaded
- WHEN a user enables Nextcloud's dark theme
- THEN `--color-main-background` and both `--background-invert-if-*` variables MUST carry
  Nextcloud's own dark values (the bridge declares none of them)
- AND no unreadable surface MUST result

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

### Requirement: La Suite Asset License Compliance

Every asset bundled for the lasuite stack MUST be under a redistributable license and MUST carry
that license: Cunningham token values (MIT), Inter (SIL OFL 1.1), and Marianne
(Etalab Open Licence 2.0, from `@gouvfr/dsfr@1.15.1`). Marianne MAY be bundled ONLY when it
ships together with (a) its `etalab-2.0` licence text and the verbatim French-State restriction,
(b) the operator user agreement, and (c) the default-off admin acknowledgement gate defined by
the `marianne-font` capability — so it is never silently activated on an instance whose operator
has not affirmed eligibility. The app MUST NOT bundle La Suite or French-state **logos** or any
other French-government-restricted asset beyond the gated Marianne fonts; the lasuite token
set's logo slot MUST remain empty.

#### Scenario: Compliance is test-enforced

- GIVEN the test suite runs
- WHEN the license-compliance test executes
- THEN it MUST assert `OFL.txt` exists under `css/systems/lasuite/fonts/`
- AND it MUST assert every bundled `css/systems/lasuite/fonts/marianne/*.woff2` maps to
  `Etalab-2.0` in `.license-overrides.json` and that `MARIANNE-LICENCE.md` + `AGREEMENT-MARIANNE.md`
  exist
- AND it MUST assert the gated `css/systems/lasuite/marianne.css` uses only app-relative `url()`
  (no external Marianne source)
- AND it MUST assert the `lasuite` entry in `token-sets.json` has no `logo` key

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

### Requirement: La Suite Brand Override Layer

The app MUST ship `css/systems/lasuite/brand-override.css`: a hand-authored, provenance-commented layer
that reproduces the *deployed* La Suite violet theme, which no published Cunningham package contains.
It MUST redeclare on `:root` the brand and logo tokens the live override changes — `brand-600
#534fc2`, `brand-650 #4844ad` (also the logo colour), the dependent brand scale, and the `logo-*`
tokens (plus the short aliases the bridge reads) — and MUST load immediately after `defaults` in the
`lasuite` bundle so the cascade resolves to violet. It MUST NOT be generated by
`scripts/generate-lasuite-tokens.mjs` and MUST NOT be included in the `cunningham` (blue-base) bundle.

#### Scenario: Violet override wins the cascade for lasuite

- GIVEN the `lasuite` bundle is active
- WHEN `--color-primary` and `--lasuite--globals--colors--brand-600` are resolved on a page
- THEN `--color-primary` MUST resolve to `#4844AD` and `--lasuite--globals--colors--brand-600` to
  `#534fc2` (the override beating the blue base)

#### Scenario: Provenance recorded

- GIVEN `css/systems/lasuite/brand-override.css`
- WHEN its header comment is read
- THEN it MUST state the values were observed in the `docs.numerique.gouv.fr` La Suite Docs bundle,
  `:root` block 5, with an observation date, and are absent from any published Cunningham package

### Requirement: La Suite Shell Geometry Layer

The `lasuite` design system SHALL reshape the Nextcloud shell to La Suite Docs' geometry, measured
from the La Suite Docs source (suitenumerique/docs at 9de17b3f820a28ef159e29a372c84fecd6fc45eb):
a 64px header row (`HEADER_ROW_MIN_HEIGHT`), a 300px left panel and a full-width main area.
The geometry SHALL be set through Nextcloud's own `--header-height` variable, so the header, the
app menu triggers, the content offset and `--body-height` move together. The shared La Suite
overrides SHALL derive every header-dependent offset from `--header-height` rather than from a
fixed pixel value. The `cunningham` sibling and every other design system SHALL keep the stock
Nextcloud header height.

#### Scenario: On Nextcloud 35 the lasuite header row is 64px tall

- **GIVEN** the `lasuite` token set is active on a Nextcloud 35 server
- **WHEN** an admin opens any page
- **THEN** `#header` renders 64px tall

#### Scenario: The content starts directly under the taller header

- **GIVEN** the `lasuite` token set is active on a Nextcloud 35 server
- **WHEN** an admin opens the Files app
- **THEN** the top edge of `#content-vue` equals the bottom edge of `#header`, with no gap and no overlap

#### Scenario: The search field stays centred in the header

- **GIVEN** the `lasuite` token set is active
- **WHEN** an admin opens a page that shows the unified search field
- **THEN** the vertical centre of the search field equals the vertical centre of `#header`, within 1px
- **AND** the search field renders 34px tall, its 1px borders included

#### Scenario: The app navigation keeps La Suite Docs' 300px width

- **GIVEN** the `lasuite` token set is active
- **WHEN** an admin opens the Files app at a desktop viewport
- **THEN** the app navigation renders 300px wide

#### Scenario: The Cunningham sibling keeps the stock header height

- **GIVEN** the `cunningham` token set is active
- **WHEN** an admin opens any page
- **THEN** no shell geometry stylesheet is linked
- **AND** `#header` does not render 64px tall

### Requirement: Version-Scoped Design-System Stylesheets

A design system MAY declare `versioned_stylesheets` in `design-systems.json`: stylesheets keyed by
Nextcloud major version. Such a stylesheet SHALL load only when the running server reports that
major, directly after the design system's own stylesheets and before the token layer, and SHALL
appear in the stylesheet manifest the admin panel swaps without a reload. A server whose major is
not listed SHALL load none of them, so a new Nextcloud release falls back to stock geometry
instead of overrides written for different markup.

#### Scenario: The shell layer loads exactly on the Nextcloud majors it lists

- **GIVEN** the `lasuite` token set is active
- **WHEN** an admin opens any page
- **THEN** `css/systems/lasuite/shell-nc35.css` is linked when the server major is 35
- **AND** it is not linked on any other major
