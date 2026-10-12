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
