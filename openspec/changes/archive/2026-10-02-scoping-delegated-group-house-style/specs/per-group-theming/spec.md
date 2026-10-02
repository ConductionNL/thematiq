# Spec delta: per-group theming (per-group-theming)

Chosen groups can pick their own house style from a list the administrator allows; all other groups stay locked.

## ADDED Requirements

### Requirement: An administrator delegates a group mapping

A group mapping entry MUST be able to carry `delegated` and `allowedTokenSets`. An administrator MUST be able to mark an entry as delegated on Settings > Administration > Theming and choose its allowed token sets. The allowed list MUST NOT be empty, MUST contain the entry's current set and MUST name only available sets. Entries stored before this change MUST read as not delegated. Delegation MUST NOT change the resolution order.

#### Scenario: An administrator delegates a group

- GIVEN an administrator on Settings > Administration > Theming with a mapping for group `gemeente-a` to `rijkshuisstijl`
- WHEN they mark it as delegated with allowed sets `rijkshuisstijl` and `gemeente-a-huisstijl` and save
- THEN the mapping MUST show the row as delegated with both allowed sets
- AND members of `gemeente-a` MUST still see `rijkshuisstijl` until a choice is made

#### Scenario: Undelegated groups stay locked

- GIVEN a mapping for group `concern` that is not delegated
- WHEN a subadmin of `concern` opens their personal settings
- THEN they MUST NOT see a house style choice for `concern`

### Requirement: A group subadmin chooses the house style of a delegated group

A subadmin of a delegated group MUST be able to choose that group's token set from its allowed list in the personal settings section "House style of my groups". The section MUST appear only for users who are subadmin of at least one delegated group. The endpoints MUST be `#[NoAdminRequired]` and MUST check, on every call, that the user is subadmin of the named group and that the set is on its allowed list, answering 403 otherwise. A choice MUST bump the mapping generation so the group sees it on its next page load.

#### Scenario: A subadmin picks the house style of their municipality

- GIVEN group `gemeente-a` delegated with allowed sets `rijkshuisstijl` and `gemeente-a-huisstijl`
- AND a user who is subadmin of `gemeente-a`
- WHEN the subadmin opens Settings > Personal > House style of my groups and chooses `gemeente-a-huisstijl`
- THEN a member of `gemeente-a` who opens the dashboard next MUST see the `gemeente-a-huisstijl` colours

#### Scenario: A subadmin cannot change another group

- GIVEN a subadmin of `gemeente-a` only
- WHEN they call `POST /apps/thematiq/api/my-groups/gemeente-b/house-style`
- THEN the response MUST be 403
- AND the mapping of `gemeente-b` MUST be unchanged

#### Scenario: A set outside the allowed list is refused

- GIVEN a subadmin of delegated group `gemeente-a` whose allowed list does not contain `amsterdam`
- WHEN they request `amsterdam` for `gemeente-a`
- THEN the response MUST be 403 and the mapping MUST be unchanged

### Requirement: Delegated choices are audited

Every delegated choice MUST write a `token_set_changed` audit entry with the subadmin's user id as actor, the old and new set, and the group in its context.

#### Scenario: The brand owner sees who changed a group's look

- GIVEN a subadmin of `gemeente-a` changed the group's set
- WHEN an administrator opens the theming audit log
- THEN the entry MUST show the subadmin as user, the old and new set, and the group `gemeente-a`

### Requirement: Locking a group back keeps its current set

Turning delegation off MUST keep the entry's current token set and MUST remove the group from the subadmin's section.

#### Scenario: An administrator locks a group again

- GIVEN delegated group `gemeente-a` currently on `gemeente-a-huisstijl`
- WHEN an administrator turns delegation off for it
- THEN members of `gemeente-a` MUST keep seeing `gemeente-a-huisstijl`
- AND the subadmin's personal section MUST no longer offer `gemeente-a`
