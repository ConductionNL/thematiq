# Spec delta: Token Sets (token-set-vocabulary-audit)

Adds the mechanical definition of a *correct* shipped token set — one that declares the
`--nldesign-*` vocabulary its design system actually reads — and the admin surface that says so when
a set does not. The existing "Token Set CSS Structure" requirement stays true exactly as written
(a set MAY override any `--nldesign-*` variable, and an incomplete set MUST still render); this delta
adds the separate statement that a *shipped* set MUST NOT rely on that fallback for the tokens that
carry a brand's identity.

## ADDED Requirements

### Requirement: Shipped Token Set Vocabulary Completeness
Every shipped `css/tokens/{id}.css` file whose `design_system` reads the `--nldesign-*` vocabulary
MUST declare the required semantic tokens itself, MUST NOT declare `--nldesign-*` names no stylesheet
in the app declares a default for or reads, and MUST agree with `token-sets.json` about its own
primary colour. A set that fails any of the three MUST be reported by the audit and MUST either be
fixed or be recorded in the known-incomplete allow-list.

The required semantic tokens are exactly:

```
--nldesign-color-primary            --nldesign-color-text            --nldesign-color-error
--nldesign-color-primary-text       --nldesign-color-text-muted      --nldesign-color-error-rgb
--nldesign-color-primary-hover      --nldesign-color-border          --nldesign-color-warning
--nldesign-color-primary-light      --nldesign-color-border-dark     --nldesign-color-warning-rgb
--nldesign-color-primary-light-hover --nldesign-color-link           --nldesign-color-success
--nldesign-color-header-background  --nldesign-color-link-hover      --nldesign-color-success-rgb
--nldesign-color-header-text                                         --nldesign-color-info
--nldesign-color-nav-background                                      --nldesign-color-info-rgb
--nldesign-font-family              --nldesign-border-radius
--nldesign-border-radius-small      --nldesign-border-radius-large
```

#### Scenario: A set that declares the full required vocabulary is complete
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN `css/tokens/amsterdam.css` declares all 26 required semantic tokens
- AND every `--nldesign-*` name it declares is declared or read by some CSS layer under `css/`
- AND its `--nldesign-color-primary` normalises to the same hex as `token-sets.json`'s
  `theming.primary_color` for `amsterdam`
- WHEN `TokenSetVocabularyAuditService::auditSet()` is called for `amsterdam`
- THEN `missingRequired` MUST be empty
- AND `foreignNldesignNames` MUST be empty
- AND `primaryMismatch` MUST be false
- AND `complete` MUST be true

#### Scenario: Missing required tokens are evaluated against the set file alone
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN `css/tokens/zwolle.css` declares none of the required semantic tokens
- AND `css/systems/nldesign/defaults.css` declares Rijkshuisstijl values for all of them
- WHEN the set is audited
- THEN the audit MUST NOT layer `defaults.css` under the set
- AND `missingRequired` MUST list all 26 required tokens
- AND `complete` MUST be false
- AND the reason MUST be reported as "the set renders as the defaults.css brand, not its own"

#### Scenario: `--nldesign-*` names nothing reads are reported as foreign
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN a shipped set declares `--nldesign-color-blue-40` (a raw upstream palette step)
- AND no `.css` file under `css/` outside `css/tokens/` declares or reads that name
- WHEN the set is audited
- THEN `foreignNldesignNames` MUST contain `--nldesign-color-blue-40`
- AND `complete` MUST be false
- AND a raw palette step MUST instead be declared under the brand prefix (e.g.
  `--zwolle-color-blue-40`), where it cannot masquerade as app vocabulary

#### Scenario: The accepted vocabulary is every name any non-token-set CSS layer declares or reads
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN `css/systems/nldesign/theme.css` reads `--nldesign-logo-url`
- AND neither `defaults.css` nor `utrecht-bridge.css` mentions that name
- WHEN a set declaring `--nldesign-logo-url` is audited
- THEN the name MUST NOT be reported as foreign
- AND the vocabulary scan MUST exclude `css/tokens/` (the audited input)
- AND the vocabulary scan MUST exclude the runtime-generated `custom-overrides.css` and
  `custom-css.css`, so a value an admin typed into the theme editor can never widen the vocabulary

#### Scenario: Token names are matched case-sensitively but not case-restrictively
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN `css/tokens/nijmegen.css` declares `--nldesign-tokenSetOrder-0` (an upstream generator
  artefact with a camelCase segment)
- WHEN the set is audited
- THEN the name MUST be recognised as a declaration
- AND it MUST be reported in `foreignNldesignNames`, because no layer reads it
- AND the vocabulary scan and the declaration parse MUST use the same character class, so the two
  can never disagree about whether such a name exists

#### Scenario: A primary colour that disagrees with the manifest is a mismatch
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN `css/tokens/xxllnc.css` declares `--nldesign-color-primary: #000000`
- AND `token-sets.json`'s entry for `xxllnc` declares `theming.primary_color: "#333333"`
- WHEN the set is audited
- THEN `primaryMismatch` MUST be true
- AND `declaredPrimary` MUST be the normalised manifest value and `cssPrimary` the normalised CSS value
- AND comparison MUST normalise case and expand 3-digit hex to 6-digit before comparing

#### Scenario: An absent or non-literal primary is not double-reported as a mismatch
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN a set does not declare `--nldesign-color-primary` at all, or declares a non-hex value
- WHEN the set is audited
- THEN the defect MUST be reported once, in `missingRequired`
- AND `primaryMismatch` MUST be false
- AND a manifest entry with no `theming.primary_color` MUST NOT produce a mismatch either

#### Scenario: A set whose design system reads no `--nldesign-*` name is not auditable
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN `design-systems.json` declares the `summer-breeze` system's stylesheets
- AND none of those stylesheets references any `--nldesign-*` name
- WHEN `css/tokens/summer-breeze.css` is audited
- THEN `auditable` MUST be false
- AND `missingRequired` and `foreignNldesignNames` MUST be empty
- AND `complete` MUST be true, so the set is never counted as failing
- AND the same MUST hold for a set whose `design_system` is `none` (stock Nextcloud, no stylesheet)
- AND a system that DOES read the vocabulary through a bridge layer (`high-contrast`, `lasuite`,
  `cunningham`) MUST be audited like any `nldesign` set

#### Scenario: Commented-out declarations never count
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService
- GIVEN a set contains `/* --nldesign-color-primary: red; */`
- WHEN the set is audited
- THEN the token MUST still be reported in `missingRequired`
- AND CSS comments MUST be stripped before declaration parsing

#### Scenario: The audit gate fails for an unlisted incomplete set
- GIVEN `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` lists the known-incomplete set ids
- WHEN `tests/Unit/TokenSetVocabularyTest.php` runs
- THEN a set that is auditable, incomplete, and NOT listed MUST fail the test
- AND the failure message MUST name the set and every missing, foreign and mismatched token

#### Scenario: The allow-list can only shrink
- GIVEN an allow-listed set has since been regenerated and now passes all three rules
- WHEN the audit gate runs
- THEN the test MUST fail until that id is deleted from the allow-list
- AND the allow-list MUST be empty once every shipped set is complete

#### Scenario: The audit is runnable without a PHP runtime
- GIVEN a token-set author has no `vendor/` directory and no PHP CLI
- WHEN they run `npm run audit:token-sets`
- THEN a table MUST be printed with one row per shipped set: id, design system, missing count,
  foreign count, primary verdict, and overall verdict
- AND the same allow-list fixture the PHPUnit gate reads MUST be used, so the two can never disagree
  about what is known-broken
- AND `--verbose` MUST list every offending token name
- AND `--check` MUST exit non-zero on an unlisted failure or a stale allow-list entry
- AND the required-token list used by the CLI MUST be asserted equal to the PHP service's constant by
  a test, because the two are duplicated with no build step between them

### Requirement: Incomplete Sets Are Surfaced In The Admin Dropdown
An incomplete shipped set MUST be labelled as such in the admin settings UI, at the point of
selection, with the specific findings available to the admin. The finding MUST travel on the existing
`warnings` channel `TokenSetService::applyWarnings()` already uses for WCAG contrast, and MUST be
distinguishable from a contrast warning without inspecting its other fields.

#### Scenario: Selecting an incomplete set shows the "Incomplete set" badge
- GIVEN the admin opens the nldesign settings page
- WHEN they select a token set whose audit reports it incomplete
- THEN a badge reading "Incomplete set" MUST appear next to the design-system badge
- AND its tooltip MUST list the missing required tokens, the foreign `--nldesign-*` names, and the
  primary-colour disagreement, whichever apply
- AND the badge MUST be hidden entirely for a complete set

#### Scenario: The apply dialog explains the fallback
- GIVEN the admin selects an incomplete set and the apply dialog opens
- WHEN the dialog renders its warnings
- THEN a non-blocking banner MUST state that the missing tokens fall back to the Rijkshuisstijl
  defaults instead of this brand
- AND the banner MUST be separate from the WCAG contrast banner
- AND applying the set MUST NOT be blocked

#### Scenario: The vocabulary finding is distinguishable from a contrast finding
- GIVEN a set has both a contrast warning and a vocabulary warning
- WHEN the `warnings` array reaches the admin JS
- THEN the vocabulary entry MUST carry `kind: 'incomplete'` with `missing[]`, `foreign[]`,
  `primaryMismatch`, `declaredPrimary` and `cssPrimary`
- AND contrast entries MUST keep their existing shape with no `kind` field
- AND the contrast banner MUST exclude the vocabulary entry rather than rendering it as a
  contrast pair

#### Scenario: The custom-set list badge has three states, ranked
- GIVEN the custom token set list is rendered from the uploaded-sets endpoint
- WHEN a listed set carries a warning with `kind: 'incomplete'`
- THEN its badge MUST read "Incomplete set", taking priority over "Contrast warning", because a set
  that never declares the vocabulary is broken in a way no contrast ratio can reveal
- AND a set with only contrast warnings MUST read "Contrast warning"
- AND a set with no warnings MUST read "WCAG AA OK"
- AND note that `TokenSetService::applyWarnings()` returns uploader-supplied warnings for a genuine
  custom upload without auditing it, so this ranking is currently only reachable for a custom id
  shadowed by a shipped manifest entry; auditing custom uploads is out of scope for this change and
  the ranking is specified now so the two paths cannot disagree later

#### Scenario: A complete catalogue stays quiet
- GIVEN every shipped set passes the vocabulary audit
- WHEN the settings page is rendered
- THEN no completeness badge MUST be visible for any set
- AND no vocabulary entry MUST appear in any set's `warnings` array
