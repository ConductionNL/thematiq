# dark-mode — delta

Requirements added by the `lasuite-dark-palette` change.

## ADDED Requirements

### Requirement: A design system whose overrides consume its own token ramp SHALL ship a dark counterpart for that ramp

When a design system's `element-overrides.css` paints the shell from its own `--{system}-*`
tokens, every ground-dependent value it reads MUST resolve to a dark value in dark mode. A rule
SHALL read such a value through a token that the system's dark scope remaps (for La Suite: the
Cunningham contextual tokens and the `--lasuite-content-*` aliases), never through a raw light
ramp step, and both dark scopes (the `prefers-color-scheme: dark` media block and the explicit
`data-theme` block) SHALL remap every such token.
Redefining only the shared `--nldesign-*` layer is NOT sufficient: an override that reads the
system ramp with `!important` discards the `--nldesign-*` value entirely, so the dark variant
computes and is then thrown away.

Dark values SHALL be sourced from the upstream design system's own dark palette where one
exists, rather than derived by inverting the light ramp. Cunningham's dark theme keeps the ramp
and remaps its contextual tokens, so the La Suite dark block remaps the same contextuals to the
same ramp steps.

#### Scenario: A ground-dependent token consumed by an override has a dark value

@e2e exclude a static resolution of the shipped stylesheets in the lasuite load order; proven by tests/vitest/lasuiteDarkRamp.spec.js 'read no raw gray step and no token the dark blocks leave unmapped' and 'resolve #header to a dark surface in dark mode and a light one in light mode'

- **GIVEN** `element-overrides.css` paints the header from `--lasuite--contextuals--background--surface--primary`
- **WHEN** the dark scope is active
- **THEN** that token resolves to upstream's dark value (`gray-800`)
- **AND** no override rule reads a raw `--lasuite-color-gray-*` step directly

#### Scenario: Redefining only the shared layer fails the check

@e2e exclude a build-time check with no page; proven by tests/vitest/lasuiteDarkRamp.spec.js 'fails when a dark variant redefines only the shared layer' and 'fails when only one of the two dark scopes remaps a token a rule reads'

- **GIVEN** a dark variant that redefines `--nldesign-color-header-background`
- **AND** an override that sets the header from `--lasuite-color-gray-000` with `!important`
- **WHEN** the dark-ramp check (`tests/css/check-lasuite-dark-ramp.js`) runs
- **THEN** it fails, because the token the override actually consumes has no dark value

### Requirement: Translucent override values SHALL be legible on both grounds

A value expressed as a translucent overlay SHALL be defined per ground, or expressed so it
resolves correctly on both. A dark wash intended for a light surface SHALL NOT be reused
unchanged on a dark surface.

#### Scenario: The active-row wash remains visible in dark mode

@e2e exclude a static resolution of the shipped stylesheets; proven by tests/vitest/lasuiteDarkRamp.spec.js 'keep the selected row visible on the dark navigation'

- **GIVEN** the active navigation row is marked by a translucent wash
- **WHEN** the interface renders in dark mode
- **THEN** the wash is a light wash over the dark navigation surface
- **AND** the row it paints differs from that surface

### Requirement: Dark-mode verification SHALL assert resolved shell values, not only stylesheet injection

Dark-mode coverage for a design system SHALL assert the values the shell resolves to in dark
mode (header, content canvas, content card, navigation and search field), resolved from the
shipped stylesheets in that system's load order, and the contrast of the text on them.
Asserting stylesheet order, scoping and toggle state alone is insufficient: a stylesheet can be
correctly ordered, correctly scoped, and have no effect on the system under test.

#### Scenario: The shell is dark in dark mode

@e2e exclude resolved from the shipped stylesheets in the lasuite load order, because switching the shared instance to the lasuite set and a dark user theme is a state change no spec-coverage run may make; proven by tests/vitest/lasuiteDarkRamp.spec.js 'resolve %s to a dark surface in dark mode and a light one in light mode' (header, canvas, card, navigation, search) and the WCAG AA cases

- **WHEN** the dark scope is active
- **THEN** the header, navigation, content canvas and card resolve to dark values
- **AND** the card stays lighter than the canvas it sits on
- **AND** text, muted text and brand text on them reach 4.5:1

#### Scenario: Injection-order coverage alone does not satisfy this requirement

@e2e exclude a statement about what counts as coverage, not a behaviour; the resolved-value tests above are what satisfies it

- **GIVEN** a suite that asserts only injection order, scoping and toggle state
- **WHEN** a design system ships no dark ramp for its own tokens
- **THEN** that suite passes while the interface renders light
- **AND** this requirement is therefore NOT met by such a suite
