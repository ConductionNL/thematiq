# Spec delta: Token Sets (every-shipped-set-selectable)

Two requirements change. "Only Fully Functional Brands Are Selectable" is rewritten: its rule
was a hand-written constant holding 2 of 59 shipped sets, and it is now derived from the
vocabulary audit it always named as its own exit condition. "Shipped Token Set Instance
Coverage" gains the distinction between a dimension that is measured and one that is gated,
because one of its four bars rested on a measurement that turned out to be wrong.

## Copied to the canonical spec

Both are already in `openspec/specs/token-sets/spec.md`, verbatim, so every `@spec` anchor
resolves against the source of truth rather than against an unmerged change, and so the phpcs
`@spec` rule — which refuses a tag naming a change directory — is satisfied. The canonical copy
is the one every tag points at.

- `### Requirement: Only Fully Functional Brands Are Selectable` — rewritten around the two
  measured conditions, the shipped file with no manifest entry, the statement that the bridge
  figure must not affect selectability, and a scenario for the anti-narrowing gate.
- `### Requirement: Shipped Token Set Instance Coverage` — four dimensions measured, three
  gated; `$reported` reasons; five failure cases instead of three.

## What changed in each

**Only Fully Functional Brands Are Selectable.** The previous text said: "Today that leaves
`nextcloud` and nothing else: every other shipped brand is still discovered, still served and
still in the catalogue, but is not offered for selection until it passes the vocabulary audit."
That condition has been met, so the rule is now derived from the audit instead of from a list.
The scenario "The allowlist is the only shipped set offered" is replaced by "Every named shipped
set the audit passes is offered"; "Adding a brand to the allowlist is an audit outcome" is
deleted, because there is no list to add a brand to; and three scenarios are added — a
vocabulary-incomplete set is not offered, a set that declares no component tokens is still
offered, and the picker cannot be narrowed again without failing.

**Shipped Token Set Instance Coverage.** The previous text made all four dimensions bars. One of
those bars rested on a measurement that was wrong, so the requirement now distinguishes the
three GATED dimensions from the one that is measured and published and must not fail anything,
states the measurement behind the split, and requires a `$reported` reason for every non-gated
dimension. "A set that dresses no components is reported and must be accounted for" is replaced
by "A set that declares no component tokens is reported, not failed"; "A dimension cannot leave
the gate without a written reason" is added; and the "allow-listed set that starts passing"
scenario moves from `bridge` to `logo`, which is still gated.

Both blocks below are the full replacement text, copied verbatim from
`openspec/specs/token-sets/spec.md`, because a MODIFIED requirement replaces the whole block.

## MODIFIED Requirements

### Requirement: Only Fully Functional Brands Are Selectable
The admin dropdown MUST offer every shipped token set that is both NAMED in `token-sets.json` and not reported incomplete by `TokenSetVocabularyAuditService`, plus every admin-imported `custom-*` set. Both conditions MUST be measured at read time rather than held in a hand-written list, so the picker cannot go stale: a set that stops passing the audit stops being offered with no code change, and a set that starts passing is offered with no code change either.

A shipped token file with no `token-sets.json` entry MUST NOT be offered. Discovery is filesystem-based, so the shared role layer `css/tokens/conduction.css` is found like any other file, but it has no name, description or theming of its own and is an input to `scripts/generate-brand-set.mjs`, not a theme.

The number of `--utrecht-*` component tokens a set declares MUST NOT affect selectability. A set that declares none of them adopts the NL Design System's component geometry and type scale while still branding the component colours from its own semantic layer, which is a working theme rather than a broken one.

Narrowing a picker MUST NOT be able to change what an instance is doing, so three ids survive both conditions.

#### Scenario: Every named shipped set the audit passes is offered
- GIVEN an instance running the stock set
- AND `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` reports no incomplete set
- WHEN the admin panel builds its dropdown
- THEN the dropdown MUST contain every id in `token-sets.json`
- AND it MUST NOT contain a shipped token file that has no `token-sets.json` entry
- AND the full catalogue MUST remain unchanged: discovery, the public catalogue and the preview endpoint still answer for every shipped file

#### Scenario: A vocabulary-incomplete set is not offered
@e2e exclude The GIVEN cannot be produced on an instance: every shipped set passes the vocabulary audit and an upload is never vocabulary-audited, so there is no incomplete set to select. tests/Unit/Service/TokenSetServiceSelectableTest.php::testStockInstanceOffersEveryNamedCompleteSet lays down a named nldesign set declaring only a primary and asserts it is absent from the picker and present in the catalogue.
- GIVEN a named shipped set whose file declares none of the required semantic vocabulary
- WHEN the dropdown is built
- THEN that set MUST NOT be offered
- BECAUSE it renders as the `defaults.css` brand rather than its own, and an admin cannot tell that from a dropdown

#### Scenario: A set that declares no component tokens is still offered
- GIVEN a named shipped set that declares none of the `--utrecht-*` names `css/systems/nldesign/utrecht-bridge.css` reads
- WHEN the dropdown is built
- THEN that set MUST be offered
- BECAUSE the bridge resolves 42 of its 84 declarations from `--nldesign-*` tokens the set declares, 38 from non-colour literals and 3 from a colour literal, so the set's own brand colours reach its components

#### Scenario: The active set is always selectable
- GIVEN the instance is running a set the two conditions would otherwise drop
- WHEN the dropdown is built
- THEN that set MUST still be offered
- BECAUSE dropping it would render the panel with no option selected, and the first save would silently re-theme the instance to whatever happened to come first

#### Scenario: A set a group mapping points at is always selectable
- GIVEN a per-group mapping assigns a token set to a group
- WHEN the dropdown is built
- THEN that set MUST still be offered
- BECAUSE the group picker is fed from this same list, and a group's theme would disappear from the UI while still being applied

#### Scenario: An imported set is always selectable
- GIVEN an admin has imported a `custom-*` set
- WHEN the dropdown is built
- THEN that set MUST be offered unconditionally
- BECAUSE the importer tells the admin their upload was added and selectable

#### Scenario: The picker cannot be narrowed again without failing
@e2e exclude A rule about the implementation of getSelectableTokenSets(), asserted against the real css/tokens/ and token-sets.json by tests/Unit/Service/TokenSetServiceSelectableTest.php::testEveryNamedShippedSetTheAuditPassesIsOfferedOnTheRealCatalogue, which names every set a narrowing withheld. A browser cannot see the difference between a deliberate narrowing and a correct one.
- GIVEN a change that withholds a named shipped set the audit passes
- WHEN the test suite runs
- THEN it MUST fail
- AND the failure MUST name the sets that were withheld

### Requirement: Shipped Token Set Instance Coverage
Every selectable shipped token set — one with an entry in `token-sets.json` whose
`design_system` reads the `--nldesign-*` vocabulary — MUST be measured on four dimensions and
published. Three of the four are GATED: each MUST either be at its bar or be recorded in
`tests/Unit/fixtures/token-set-coverage-allowlist.json` with a reason of at least 20
characters. The fourth is measured and reported and MUST NOT fail anything.

A dimension that is measured but not gated MUST carry a written reason of at least 20
characters under `$reported` in that same fixture, and a gated dimension MUST NOT carry one.
Demoting a dimension is the only edit that lowers the allow-list count with no set improving,
so it MUST be impossible to do silently.

The four dimensions are:

- **bridge** — the number of the `--utrecht-*` custom properties
  `css/systems/nldesign/utrecht-bridge.css` reads that the set declares. MEASURED AND REPORTED,
  NOT GATED: of the bridge's 84 declarations, 42 fall back to a `--nldesign-*` token the set
  itself declares, 38 to a non-colour literal and 3 to a colour literal, so a set that declares
  none of them adopts the design system's component geometry and type scale while still branding
  the component colours from its own semantic layer. The denominator MUST be derived from the
  bridge file with comments stripped, never written as a literal. The dimension does not apply
  to a set whose design system does not link the bridge.
- **font** — the first family of the set's `--nldesign-font-family`, with `var()` chains
  resolved. The bar is that the family has an `@font-face` in a stylesheet the set's own design
  system LINKS, or is a system family, or is declared undistributable in the set's `font`
  block.
- **logo** — the bar is that the set's `theming.logo` names a file.
- **contrast** — the bar is a verdict of `pass` in `docs/reference/contrast-report.json`, which
  MUST be generated by the same service that generates the markdown report, so the audit never
  reimplements the contrast maths in a second runtime.

The audit MUST fail in five distinct cases, and all five MUST be exercised by tests that move
the real data:

1. a set below the bar on a GATED dimension where it is not allow-listed;
2. a set that is allow-listed on a gated dimension it now passes, so the entry MUST be deleted;
3. an allow-list entry whose reason is missing or shorter than 20 characters;
4. a dimension that is measured but not gated and carries no `$reported` reason;
5. a `$reported` reason for a dimension that is in fact gated, or an allow-list entry under a
   dimension nothing gates.

The measurement MUST be published as a generated reference page
(`docs/reference/token-set-coverage.md`), the page MUST record the counts it was generated
from, and the page MUST be covered by a staleness check.

A token file with no entry in `token-sets.json` is not selectable and MUST NOT be measured on
these four dimensions; `css/tokens/conduction.css` is the shared role layer, not a theme.

#### Scenario: A set that declares no component tokens is reported, not failed
@e2e exclude No browser is involved: the audit is a filesystem measurement over css/tokens/ and css/systems/nldesign/utrecht-bridge.css, and no surface on an instance shows a bridge figure. tests/vitest/tokenSetCoverage.spec.js asserts the figure is published for every bridge-zero set and that none of them is reported as a failure.
- GIVEN `css/tokens/zwolle.css` declares none of the `--utrecht-*` names the bridge reads
- WHEN the coverage audit runs
- THEN its `bridge` figure MUST be `0` of the number the bridge file itself contains
- AND the published report MUST carry that figure
- AND the audit MUST NOT report it as a failure, and MUST NOT require an allow-list entry for it

#### Scenario: A dimension cannot leave the gate without a written reason
@e2e exclude The GIVEN is the state of a test fixture read by a CLI, not of a running Nextcloud. tests/vitest/tokenSetCoverage.spec.js proves both halves with probes: an empty `$reported` is reported as reasonless, and a `$reported` entry for a gated dimension is reported as stale.
- GIVEN a dimension the audit measures but does not gate
- WHEN its `$reported` reason is missing or shorter than 20 characters
- THEN `node scripts/audit-token-sets.mjs --check` MUST exit non-zero
- AND a `$reported` reason for a dimension that IS gated MUST also fail
- BECAUSE demoting a dimension lowers the allow-list count with nothing fixed

#### Scenario: An allow-listed set that starts passing fails the gate until the entry is deleted
@e2e exclude The GIVEN cannot be produced on an instance: it is a state of a test fixture read by a CLI, not of a running Nextcloud. tests/vitest/tokenSetCoverage.spec.js proves it with a probe that raises a listed set above the bar, and the CLI was run with an entry deleted by hand to confirm it exits 1.
- GIVEN `tests/Unit/fixtures/token-set-coverage-allowlist.json` lists `zwolle` under `logo`
- WHEN `zwolle` gains a logo and its `logo` dimension starts passing
- THEN the audit MUST report the entry as one that now passes
- AND `node scripts/audit-token-sets.mjs --check` MUST exit non-zero
- AND the gate MUST pass again only once the entry is deleted

#### Scenario: An allow-list entry with no reason is not an allow-list entry
@e2e exclude The GIVEN is a test fixture value, not instance state. tests/vitest/tokenSetCoverage.spec.js asserts a reason under 20 characters is reported.
- GIVEN an entry whose value is an empty string or a word such as `todo`
- WHEN the audit runs
- THEN it MUST be reported as having no usable reason
- AND the gate MUST exit non-zero

#### Scenario: The denominator comes from the bridge, not from a constant
@e2e exclude The GIVEN is the content of a stylesheet in the package, which no instance can change. tests/vitest/tokenSetCoverage.spec.js recomputes the denominator from the file and compares it to the audit's.
- GIVEN `css/systems/nldesign/utrecht-bridge.css` documents its own pattern in a comment that
  names `--utrecht-Y`
- WHEN the audit counts the names the bridge reads
- THEN comments MUST be stripped first
- AND the denominator MUST equal the number of distinct `--utrecht-*` names outside comments
