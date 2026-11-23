# lasuite-parity Specification

## Purpose
The La Suite numerique look in Nextcloud: a Cunningham token base generated from the upstream source, the sourced violet brand and a blue sibling set, every Nextcloud variable bridged, component parity checked end to end, and a guard against drift from the generated tokens.
## Requirements
### Requirement: Generated Cunningham Token Base

The `lasuite` defaults layer MUST be **generated**, not hand-transcribed. The app MUST add
`@openfun/cunningham-tokens` (MIT) as a `devDependency` and ship a re-runnable
`scripts/generate-lasuite-tokens.mjs` that reads that package's `dist/cunningham-tokens.css` and
emits `css/systems/lasuite/defaults.css` containing **all 1167** `--lasuite--*` custom properties on
`:root`, one per Cunningham `--c--*` token. The rename MUST use a documented, reversible mapping:
`--c--<path>` ⇄ `--lasuite--<path>` — a prefix swap only, preserving every `--` hierarchy separator,
with no segment collapsing. The generated file MUST carry a provenance header naming the source
package, its resolved version, the token count, the mapping rule, the generation date, and the MIT
attribution. The generated output MUST be committed. The generator MUST be deterministic (stable
token order and formatting) and MUST accept an output path (arg or env) defaulting to the committed
file. To preserve existing consumers, the generated file MUST also contain a closed, explicitly
listed compatibility-alias `:root` block mapping the short token names the `lasuite` bridge and
element-overrides layers read (e.g. `--lasuite-color-brand-650`, `--lasuite-border-radius`,
`--lasuite-spacing-md`) to their canonical `--lasuite--*` tokens.

#### Scenario: All 1167 tokens generated with reversible names

- GIVEN `@openfun/cunningham-tokens` is installed as a devDependency
- WHEN `node scripts/generate-lasuite-tokens.mjs` runs
- THEN `css/systems/lasuite/defaults.css` MUST define one `--lasuite--*` custom property for every one
  of the 1167 `--c--*` tokens in the package's `dist/cunningham-tokens.css`
- AND each name MUST equal its source with `--c--` replaced by `--lasuite--` and all other characters
  preserved (so `--c--globals--colors--brand-600` becomes `--lasuite--globals--colors--brand-600`)
- AND the transform MUST be reversible by swapping the prefix back

#### Scenario: Base is the blue Cunningham base, not the deployed violet

- GIVEN the generated defaults layer is loaded on its own (no brand override)
- WHEN `--lasuite--globals--colors--brand-600` is resolved
- THEN it MUST equal `#0659C5` (the published Cunningham blue base)
- AND the file MUST NOT hard-code the violet deployment values (those live in the brand-override layer)

#### Scenario: Provenance header and compatibility aliases present

- GIVEN the generated `defaults.css`
- WHEN its header and trailing alias block are read
- THEN the header MUST name `@openfun/cunningham-tokens`, its version, the token count (1167), the
  `--c--`→`--lasuite--` rule, the generation date, and the MIT licence
- AND the alias block MUST define every short `--lasuite-*` name the bridge and element-overrides
  layers consume, each as `var(--lasuite--…)` of its canonical token

### Requirement: Sourced La Suite Violet Brand Override

The app MUST reproduce La Suite's *deployed* violet theme, which no published Cunningham package ships — it exists only
as an app-level override in the live bundle. The app MUST ship a separate
`css/systems/lasuite/brand-override.css` that reproduces the deployed violet theme by redeclaring, on
`:root`, the brand and logo tokens the live override changes: `brand-600 #534fc2`,
`brand-650 #4844ad` (also the logo colour), the dependent `brand-050…brand-950` scale, and the
`logo-*` tokens (plus the short aliases the bridge reads). The file MUST carry a provenance comment
stating it was observed in the `docs.numerique.gouv.fr` La Suite Docs bundle, `:root` block 5, with
the observation date, and that it is not present in any published Cunningham package. The `lasuite`
design system bundle MUST load `brand-override` immediately after `defaults` so the cascade yields the
deployed violet values. `brand-override.css` MUST NOT be produced by the generator (it is a
hand-authored, sourced artifact).

#### Scenario: Deployed cascade resolves to violet

- GIVEN the `lasuite` design system is active (bundle order fonts → defaults → brand-override → bridge
  → element-overrides)
- WHEN `--color-primary` is resolved on a rendered page
- THEN it MUST resolve to the violet `#4844AD` (brand-650), overriding the blue base from `defaults`
- AND `--lasuite--globals--colors--brand-600` MUST resolve to `#534fc2` on that page

#### Scenario: Override is sourced and separate, not generated

- GIVEN `css/systems/lasuite/brand-override.css`
- WHEN its header comment is read
- THEN it MUST state the values were observed in the live `docs.numerique.gouv.fr` bundle, `:root`
  block 5, with a date, and are absent from published packages
- AND the drift guard (see below) MUST NOT touch this file (it is not generator output)

### Requirement: Blue-Base Cunningham Sibling Set

The app SHOULD ship the published Cunningham blue base as its own selectable set (a MUST once adopted), since that is the
artifact the npm packages actually distribute. When shipped, it MUST reuse the same generated
`defaults.css` and add **no** new base CSS beyond one Layer-3 token file and two manifest entries: a
`cunningham` design system whose bundle is `systems/lasuite/fonts`, `systems/lasuite/defaults`,
`systems/lasuite/bridge`, `systems/lasuite/element-overrides` (no `brand-override`), and a
`cunningham` token set. This set is secondary to `lasuite`; it MUST NOT change any `lasuite`
behaviour.

#### Scenario: Blue base resolves without the violet override

- GIVEN the `cunningham` token set is active
- WHEN `--color-primary` is resolved
- THEN it MUST resolve to the blue base's brand-650 `#1A509F` from the shared generated
  `defaults.css` (the same scale step `--color-primary` resolves to for lasuite's violet
  `#4844AD` — the shared bridge/element-overrides derive `--color-primary` from
  `--lasuite-color-brand-650` specifically, not brand-600; brand-600 `#0659C5` is the value of the
  raw generated token `--lasuite--globals--colors--brand-600`, a different, unrendered step)
- AND no `--lasuite/brand-override` stylesheet MUST be loaded for this set
- AND activating `lasuite` afterwards MUST still resolve to the violet `#4844AD`

#### Scenario: Sibling reuses the generated base

- GIVEN both the `lasuite` and `cunningham` design systems are declared
- WHEN their bundles are inspected
- THEN both MUST reference the same `systems/lasuite/defaults` stylesheet
- AND only the `lasuite` bundle MUST additionally include `systems/lasuite/brand-override`

### Requirement: Complete Nextcloud Variable Bridge Coverage

The `lasuite` bridge layer MUST provide provable coverage of the audited Nextcloud `--color-*`
variable surface. Every Nextcloud `--color-*` variable in the `nextcloud-variable-mapping` canonical
audit — the same surface `css/systems/nldesign/overrides.css` covers — MUST appear in
`css/systems/lasuite/bridge.css` as either a mapping to a `--lasuite--*` (or short-alias) token or a
commented line stating why it is not overridden. The dark-mode-compatibility variables
(`--color-main-background`, `--color-main-background-rgb`, `--color-main-background-translucent`,
`--color-background-plain`, `--background-invert-if-dark`, `--background-invert-if-bright`) MUST remain
in the "not overridden" category with their `REQ-CSS-007` reason. No mapping may introduce a circular
`var()` reference (REQ-CSS-005).

#### Scenario: Every audited variable is accounted for

- GIVEN the audited Nextcloud `--color-*` variable set (as enumerated in
  `css/systems/nldesign/overrides.css` per the `nextcloud-variable-mapping` spec)
- WHEN `css/systems/lasuite/bridge.css` is scanned
- THEN each audited variable MUST be present either as an active mapping or as a commented line with a
  reason
- AND the dark-mode-compat variables MUST be present only as reasoned comments, never as active
  overrides

#### Scenario: Coverage is test-asserted

- GIVEN a coverage check comparing the audited variable name set against `bridge.css`
- WHEN it runs
- THEN it MUST fail if any audited `--color-*` variable is neither mapped nor commented in `bridge.css`
- AND it MUST report the missing variable names

### Requirement: Component Parity End-to-End Verification

The app MUST ship `tests/e2e/spec-coverage/lasuite-parity.spec.ts` that renders Nextcloud components
under the active La Suite set and asserts their **computed** styles equal the Cunningham reference
values. It MUST cover a button, an input, a modal, a header, and a table, asserting for each the
computed `background-color`, `color`, `border-radius`, `font-family`, `font-size`, `font-weight`,
`padding`, and `box-shadow`. Comparisons MUST normalise notation (rgb() vs hex, whitespace) so that a
purely notational difference is not reported as a failure, and any real mismatch MUST fail with a
message naming the exact CSS property and the expected-vs-actual delta. Because the e2e instance is
load-fragile (issue #181), the spec MUST run serial with a small, fixed batch (one `test()` per
element) rather than heavy parallel navigation.

#### Scenario: Rendered components match Cunningham reference values

- GIVEN the `lasuite` token set is active on the dev instance
- WHEN the parity spec reads the computed styles of a rendered primary button, text input, modal,
  header, and table
- THEN each asserted property MUST equal the Cunningham reference value for the active set (violet
  brand-650 `#4844ad`, radius `4px`, the Inter/Marianne stack), after notation normalisation
- AND for the `cunningham` set the reference table MUST use the blue base's brand-650 (`#1A509F` —
  the same scale step the shared bridge/element-overrides derive every rendered brand-accent from
  for lasuite's violet `#4844AD`; brand-600 `#0659C5` is a different, unrendered step)

#### Scenario: Mismatch names the property and delta

- GIVEN a rendered component whose computed `border-radius` is `8px` while the reference is `4px`
- WHEN the parity spec compares them
- THEN the test MUST fail
- AND the failure message MUST name `border-radius` and report expected `4px` vs actual `8px`

#### Scenario: Spec is small and batchable

- GIVEN the load-fragile e2e instance (issue #181)
- WHEN the parity spec runs
- THEN it MUST execute serially (`mode: 'serial'`) with one `test()` per element
- AND it MUST NOT launch heavy parallel navigation that can overwhelm the instance

### Requirement: Generated Token Drift Guard

The app MUST provide a CI-runnable check that re-generates the `lasuite` defaults from the installed
npm package and fails if the committed `css/systems/lasuite/defaults.css` differs. The check MUST run
`scripts/generate-lasuite-tokens.mjs` into a temporary path and diff it against the committed file,
exiting non-zero on any difference and printing the differing tokens. It MUST be wired into
`package.json` scripts following the existing l10n-completeness check pattern. An upstream Cunningham
bump is therefore a deliberate `package.json` version change plus a regenerate-and-commit, surfaced by
this guard.

#### Scenario: Committed file matches the generator

- GIVEN the pinned `@openfun/cunningham-tokens` version is installed
- WHEN the drift check runs
- THEN re-generating MUST reproduce the committed `defaults.css` byte-for-byte
- AND the check MUST exit zero

#### Scenario: Drift is caught

- GIVEN a hand edit to a token value in the committed `defaults.css`, or an un-regenerated upstream
  version bump
- WHEN the drift check runs
- THEN it MUST exit non-zero
- AND it MUST print the differing token name(s)

