## ADDED Requirements

### Requirement: Selection and highlight pairs are audited
The contrast audit MUST check two more pairs for every audited set that declares either side: `--nldesign-color-text-selection` against `--nldesign-color-background-selection` composited over the set background, and `--nldesign-color-text` against `--nldesign-color-mark`, each against the 4.5:1 text threshold.

#### Scenario: A set that only moves the selection wash is audited
@e2e exclude backend computation; PHPUnit asserts the verdict per set.
- GIVEN a set declares `--nldesign-color-background-selection` and not the text side
- WHEN the contrast audit runs
- THEN the pair MUST be evaluated with Nextcloud's own selected-text colour for that scheme

#### Scenario: A pale highlight under dark text fails
@e2e exclude backend computation; PHPUnit asserts the verdict per set.
- GIVEN a set declares `--nldesign-color-mark: #222222` and `--nldesign-color-text: #1a1a1a`
- WHEN the contrast audit runs
- THEN the pair MUST be reported below 4.5:1
- AND the report MUST name both tokens

#### Scenario: A set that declares neither side is not reported
@e2e exclude backend computation; PHPUnit asserts the verdict per set.
- GIVEN a set declares neither selection token nor `--nldesign-color-mark`
- WHEN the contrast audit runs
- THEN the two pairs MUST NOT appear for that set
- BECAUSE Nextcloud's own values apply and are Nextcloud's responsibility
