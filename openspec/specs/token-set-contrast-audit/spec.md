# token-set-contrast-audit Specification

## Purpose
TBD - created by archiving change shipped-token-set-contrast-audit. Update Purpose after archive.

## Requirements

### Requirement: Automated Contrast Audit Over All Shipped Token Sets
For every token set declared in `token-sets.json` whose design system reads `--nldesign-*` tokens (i.e. `design_system` is not `none`), the app MUST compute WCAG relative-luminance contrast ratios for the fixed pairs using the existing `ContrastService`: `--nldesign-color-primary` vs `--nldesign-color-primary-text` against the 4.5:1 AA text threshold, and `--nldesign-color-primary` vs the set background (`--nldesign-color-background`, or `theming.background_color` when the token is absent) against the 3:1 AA non-text threshold. The values MUST be resolved from the set's `css/tokens/{id}.css` layered over `css/defaults.css`. A pair whose values cannot be resolved to literals MUST be reported as `unevaluated` and MUST NOT be treated as passing.

#### Scenario: Every audited set yields a computed verdict
@e2e exclude backend computation — PHPUnit iterates token-sets.json and asserts a verdict per set
- GIVEN the token sets in `token-sets.json` with a non-`none` design system
- WHEN the contrast audit runs
- THEN each set MUST produce a ratio for the primary/text pair and the primary/background pair
- AND each pair MUST be classified as `AA`, `fail`, or `unevaluated`
- AND no `unevaluated` pair MUST be classified as passing

#### Scenario: A set presented as AA-compliant that falls below AA fails the gate
@e2e exclude CI gate — PHPUnit assertion, not a UI flow
- GIVEN a shipped token set that the documentation presents as WCAG-AA compliant
- WHEN its primary/text ratio is below 4.5:1 (or primary/background below 3:1)
- THEN the audit MUST fail with the set id, the offending pair, the computed ratio, and the threshold

### Requirement: Reproducible Contrast Report

The audit MUST emit a generated report at `docs/reference/contrast-report.md` listing, for every
audited set, its id, the computed primary/text ratio, the computed primary/background ratio, the
applicable thresholds, and the verdict. The report MUST be deterministic: regenerating it from
unchanged token files MUST produce byte-identical output. The report is the machine-checked
source of truth that replaces the shipped-set verdict of the hand-written
`docs/reference/token-audit.md`.

The shipped-set report and the active-configuration compliance evidence report (spec
`compliance-evidence`) MUST share one contrast contract: both MUST compute ratios through
`ContrastService::ratio()`/`parseColor()` (no second contrast implementation anywhere in the
app), and both MUST apply the `unevaluated`-never-passes rule. The shipped-set report audits
each shipped set in isolation over its fixed pairs; the per-instance ACTIVE configuration
(active set + custom overrides, full pair matrix) is the `compliance-evidence` report's scope —
this requirement does not duplicate it.

#### Scenario: Report covers every audited set deterministically

@e2e exclude generated-artifact invariant — PHPUnit compares regenerated output
- GIVEN unchanged token files
- WHEN the report is generated twice
- THEN both outputs MUST be byte-identical
- AND the report MUST contain one row for every token set with a non-`none` design system

#### Scenario: Shipped audit and compliance report agree on shared math

@e2e exclude cross-service invariant — PHPUnit computes one pair through both paths
- GIVEN a token set whose primary/primary-text pair is evaluated by `ShippedTokenSetAuditService`
- AND the same effective values evaluated by `ComplianceReportService` (no overrides configured)
- WHEN both ratios are computed
- THEN the two ratios MUST be identical
- AND both services MUST classify a non-literal value as `unevaluated`

### Requirement: Non-Compliant Sets Are Surfaced in the Apply Dialog
When an admin opens the token-set apply dialog for a shipped set whose audit verdict is `fail` or `unevaluated`, the dialog MUST display a non-blocking contrast warning, reusing the same warning surface already specified for custom uploads (the `custom-token-sets` "Contrast warning resurfaces when applying the set" behavior). The admin MUST still be able to apply the set (the warning is informational, not a block).

#### Scenario: Applying a sub-AA shipped set shows a warning but still applies
- GIVEN a shipped token set with a stored `fail` contrast verdict
- WHEN the admin selects it and the apply dialog opens
- THEN the dialog MUST display the contrast warning with the computed ratio and the AA threshold
- AND the admin MUST still be able to apply the set

#### Scenario: A compliant shipped set shows no contrast warning
- GIVEN a shipped token set whose fixed pairs both meet AA
- WHEN the admin opens its apply dialog
- THEN no contrast warning MUST be shown for that set

**See also**: `app-token-set-selection`'s `GET /api/token-sets` `wcagLevel`
field reuses this exact audit path (`ShippedTokenSetAuditService::auditSet()`)
and cache namespace (`ICache` prefix `nldesign_wcag_level`) to expose the
same per-set verdict to a leaf app's own non-admin picker, and its "Selection
Contrast Is Non-Blocking" requirement extends the non-blocking policy this
spec already applies in the apply dialog to that new consumption path.

### Requirement: Translucent colours are measured as they render

`ContrastService` MUST read `#rgba`, `#rrggbbaa`, and the alpha of `rgba()` and `hsla()`. Before a
ratio is computed, a translucent foreground MUST be blended over its background, and a translucent
background over the page background (`--nldesign-color-background`, else white). This MUST stay
inside the one contrast implementation this spec requires, and a colour that cannot be parsed MUST
stay `unevaluated`.

#### Scenario: Faint text fails even though its opaque colour would pass
@e2e exclude Contrast arithmetic, covered by PHPUnit on ContrastService
- GIVEN text `rgba(0, 0, 0, 0.2)` on background `#ffffff`
- WHEN the pair is evaluated
- THEN the ratio MUST be computed for the blend `#cccccc` on `#ffffff`
- AND the pair MUST fail AA

#### Scenario: An 8-digit hex is evaluated, not skipped
@e2e exclude Contrast arithmetic, covered by PHPUnit on ContrastService
- GIVEN text `#000000cc` on background `#ffffff`
- WHEN the pair is evaluated
- THEN the pair MUST get a ratio and a verdict
- AND it MUST NOT be `unevaluated`
