# Spec: Per-group theming

## MODIFIED Requirements

### Requirement: An administrator delegates a group mapping

A group mapping entry MUST be able to carry `delegated` and `allowedTokenSets`. An administrator MUST be able to mark an entry as delegated on Settings > Administration > Theming and choose its allowed token sets. The allowed list MUST NOT be empty, MUST contain the entry's current set and MUST name only available sets. Entries stored before this change MUST read as not delegated. Delegation MUST NOT change the resolution order.

#### Scenario: An administrator delegates a group

@e2e exclude needs a group with a subadmin on the shared CI instance; proven by tests/vitest/admin-group-delegation.spec.js and tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testAdminEndpointsCarryTheDelegationFields

- GIVEN an administrator on Settings > Administration > Theming with a mapping for group `gemeente-a` to `rijkshuisstijl`
- WHEN they mark it as delegated with allowed sets `rijkshuisstijl` and `gemeente-a-huisstijl` and save
- THEN the mapping MUST show the row as delegated with both allowed sets
- AND members of `gemeente-a` MUST still see `rijkshuisstijl` until a choice is made

#### Scenario: Undelegated groups stay locked

@e2e exclude needs a subadmin account on the shared CI instance; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testRefusedChoicesChangeNothing and ::testGroupHouseStyleBlockOnlyForSubadminsOfDelegatedGroups

- GIVEN a mapping for group `concern` that is not delegated
- WHEN a subadmin of `concern` opens Settings > Administration > Theming
- THEN they MUST NOT see a house style choice for `concern`

### Requirement: A group subadmin chooses the house style of a delegated group

A subadmin of a delegated group MUST be able to choose that group's token set from its allowed list in the block "House style of my groups" in the Theming section of the administration settings. The block MUST be a delegated setting (`IDelegatedSettings`), named "House style of my groups" under Administration privileges, so an administrator can grant it to a group. It MUST appear only for users who are subadmin of at least one delegated group. The personal settings MUST NOT carry a thematiq block. The endpoints MUST require that delegated setting (`#[AuthorizedAdminSetting]`, which administrators always pass) and MUST check, on every call, that the user is subadmin of the named group and that the set is on its allowed list, answering 403 otherwise. A choice MUST bump the mapping generation so the group sees it on its next page load.

#### Scenario: A subadmin picks the house style of their municipality

@e2e exclude needs a subadmin account and a privilege grant on the shared CI instance; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminOfTheGroupChoosesAnAllowedSet and tests/vitest/admin-group-house-style.spec.js

- GIVEN group `gemeente-a` delegated with allowed sets `rijkshuisstijl` and `gemeente-a-huisstijl`
- AND a user who is subadmin of `gemeente-a` and in a group granted "House style of my groups"
- WHEN the subadmin opens Settings > Administration > Theming and chooses `gemeente-a-huisstijl`
- THEN a member of `gemeente-a` who opens the dashboard next MUST see the `gemeente-a-huisstijl` colours

#### Scenario: The endpoints need the grant

@e2e exclude API authorisation; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminEndpoints
- GIVEN the my-groups endpoints
- WHEN their authorisation is read
- THEN both MUST carry `#[AuthorizedAdminSetting(settings: GroupHouseStyle::class)]` and not `#[NoAdminRequired]`

#### Scenario: A subadmin cannot change another group

@e2e exclude API authorisation; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminEndpoints

- GIVEN a subadmin of `gemeente-a` only
- WHEN they call `POST /apps/thematiq/api/my-groups/gemeente-b/house-style`
- THEN the response MUST be 403
- AND the mapping of `gemeente-b` MUST be unchanged

#### Scenario: A set outside the allowed list is refused

@e2e exclude API authorisation; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testSubadminEndpoints and ::testRefusedChoicesChangeNothing

- GIVEN a subadmin of delegated group `gemeente-a` whose allowed list does not contain `amsterdam`
- WHEN they request `amsterdam` for `gemeente-a`
- THEN the response MUST be 403 and the mapping MUST be unchanged

### Requirement: Locking a group back keeps its current set

Turning delegation off MUST keep the entry's current token set and MUST remove the group from the subadmin's block.

#### Scenario: An administrator locks a group again

@e2e exclude needs a subadmin account on the shared CI instance; proven by tests/Unit/Service/DelegatedGroupThemingServiceTest.php::testLockingBackKeepsTheSetAndHidesTheGroup

- GIVEN delegated group `gemeente-a` currently on `gemeente-a-huisstijl`
- WHEN an administrator turns delegation off for it
- THEN members of `gemeente-a` MUST keep seeing `gemeente-a-huisstijl`
- AND the subadmin's "House style of my groups" block MUST no longer offer `gemeente-a`
