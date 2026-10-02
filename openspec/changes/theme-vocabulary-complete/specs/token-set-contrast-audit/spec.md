## ADDED Requirements

### Requirement: Selection and highlight pairs are audited
The contrast audit MUST report two more pairs for every audited set that declares their settable side, each against the 4.5:1 text threshold: `--nldesign-nc-color-text-selection` against the selection wash Nextcloud derives from the set's primary (20% of it over the set's background), and `--nldesign-color-text` against `--nldesign-nc-color-mark`.

#### Scenario: A set that moves the selected-text colour is audited against the derived wash
@e2e exclude backend computation; `tests/Unit/Service/SettableContrastPairsTest.php` asserts it.
- GIVEN a set declares `--nldesign-nc-color-text-selection` and a primary colour
- WHEN the contrast audit runs
- THEN the pair MUST be evaluated against 20% of that primary over the set's background

#### Scenario: A pale highlight under dark text fails
@e2e exclude backend computation; `tests/Unit/Service/SettableContrastPairsTest.php` asserts it.
- GIVEN a set declares `--nldesign-nc-color-mark: #222222` and `--nldesign-color-text: #1a1a1a`
- WHEN the contrast audit runs
- THEN the pair MUST be reported below 4.5:1
- AND the report MUST name both tokens

#### Scenario: A set that declares neither side is not reported
@e2e exclude backend computation; `tests/Unit/Service/SettableContrastPairsTest.php` asserts it.
- GIVEN a set declares neither `--nldesign-nc-color-text-selection` nor `--nldesign-nc-color-mark`
- WHEN the contrast audit runs
- THEN the two pairs MUST NOT appear for that set
- BECAUSE Nextcloud's own values apply and are Nextcloud's responsibility
