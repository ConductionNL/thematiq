# shell-catalogue Specification

## Purpose
Export every shipped token set as shell roles. Created by change `shell-catalogue`, extended by `shell-catalogue-dark-roles` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: Every shipped set is in the catalogue; The catalogue is reproducible; A logo is redistributable only when the repository says so; A font is redistributable only under a stated licence; A set with a dark variant has dark roles; The dark roles read on each other.

## Requirements

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

### Requirement: A set with a dark variant has dark roles

A catalogue entry MUST hold `dark.tokens`, the eighteen shell roles for a dark page, when the set
ships `css/tokens/dark/<id>.css`, and MUST NOT hold a `dark` block when it does not. The dark roles
MUST be mapped by `ShellRoleMapper` from the set's light declarations with the declarations of
that committed dark file over them. No dark value MAY be derived by another rule. Each dark role
MUST be a non-empty string.

#### Scenario: A set with a dark variant
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN `css/tokens/dark/zuiddrecht.css` declares `--nldesign-color-text`
- WHEN the catalogue is generated
- THEN `dark.tokens.ink` for `zuiddrecht` MUST be that value
- AND `dark.tokens.radius` MUST equal the light `radius`

#### Scenario: The stock set
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN the `nextcloud` set ships no dark variant
- WHEN the catalogue is generated
- THEN its entry MUST hold no `dark` block

### Requirement: The dark roles read on each other

In every dark block, `ink`, `text`, `muted` and `link` MUST reach 4.5:1 on `ground`, and
`primary-deep` MUST reach 4.5:1 on `primary-light`. A set that names no page background MUST get
Nextcloud's dark page (`#171717`) as its dark `ground`, never the white of its light page. When a
set's darker primary step does not reach 4.5:1 on its dark `primary-light`, `primary-deep` MUST be
the primary, which is what Nextcloud writes on that tint, or failing that the `ink`.

#### Scenario: A set without a page background
@e2e exclude Generated data file, covered by PHPUnit (ShellCatalogueTest)
- GIVEN the `enschede` set names no page background
- WHEN the catalogue is generated
- THEN its dark `ground` MUST be `#171717`
- AND its dark `ink` MUST reach 4.5:1 on it

#### Scenario: The selected item on a dark page
@e2e exclude Mapping rule, covered by PHPUnit (ShellRoleMapperTest, ShellCatalogueTest)
- GIVEN a dark variant whose primary hover is darker than its dark tint allows
- WHEN the dark roles are mapped
- THEN `primary-deep` MUST reach 4.5:1 on `primary-light`
