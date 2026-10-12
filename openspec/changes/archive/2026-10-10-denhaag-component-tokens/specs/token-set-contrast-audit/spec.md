# Spec delta: token-set-contrast-audit (denhaag-component-tokens)

@e2e exclude backend computation, PHPUnit iterates `token-sets.json` and asserts a verdict per
set and pair.

## ADDED Requirements

### Requirement: The audit checks the text pairs the Den Haag components draw
For every set the audit already covers, the shipped token set contrast audit MUST resolve the
generated Den Haag section of `css/public-bridge.css` under the set, and MUST check these pairs
against 4.5:1 with `ContrastService`: the current, checked and not-checked step marker text on
its fill or the page background; the case card title and subtitle on the card background; the
action row's warning date on the action background; each data badge variant's text on its
rendered fill; the side navigation link and active link on the page background; and the file
link on the page background. A pair that cannot be resolved to literals MUST be reported as
`unevaluated` and MUST NOT pass. The report MUST list each pair per set.

#### Scenario: A set whose current step is unreadable fails
- **GIVEN** a set whose primary text colour on its primary colour is below 4.5:1
- **WHEN** the audit runs
- **THEN** the step marker current pair MUST be `fail` with the set id, the property names and the ratio

#### Scenario: The report carries the new pairs
- **GIVEN** unchanged token files and an unchanged mapping
- **WHEN** `docs/reference/contrast-report.md` is generated twice
- **THEN** both outputs MUST be byte-identical and MUST hold the Den Haag pairs for every audited set
