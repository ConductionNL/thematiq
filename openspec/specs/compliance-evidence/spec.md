# compliance-evidence Specification

## Purpose
TBD - created by archiving change compliance-evidence-report. Update Purpose after archive.
## Requirements
### Requirement: Effective Token Resolution for the Active Configuration

The app MUST resolve the EFFECTIVE value of every token referenced by the compliance pair matrix
for the ACTIVE theme configuration, layering sources in the same order the runtime cascade
applies them (`css-architecture` REQ-CSS-001/REQ-CSS-009):

1. `css/systems/{designSystem}/defaults.css` of the active token set's design system
   (`--nldesign-*` defaults);
2. `css/tokens/{activeTokenSet}.css` (shipped or `custom-*` set);
3. when no explicit background token resolves, the active set's `theming.background_color` from
   `token-sets.json` (or the custom-set manifest), and as last resort `#ffffff`;
4. `css/custom-overrides.css` (admin overrides of Nextcloud `--color-*` tokens) — ALWAYS last,
   winning over all earlier layers.

For pair tokens named in the Nextcloud `--color-*` vocabulary, resolution MUST first consult the
custom-overrides layer, then the corresponding `--nldesign-*` source token per the published
mapping in `css/systems/nldesign/overrides.css`. `var()` indirections MUST be resolved
transitively against the layered declaration map with a bounded depth (at least 4 levels); any
value that does not resolve to a color literal parseable by `ContrastService::parseColor()` MUST
be classified `unevaluated` and MUST NOT be treated as passing. Resolution MUST reuse
`CssParserService` for parsing and `CustomOverridesService::read()` for the overrides layer — no
second CSS parser.

#### Scenario: Custom override changes the reported effective value

@e2e exclude backend resolution — PHPUnit on the service with fixture CSS
- GIVEN the active token set defines `--nldesign-color-primary: #154273`
- AND `custom-overrides.css` contains `--color-primary: #767676 !important;`
- WHEN the compliance report resolves the primary/background pair
- THEN the effective primary value MUST be `#767676` (override layer wins)
- AND the report MUST NOT use the token set's `#154273` for that pair

#### Scenario: Unresolvable value is unevaluated, never passing

@e2e exclude backend resolution — PHPUnit on the service
- GIVEN an effective token value that is `var(--undefined-token)` after transitive resolution
- WHEN the pair containing it is evaluated
- THEN the pair MUST be reported as `unevaluated` with the unresolved token named
- AND the pair MUST NOT count toward the passing total

#### Scenario: Stock Nextcloud configuration still produces a report

@e2e exclude backend resolution — PHPUnit on the service
- GIVEN the active token set is `nextcloud` (`design_system: "none"`) and no custom overrides
- WHEN the compliance report is generated
- THEN the report MUST still be produced
- AND pairs whose tokens have no nldesign-defined effective value MUST be reported as
  `unevaluated` with a note that stock Nextcloud values are outside nldesign's control

### Requirement: Compliance Pair Matrix

The compliance report MUST evaluate exactly the following pair matrix, derived from the
`TokenRegistry` tab groups (login/brand, status, typography, content/border). The matrix MUST be
defined in one place in `ComplianceReportService` and echoed verbatim in the report output so an
auditor can see what was and was not measured.

Text pairs at the 4.5:1 normal-text threshold (SC 1.4.3):

| # | Foreground | Background |
|---|------------|------------|
| 1 | `--color-primary-text` | `--color-primary` |
| 2 | `--color-primary-element-text` | `--color-primary-element` |
| 3 | `--color-primary-light-text` | `--color-primary-light` |
| 4 | `--color-primary-element-light-text` | `--color-primary-element-light` |
| 5 | `--color-main-text` | effective main background |
| 6 | `--color-text-maxcontrast` | effective main background |
| 7 | `--color-text-error` | effective main background |
| 8 | `--color-text-success` | effective main background |
| 9 | `--color-text-warning` | effective main background |

Non-text / UI-component pairs at the 3:1 threshold (SC 1.4.11, also the large-text floor of
SC 1.4.3):

| # | Foreground | Background |
|----|------------|------------|
| 10 | `--color-primary` | effective main background |
| 11 | `--color-primary-element` | effective main background |
| 12 | `--color-error` | effective main background |
| 13 | `--color-warning` | effective main background |
| 14 | `--color-success` | effective main background |
| 15 | `--color-info` | effective main background |
| 16 | `--color-border-maxcontrast` | effective main background |
| 17 | `--color-border-error` | effective main background |
| 18 | `--color-border-success` | effective main background |

"Effective main background" MUST be the custom-overridden `--color-main-background` when set,
else the active set's `--nldesign-color-background`, else the set's `theming.background_color`,
else `#ffffff`.

#### Scenario: Report contains one row per matrix pair

@e2e exclude backend computation — PHPUnit asserts row count and pair labels
- GIVEN any active configuration
- WHEN the compliance report is generated
- THEN the JSON `pairs` array MUST contain exactly 18 entries in matrix order
- AND each entry MUST carry the foreground token, background token, effective literal values (or
  null), computed ratio (or null), threshold, threshold basis (`normal-text` or `ui-component`),
  and verdict (`pass`, `fail`, `unevaluated`)

#### Scenario: Known color pair yields the known ratio

@e2e exclude math verification — PHPUnit with fixture values
- GIVEN an effective configuration where `--color-main-text` resolves to `#000000` and the
  effective main background to `#ffffff`
- WHEN pair 5 is computed
- THEN the reported ratio MUST be 21.00:1 and the verdict `pass`
- AND for a fixture resolving to `#767676` on `#ffffff` the ratio MUST be 4.54:1 (± 0.01)

### Requirement: WCAG 2.2 AA Classification

Every evaluated pair MUST be classified against WCAG 2.2 AA: ratio ≥ 4.5:1 for normal-text
pairs, ratio ≥ 3:1 for large-text/UI-component pairs. The ratio math MUST be the existing
`ContrastService::ratio()` relative-luminance implementation — the compliance report MUST NOT
introduce a second contrast formula. The report summary MUST state totals: pairs passed, failed,
unevaluated — and an overall verdict of `pass` ONLY when zero pairs failed AND zero pairs are
unevaluated; any unevaluated pair caps the overall verdict at `incomplete`.

#### Scenario: One failing pair fails the overall verdict

@e2e exclude classification logic — PHPUnit
- GIVEN 17 passing pairs and one pair at 2.1:1 against a 4.5:1 threshold
- WHEN the summary is computed
- THEN the overall verdict MUST be `fail`
- AND the failing pair MUST be listed with its computed ratio and threshold

#### Scenario: Unevaluated pairs cannot produce a clean pass

@e2e exclude classification logic — PHPUnit
- GIVEN no failing pairs but two `unevaluated` pairs
- WHEN the summary is computed
- THEN the overall verdict MUST be `incomplete`, not `pass`

### Requirement: Report Formats and Metadata

The report MUST be renderable as (a) JSON with a stable documented schema and (b) human-readable
Markdown. Both formats MUST carry identical report metadata: instance id (`instanceid` system
config) and instance base URL, nldesign app version, Nextcloud server version, active token set
id + display name + version (the set's `version` field from `token-sets.json` or the custom-set
manifest when present, else `unversioned`), the active design system id, the generation
timestamp (ISO 8601, UTC), and `overridesHash` — the SHA-256 hex digest of the canonicalized
custom-overrides declaration list (sorted `name: value` lines, empty overrides hashing the empty
canonical form). Given identical configuration and an identical clock, regeneration MUST be
byte-identical (same determinism discipline as `token-set-contrast-audit`'s shipped report).

#### Scenario: Metadata identifies the audited configuration

@e2e exclude serialization — PHPUnit on the renderer
- GIVEN a generated report in either format
- WHEN its metadata block is read
- THEN it MUST contain instance id, instance URL, app version, Nextcloud version, token set id,
  token set version (or `unversioned`), design system id, ISO 8601 UTC timestamp, and the
  overrides SHA-256 hash
- AND changing one custom override and regenerating MUST change `overridesHash`

#### Scenario: Deterministic regeneration

@e2e exclude determinism invariant — PHPUnit with a frozen clock
- GIVEN unchanged configuration and a frozen clock
- WHEN the report is generated twice in each format
- THEN both JSON outputs MUST be byte-identical and both Markdown outputs MUST be byte-identical

### Requirement: Honest Scope Statement

Both report formats MUST embed a scope statement, verbatim in Markdown and as a `scope` metadata
field in JSON, stating that: the report covers the color-contrast of theme tokens ONLY (evidence
toward WCAG 2.2 SC 1.4.3 and SC 1.4.11); it is NOT a WCAG-EM audit and NOT a full WCAG
evaluation; it does not evaluate content, keyboard operability, semantics, or any non-color
criterion; and it is supporting evidence for a toegankelijkheidsverklaring — an expert WCAG-EM
evaluation remains required. No output of this feature — report, endpoint response, occ output,
docs — may claim WCAG compliance of the instance (claim-accuracy discipline,
`openspec/specs/claim-accuracy/spec.md`).

#### Scenario: Scope statement is always present

@e2e exclude output invariant — PHPUnit asserts on both renderers
- GIVEN any generated report, in either format, whatever the verdict
- WHEN the output is inspected
- THEN the scope statement MUST be present
- AND it MUST state the report is not a WCAG-EM audit nor a full WCAG evaluation
- AND a fully-passing report MUST NOT contain any wording claiming the instance "is WCAG
  compliant" or "voldoet aan WCAG"

### Requirement: Admin Export Endpoint

The report MUST be exportable via `GET /apps/nldesign/settings/compliance-report?format=json|markdown`
on `SettingsController`, carrying the same `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)`
posture as every other `/settings/*` endpoint (admin-only, CSRF-protected — no `#[PublicPage]`,
no `#[NoAdminRequired]`, no `#[NoCSRFRequired]`). The response MUST be served as a download with
`Content-Disposition: attachment` and a filename embedding the instance id, token set id, and
date (e.g. `nldesign-compliance-{instanceid}-{tokenSet}-{YYYYMMDD}.json`/`.md`). An unknown
`format` value MUST return HTTP 400; the default format is `json`.

#### Scenario: Admin downloads the JSON report

- GIVEN an authenticated admin on the settings panel
- WHEN they request `/apps/nldesign/settings/compliance-report?format=json`
- THEN the response MUST be `application/json` with `Content-Disposition: attachment`
- AND the body MUST parse as the documented report schema

#### Scenario: Non-admin cannot export

@e2e exclude auth-posture assertion — PHPUnit/middleware test, single-admin test env
- GIVEN a non-admin authenticated user
- WHEN they request the compliance-report endpoint
- THEN Nextcloud's SecurityMiddleware MUST reject the request
- AND no report content MUST be returned

### Requirement: occ Export Command

The app MUST register an occ command `nldesign:compliance-report` (first command of the app,
registered via `appinfo/info.xml` `<commands>`, class `lib/Command/ComplianceReport.php`) with
options `--format=json|markdown` (default `json`) and `--output=<path>` (default: stdout). The
command MUST reuse `ComplianceReportService` — identical output to the endpoint for identical
configuration. Exit code MUST be 0 when the report generates (whatever the verdict) and non-zero
only on generation failure, so audit pipelines distinguish "evidence says fail" from "no
evidence produced".

#### Scenario: Report generated from the command line

@e2e exclude CLI surface — verified via docker exec occ in the Verify tasks
- GIVEN a configured instance
- WHEN `occ nldesign:compliance-report --format=markdown` runs
- THEN the Markdown report MUST be written to stdout
- AND the exit code MUST be 0 even when pairs fail
- AND `--output=/tmp/report.md` MUST write the identical bytes to that path instead


### Requirement: Download From the Admin Panel

The admin settings page MUST offer the compliance evidence report as two download links, one for JSON and one for Markdown, in a section of its own. Each link MUST point at the Admin Export Endpoint with its `format` parameter, so the browser saves the file the endpoint names. The section MUST state that the report covers the colour contrast of the theme tokens only and is not a full WCAG audit.

#### Scenario: An administrator downloads the contrast evidence report from the settings page

- GIVEN an administrator on the Theming settings page with a token set active
- WHEN they look at the "Contrast evidence report" section
- THEN they MUST see a "Download as JSON" link and a "Download as Markdown" link
- AND following the JSON link MUST return the report with `Content-Disposition: attachment`
