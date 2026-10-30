# Spec delta: menu role (menu-role)

## ADDED Requirements

### Requirement: The main menu colour is a shell role

The shell roles MUST include `menu`, read from `--thematiq-shell-menu`, then
`--nldesign-component-navigation-background-color`, then `--nldesign-color-nav-background`, and
white when none is set. The endpoint, the shell catalogue and the set file MUST carry it. An upload
that declares `--thematiq-shell-menu` and no `--nldesign-color-nav-background` MUST store the value
under both names.

#### Scenario: The read order
@e2e exclude a pure mapping with no page; proven by tests/Unit/Service/ShellRoleMapperTest.php (testTheMenuIsTheNavigationColumn)
- GIVEN a set with a navigation colour `#eeeeee` and a menu colour `#123456`
- THEN `menu` MUST be `#123456`

#### Scenario: A set file with a menu colour
@e2e exclude the stored declarations are what matters; proven by tests/Unit/Service/OpenProjectSetFileTest.php (testTheMenuColourBecomesTheNavigationColour)
- GIVEN a set file with `tokens.menu` `#20242a` and no navigation colour
- WHEN it is uploaded
- THEN `--nldesign-color-nav-background` MUST be `#20242a`
