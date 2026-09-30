# dark-mode Specification

## Purpose
TBD - created by archiving change dark-mode-token-variants. Update Purpose after archive.
## Requirements
### Requirement: Dark Palette Derivation

The app MUST provide a `DarkPaletteService` that derives a dark palette from a token set's
effective light `--nldesign-*` color declarations (the set's declarations merged over the
design-system defaults). Derivation MUST preserve each color's hue, MUST invert its lightness
scale (background-class tokens land dark, text-class tokens land light, using the token's
`TokenRegistry` category), MAY reduce saturation on background-class tokens only, and MUST leave
non-color tokens (sizes, radii, fonts, urls) out of the dark output so they inherit from the
light layer. Values `ContrastService::parseColor()` cannot parse MUST be skipped, not guessed.

@e2e exclude colour-derivation arithmetic — hue preservation, per-class lightness inversion, non-colour pass-through and unparseable-value skipping are assertions about computed values, not about anything a page renders; proven by tests/Unit/Service/DarkPaletteServiceTest.php (testHuePreservedForBackgroundToken, testLightBackgroundDerivesToDarkLightness, testDarkTextDerivesToLightLightness, testNonColorTokensPassThroughUntouched, testUnparseableValuesAreSkipped, testRgbCompanionsMatchDerivedBase).

#### Scenario: Hue is preserved

- GIVEN the `rijkshuisstijl` set with `--nldesign-color-primary: #154273`
- WHEN `DarkPaletteService` derives the dark palette
- THEN every derived color token MUST keep its source hue within 2 degrees
- AND `--nldesign-color-primary` MUST remain recognisably the organisation's blue

#### Scenario: Lightness scale is inverted per token class

- GIVEN a light background token with HSL lightness ≥ 0.9 and a text token with lightness ≤ 0.3
- WHEN the dark palette is derived
- THEN the background-class token MUST resolve to a dark value (lightness ≤ 0.20)
- AND the text-class token MUST resolve to a light value (lightness between 0.62 and 0.92)

#### Scenario: Non-color tokens pass through untouched

- GIVEN a token set that defines `--nldesign-size-lint: 48px` and `--nldesign-font-family`
- WHEN the dark palette is derived
- THEN neither token MUST appear in the dark output
- AND both MUST keep their light-layer values at render time via normal inheritance

#### Scenario: Unparseable values are skipped

- GIVEN a token whose value is a gradient or a `var()` chain that `parseColor()` returns null for
- WHEN the dark palette is derived
- THEN that token MUST NOT appear in the dark output
- AND no exception MUST be thrown

### Requirement: WCAG Verification Loop

Every generated dark palette MUST pass the app's `ContrastService` pair checks at WCAG AA 4.5:1
before it is written. For each failing derived pair the service MUST adjust the foreground
lightness away from the background (bounded iteration) and, if still failing, snap the foreground
to a guaranteed-passing near-white or near-black value while logging a warning. Hand-authored
dark declarations (see the override requirement) MUST NOT be rewritten by the loop — failures
there MUST produce warnings only.

@e2e exclude contrast-ratio verification — WCAG ratios are computed against token pairs during generation; a browser can photograph the result but cannot assert the loop repaired a pathological pair or warned instead of silently changing a hand-authored one; proven by tests/Unit/Service/DarkPaletteServiceTest.php (testPathologicalPairIsRepairedToPassing, testHandAuthoredFailurePreservedAndWarned, testPassingPairProducesNoWarnings, testBrandPrimaryExceptionKeepsRecognisableBlue, testFailingBrandPrimaryIsNotExempted, testRealShippedSetsPassContrastAfterGeneration).

#### Scenario: Derived output is AA-clean by construction

- GIVEN any shipped token set eligible for derivation
- WHEN its dark variant is generated
- THEN every evaluable `ContrastService` pair in the generated declarations MUST have a contrast
  ratio of at least 4.5:1

#### Scenario: Failing derived pair is repaired

- GIVEN a derivation candidate where `--nldesign-color-primary-text` on
  `--nldesign-color-primary` measures below 4.5:1
- WHEN the verification loop runs
- THEN the foreground lightness MUST be adjusted until the pair passes
- AND if bounded iteration cannot repair it, the foreground MUST be snapped to a passing
  near-white (`#EBEBEB`) or near-black (`#111111`) value
- AND a warning MUST be logged naming the pair and the snap

#### Scenario: Hand-authored failure warns but stands

- GIVEN a token set whose hand-authored dark block declares a pair measuring 3.2:1
- WHEN the dark variant is generated
- THEN the hand-authored values MUST be emitted unchanged
- AND the generation output MUST contain a contrast warning for that pair

### Requirement: Hand-Authored Dark Overrides Win

The generator MUST honour hand-authored dark overrides: a token set CSS file MAY contain a
top-level `@media (prefers-color-scheme: dark) { :root { … } }`
block, and any `--nldesign-*` declaration found in that block MUST replace the algorithmically
derived value for the same token in the generated dark variant. The parser MUST return an empty
override set (never error) for sets without such a block or with malformed CSS.

@e2e exclude generation-time precedence — whether a hand-authored override wins over a derived value is decided before any CSS is served, and a malformed override block degrading to "no overrides" is indistinguishable in the browser from having none; proven by tests/Unit/Service/DarkPaletteServiceTest.php (testGenerateForSetHandAuthoredOverrideWins, testHandAuthoredFailurePreservedAndWarned).

#### Scenario: Override replaces derived value

- GIVEN `css/tokens/example.css` contains a dark block declaring `--nldesign-color-primary: #4844AD`
- WHEN the dark variant for `example` is generated
- THEN the generated file MUST contain `--nldesign-color-primary: #4844AD`
- AND the derived value for that token MUST be discarded

#### Scenario: Set without a dark block derives everything

- GIVEN a token set file with no `@media (prefers-color-scheme: dark)` block
- WHEN its dark variant is generated
- THEN all dark color values MUST come from the derivation algorithm

### Requirement: Generated Dark Variant Files

Dark variants MUST be materialised as static files `css/tokens/dark/<set>.css` at build,
install, or upgrade time — never derived per request. Generation MUST be available as an occ
command (`nldesign:generate-dark-variants`, with `--set` and `--force` options) and as an
`IRepairStep` that regenerates missing or stale files and logs-and-skips when the target
directory is not writable. Each generated file MUST carry a header comment with the generator
version and a hash of the source token set so freshness can be checked. Generation MUST skip
token sets whose `design_system` is `none` or `high-contrast`. Dark variants for custom uploaded
sets MUST be generated at upload time and removed when the custom set is deleted.

@e2e exclude occ command and file lifecycle — these scenarios assert what an `occ` invocation writes to css/tokens/, its exit code, its idempotency without --force and its deletion behaviour; none of that is reachable from a browser session; proven by tests/Unit/Command/GenerateDarkVariantsTest.php (testSetOptionWritesOnlyThatFile, testFullRunSkipsIneligibleSetsWithZeroExitCode, testSecondRunWithoutForceSkipsFreshFile, testForceRewritesFreshFile) and tests/Unit/Service/DarkPaletteServiceTest.php (testGenerateAndWriteIsIdempotentWithoutForce, testGenerateAndWriteForceRewrites, testDeleteDarkVariant, testDiscoverAllSetIds).

#### Scenario: occ command generates a variant

- GIVEN the `amsterdam` token set exists
- WHEN `occ nldesign:generate-dark-variants --set=amsterdam` runs
- THEN `css/tokens/dark/amsterdam.css` MUST be written
- AND the output MUST list the set and any contrast warnings

#### Scenario: Fresh files are skipped without --force

- GIVEN `css/tokens/dark/amsterdam.css` exists with a header hash matching the current
  `css/tokens/amsterdam.css`
- WHEN the command runs without `--force`
- THEN the file MUST NOT be rewritten
- AND with `--force` it MUST be rewritten

#### Scenario: Ineligible sets are skipped

- GIVEN the `nextcloud` set (`design_system: "none"`) and the high-contrast set
- WHEN generation runs over all sets
- THEN no `css/tokens/dark/nextcloud.css` MUST be produced
- AND no dark variant MUST be produced for sets with `design_system: "high-contrast"`
- AND the command exit code MUST remain zero (skips are not failures)

#### Scenario: Repair step degrades gracefully on read-only app dir

- GIVEN the app directory is not writable
- WHEN the repair step runs during upgrade
- THEN it MUST log a warning and complete without error
- AND theming MUST continue to work light-only for sets missing a dark file

#### Scenario: Custom set upload produces a dark variant

- GIVEN an admin uploads a custom token set `custom-voorbeeld`
- WHEN the upload is persisted
- THEN `css/tokens/dark/custom-voorbeeld.css` MUST be generated in the same operation
- AND deleting the custom set MUST also delete its dark variant

### Requirement: Dark Scope Selectors

Generated dark variant files MUST scope all declarations under BOTH activation paths verified in
the NC 34 theming contract: (1) a `@media (prefers-color-scheme: dark)` block whose inner
selector is `body` excluding any explicit theme choice
(`body:not([data-theme-light]):not([data-theme-dark]):not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast])`),
covering the auto ("System default") theme and anonymous pages; and (2) an unconditional
`body[data-theme-dark], body[data-themes*=dark]` block covering an explicit dark choice.
Declarations MUST NOT use `!important` (body-level scope already out-specifies the light `:root`
layer) and MUST NOT restyle a body carrying `data-theme-light`.

#### Scenario: Auto theme follows a dark OS

- GIVEN a user on the "System default" theme (body has `data-theme-default`, no explicit choice)
- AND the OS reports `prefers-color-scheme: dark`
- WHEN the page renders with a generated dark variant loaded
- THEN the media-scoped block MUST apply and nldesign surfaces MUST render dark

#### Scenario: Explicit light choice on a dark OS stays light

- GIVEN a user who explicitly enabled the Light theme (body carries `data-theme-light`)
- AND the OS reports `prefers-color-scheme: dark`
- WHEN the page renders
- THEN neither dark block MUST apply
- AND all nldesign surfaces MUST render with the light palette

#### Scenario: Explicit dark choice on a light OS renders dark

- GIVEN a user who explicitly enabled the Dark theme (body carries `data-theme-dark` and
  `data-themes` containing `dark`)
- AND the OS reports `prefers-color-scheme: light`
- WHEN the page renders
- THEN the `body[data-theme-dark]` block MUST apply and nldesign surfaces MUST render dark

#### Scenario: Anonymous login page follows the OS

- GIVEN an anonymous user on the login page (body has no `data-theme-*` attributes and an empty
  `data-themes`)
- AND the OS reports dark
- WHEN the page renders
- THEN the media-scoped block MUST apply (the `:not()` exclusions all match)

### Requirement: Dark Variant Injection

`Application::injectThemeCSS()` MUST load `tokens/dark/<activeSet>` via `\OCP\Util::addStyle()`
immediately after `tokens/<activeSet>` and before the custom-overrides layer, but only when ALL
of: the active design system is not `none`, the `dark_variants` app config is enabled, and the
generated file exists. A missing file MUST simply not load (no error).

#### Scenario: Dark stylesheet loads in order

- GIVEN the active set is `amsterdam` with `dark_variants` enabled and a generated dark file
- WHEN `injectThemeCSS()` runs
- THEN `tokens/dark/amsterdam` MUST be added directly after `tokens/amsterdam`
- AND before `custom-overrides`

#### Scenario: Missing dark file degrades silently

- GIVEN a token set without a generated dark variant
- WHEN `injectThemeCSS()` runs
- THEN no dark stylesheet MUST be added
- AND no error MUST be logged at error level

### Requirement: Admin Dark Variants Toggle

The app MUST provide an instance-wide admin toggle for dark variants, stored as the `nldesign`
app config `dark_variants` (default enabled, `'1'`), exposed as a checkbox in the admin panel
and as admin-only endpoints `GET/POST /settings/dark-variants` guarded by
`@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)`. Disabling MUST stop dark
variant stylesheets from loading without deleting generated files.

#### Scenario: Toggle disables dark variants

- GIVEN an admin POSTs `dark_variants=0` to `/settings/dark-variants`
- WHEN any page renders afterwards
- THEN no `tokens/dark/*` stylesheet MUST be present in the page head
- AND the generated files MUST remain on disk

#### Scenario: Default is enabled

- GIVEN a fresh install with no `dark_variants` config value
- WHEN `injectThemeCSS()` runs for a set with a generated dark file
- THEN the dark variant MUST load

#### Scenario: Non-admin access denied

- GIVEN a non-admin authenticated user
- WHEN `POST /settings/dark-variants` is called
- THEN the request MUST be rejected by the admin-settings authorization

### Requirement: User Theme Choice Is Never Enforced

The app MUST NOT read, set, or recommend the `enforce_theme` system config, MUST NOT modify any
user's `enabled-themes` preference, and MUST NOT ship CSS that imposes dark styling on a body
carrying an explicit light choice. Dark variants exclusively FOLLOW the theme state Nextcloud
already established (user choice or OS preference).

#### Scenario: No enforce_theme usage in the codebase

- GIVEN the implemented change
- WHEN the app source is searched for `enforce_theme` and `setEnabledThemes`
- THEN there MUST be zero occurrences outside documentation

#### Scenario: OS switch flips theme live without app involvement

- GIVEN a user on the auto theme with the app's dark variant loaded
- WHEN the OS toggles from light to dark
- THEN the rendered palette MUST switch via the CSS media query alone
- AND no nldesign PHP or JS code MUST run to effect the switch


### Requirement: Shipped Dark Logo Variants

A shipped token set whose `theming.logo_dark` is set MUST have that file under `img/logos/`, and the set's committed dark stylesheet `css/tokens/dark/{id}.css` MUST override `--nldesign-logo-url` with it. Every fill colour in a shipped dark logo MUST reach 3:1 against the dark background `#141414` (WCAG 1.4.11). The Epe set MUST ship one, because its light logo draws its letters in near black on a transparent background.

#### Scenario: A user in dark mode sees the Epe logo in light ink

- GIVEN the Epe token set is active and dark variants are enabled
- WHEN a user whose system is in dark mode opens any page
- THEN the dark stylesheet for Epe MUST set `--nldesign-logo-url` to `img/logos/epe-dark.svg`
- AND that file MUST be served with status 200
