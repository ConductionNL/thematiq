# Spec delta: token sets (rows-without-a-requirement)

The capability row `cat-community-sets` names a requirement this spec did not state.

## ADDED Requirements

### Requirement: Community brand sets sit beside the government ones

The shipped catalogue MUST include brand sets for organisations that are not a municipality, a
province or VNG, maintained in the same way and selectable in the same list. Each MUST ship its token
file.

#### Scenario: An open-source community picks its own brand
@e2e exclude Catalogue content; covered by PHPUnit (CapabilityRowsTest::testTheCatalogueHoldsCommunitySetsBesideTheGovernmentOnes)
- GIVEN an administrator opens the token set list
- WHEN they look for `OpenCatalogi` or `Demodam`
- THEN both MUST be listed and selectable
