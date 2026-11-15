# Spec delta: Token Sets (nlds-theme-converter)

Stage 1 added "Shipped Token Set Vocabulary Completeness" and an allow-list of the sets that fail it.
This delta closes that allow-list: every auditable shipped set is complete with no exemption, and a
set the converter produced carries its provenance. Brand-file sets stay with
`scripts/generate-brand-set.mjs` and `summer-breeze` keeps its own `--summer-*` vocabulary
(decision 126, 9 Oct 2026: the later merged work wins over this change's first draft). The "Token Set CSS Structure"
requirement stays true as written — one flat `:root`, no at-rules, defaults still cover anything a
set omits — the converter is simply what makes a shipped set stop relying on that fallback.

## ADDED Requirements

### Requirement: Converted Sets Carry Provenance And Are Reproducible From It
A shipped `css/tokens/{id}.css` whose header names `TokenSetConverterService` (38 sets when this
change was archived) MUST carry a provenance block naming its input kind, its upstream source and
version, the converter version, the mapping table SHA-256 and the applied / adapted / kept / skipped
counts, and MUST be reproducible by re-running the converter over that recorded source, which is what
the nightly sync (`scripts/sync-upstream-tokens.mjs`) does. A set built from a brand file MUST keep
`scripts/generate-brand-set.mjs` as its entry point and MUST name it in its header; a hand-authored
set is neither. The sync MUST regenerate converted sets only and MUST leave brand-file and
hand-authored sets untouched, because their values are a person's or a brand file's choice, not the
converter's.

#### Scenario: A converted set records what produced it
@e2e exclude Filesystem shape — tests/Unit/TokenSetProvenanceTest.php over css/tokens/*.css
- GIVEN any shipped set whose header names `TokenSetConverterService`
- WHEN its file is inspected
- THEN it MUST contain a provenance comment with the input kind, the source, the converter version
  and the mapping table SHA-256
- AND the applied / adapted / kept / skipped counts MUST be present

#### Scenario: A brand-file set names its generator, not the converter
@e2e exclude Filesystem shape — tests/Unit/TokenSetProvenanceTest.php over css/tokens/*.css
- GIVEN a shipped set built from a brand file (for example `denhaag` or `example-gemeente`)
- WHEN its header is inspected
- THEN it MUST name `scripts/generate-brand-set.mjs`
- AND it MUST NOT carry a converter provenance block

#### Scenario: The sync re-converts converted sets and leaves the others alone
@e2e exclude Regeneration logic — tests/vitest/syncUpstreamTokens.spec.js (classifySet)
- GIVEN the nightly sync runs over an upstream themes checkout
- WHEN it classifies the shipped sets
- THEN a set carrying the converter header and an upstream source MUST be re-converted from it
- AND a brand-file or hand-authored set MUST be reported as hand authored and MUST NOT be written

#### Scenario: Converting the same input twice gives the same file
@e2e exclude Regeneration check — `npm run convert:theme:check` and TokenSetConverterParityTest
- GIVEN the committed converter fixtures under `tests/Unit/fixtures/converter/`
- WHEN the converter is re-run over them
- THEN the emitted CSS MUST be byte-equal to the committed expectation in both runtimes
- AND the check command MUST exit non-zero when it is not

### Requirement: The Known-Incomplete Allow-List Is Empty
`tests/Unit/fixtures/token-set-vocabulary-allowlist.json` MUST hold an empty `sets` array, and
`TokenSetVocabularyTest` MUST guard every auditable shipped set with no exemption. A set whose design
system loads no stylesheet (`nextcloud`, design system `none`) is the documented non-audited case;
`summer-breeze` is audited against its own vocabulary (see the next requirement).

#### Scenario: Every auditable shipped set is complete
@e2e exclude Filesystem audit — PHPUnit on TokenSetVocabularyAuditService and `npm run audit:token-sets`
- GIVEN every shipped set
- WHEN the vocabulary audit runs over every `css/tokens/*.css`
- THEN every audited set MUST report `complete`
- AND only sets of a design system that loads no stylesheet MUST be reported as not audited
- AND the allow-list MUST be empty

#### Scenario: A newly added incomplete set fails the gate immediately
@e2e exclude Filesystem audit — PHPUnit gate
- GIVEN a new shipped set that declares none of the required semantic tokens
- WHEN the gate runs
- THEN it MUST fail, naming the set and the missing tokens
- AND the failure MUST NOT be suppressible by adding the set to the allow-list

### Requirement: Summer Breeze Keeps Its Own Vocabulary
`summer-breeze` MUST NOT be given an `--nldesign-*` semantic layer and MUST NOT be converted: its
stylesheets read its own `--summer-*` vocabulary, and the audit judges it against that vocabulary
("A design system with its own vocabulary is audited against it").

#### Scenario: Summer Breeze declares no nldesign layer
@e2e exclude Filesystem shape — tests/Unit/TokenSetProvenanceTest.php; the rendered result is covered by tests/e2e/spec-coverage/summer-breeze.spec.ts
- GIVEN `css/tokens/summer-breeze.css`
- WHEN its declarations are read, comments removed
- THEN it MUST declare no `--nldesign-*` custom property
- AND it MUST carry no converter provenance block
- AND the vocabulary audit MUST report it auditable and complete against `--summer-*`

### Requirement: Token Set Manifest Entries Agree With Their CSS
Extends the stage 1 `primaryMismatch` rule from a detection to a guarantee: a shipped set's
`token-sets.json` `theming.primary_color` MUST equal its `--nldesign-color-primary` after hex
normalisation, and the converter MUST route a theme's page background to the manifest entry's
`theming.background_color` rather than to the content background.

#### Scenario: The manifest primary follows the CSS, not the reverse
@e2e exclude Filesystem audit — tests/Unit/TokenSetVocabularyTest.php::testEveryShippedSetIsCompleteOrAllowListed
- GIVEN `xxllnc`, whose manifest primary contradicted its CSS before this change
- WHEN the vocabulary audit runs over every shipped set
- THEN `primaryMismatch` MUST be false for every shipped set

#### Scenario: A converted page background lands in the manifest entry
@e2e exclude Pure conversion — tests/vitest/tokenConverter.spec.js and the parity suite
- GIVEN a theme whose source declares a page background colour
- WHEN it is converted
- THEN the returned manifest entry's `theming.background_color` MUST carry that colour
- AND the CSS MUST NOT target `--color-main-background`
