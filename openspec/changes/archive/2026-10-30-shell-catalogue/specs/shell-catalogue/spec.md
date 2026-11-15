# Spec delta: shell catalogue (shell-catalogue)

Another product bundles every shipped token set as shell roles, and knows which logos and fonts it
may carry.

## ADDED Requirements

### Requirement: Every shipped set is in the catalogue

`docs/reference/shell-catalogue.json` MUST hold `version` (the integer `1`), `generated_from`,
`sets` and `fonts`. `sets` MUST hold exactly one entry per `css/tokens/<id>.css`, ordered by id,
each with `id`, `name`, `tokens` and `logo`. `tokens` MUST be the eighteen shell roles
`ShellRoleMapper` gives the shell tokens endpoint for that set, each a non-empty string. The
catalogue MUST be built from files only: no administrator's value and no uploaded logo.

#### Scenario: A shipped set
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN the token set `zuiddrecht` is shipped
- WHEN the catalogue is generated
- THEN it MUST hold one entry with id `zuiddrecht` and name `Gemeente Zuiddrecht`
- AND its `tokens` MUST hold all eighteen roles

### Requirement: The catalogue is reproducible

Two runs over the same tree MUST write the same bytes. The catalogue MUST NOT hold a date, and its
committed form MUST NOT hold a version or a commit. The tests MUST fail when the committed file
differs from a fresh run.

#### Scenario: A token set changed and the catalogue was not regenerated
@e2e exclude Staleness guard, covered by PHPUnit (ShellCatalogueTest)
- GIVEN a change to a shipped token set
- AND the committed catalogue was not regenerated
- WHEN the tests run
- THEN they MUST fail and name `composer docs:shell-catalogue`

### Requirement: A logo is redistributable only when the repository says so

`logo` MUST hold `file` and `inverse_file` (repository-relative paths or null), `licence`,
`redistributable` and `basis`. `redistributable` MUST be `true` only when this repository states
that the logo is made up or Conduction's own, `false` only when the repository states that it may
not be passed on, and `null` otherwise. The repository default licence in `REUSE.toml` alone MUST
NOT make a logo redistributable. `basis` MUST name the statement the answer rests on.

#### Scenario: A real municipality's logo
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN `img/logos/utrecht.svg` carries no statement of its own
- WHEN the catalogue is generated
- THEN `redistributable` for `utrecht` MUST be null

#### Scenario: A made-up municipality
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN `token-sets.json` describes `zuiddrecht` as a municipality that does not exist
- WHEN the catalogue is generated
- THEN `redistributable` for `zuiddrecht` MUST be true
- AND its dark logo MUST be in the list of files to copy

#### Scenario: The Rijksoverheid mark
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN `docs/reference/assets.md` quotes that use of the Rijkshuisstijl logo is forbidden
- WHEN the catalogue is generated
- THEN `redistributable` for `rijkshuisstijl` MUST be false

### Requirement: A font is redistributable only under a stated licence

`fonts` MUST hold one entry per font family a set's `font-family` role names, system fonts aside,
with `family`, `sets`, `shipped`, `files`, `licence`, `licence_files`, `redistributable` and
`basis`. `redistributable` MUST be `true` only when thematiq ships files for the family and
`REUSE.toml` names those files under a licence that allows redistribution, and `licence_files` MUST
then name the notice that travels with them. It MUST be `false` when the repository says the
family may not be passed on, and `null` otherwise.

#### Scenario: A self-hosted open font
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN thematiq ships Fira Sans and `REUSE.toml` names its files OFL-1.1
- WHEN the catalogue is generated
- THEN Fira Sans MUST be redistributable, with `OFL.txt` in `licence_files`

#### Scenario: Marianne
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN thematiq ships Marianne and `MARIANNE-LICENCE.md` reserves it for French State administrations
- WHEN the catalogue is generated
- THEN Marianne MUST NOT be redistributable
