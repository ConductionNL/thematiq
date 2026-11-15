# Spec delta: shell catalogue (shell-catalogue-dark-roles)

## ADDED Requirements

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

