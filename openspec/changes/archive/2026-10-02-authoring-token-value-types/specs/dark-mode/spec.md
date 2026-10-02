# Spec delta: dark mode (authoring-token-value-types)

## ADDED Requirements

### Requirement: Editor overrides apply the same way for every dark user

`custom-overrides.css` MUST carry, next to its `:root` block of light values, the two dark scopes
of the generated dark stylesheets: a `prefers-color-scheme: dark` block on
`body:not([data-theme-light]):not([data-theme-dark]):not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast])`,
and a `body[data-theme-dark], body[data-themes*=dark]` block. Every colour override MUST have a
declaration in both dark scopes: the administrator's dark value, or the derived one. A user who
follows a dark system setting and a user who chose the dark theme MUST see the same colour.

#### Scenario: Both kinds of dark user see the same override
- GIVEN the administrator saved "Primary color" `#154273` with dark value `#8fb8e6`
- WHEN one user on "System default" with a dark operating system loads Files
- AND another user who chose the dark theme loads Files
- THEN both MUST see `#8fb8e6` as the primary colour

#### Scenario: A light theme user is not affected by dark values
- GIVEN the same overrides
- WHEN a user who chose the light theme loads Files on a dark operating system
- THEN the user MUST see `#154273`

### Requirement: Derived dark values keep the light value's alpha

When a light colour has an alpha below 1, its derived dark value MUST keep that alpha. The dark
channels MUST be derived from the opaque colour as today, and written as `#rrggbbaa`.

#### Scenario: A translucent surface stays translucent in dark mode
@e2e exclude Generated file content, covered by PHPUnit on DarkPaletteService
- GIVEN `css/tokens/conduction-new.css` declares `--nldesign-hero-cell-background: rgba(255, 255, 255, 0.08)`
- WHEN its dark variant is generated
- THEN the dark value MUST be an 8-digit hex whose alpha is `14`
- AND it MUST NOT be the opaque `#141414` the generator writes today
