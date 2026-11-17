# theme-gallery Specification (delta)

## MODIFIED Requirements

### Requirement: An administrator browses the gallery

With the gallery on, the Gallery block MUST list every valid index entry with its name, organisation, swatches, licence, source link and contrast result. Entries without an SPDX licence or with a malformed field MUST NOT be listed. When the index cannot be reached, the block MUST say so and point to installing by upload.

#### Scenario: An administrator finds their province

@e2e exclude needs a reachable index with a real entry, which the shipped index does not have yet; proven by tests/Unit/Service/ThemeGalleryServiceTest.php::testInvalidEntriesAreNotListed and tests/vitest/admin-gallery.spec.js 'turns the gallery on and lists the entries with swatches, licence and contrast'

- GIVEN the gallery is on and the index lists `provincie-utrecht`
- WHEN an administrator opens the Gallery block
- THEN they MUST see "Provincie Utrecht" with its swatches, licence and contrast result

#### Scenario: An administrator finds their water board

@e2e exclude needs a reachable index with a real entry, which the shipped index does not have yet; proven by the same tests as the province scenario, since the gallery lists every valid entry whatever kind of organisation it belongs to

- GIVEN the gallery is on and the index lists `waterschap-rivierenland`
- WHEN an administrator opens the Gallery block
- THEN they MUST see "Waterschap Rivierenland" with its swatches, licence and contrast result
- AND installing it MUST offer it in the Design token set dropdown like any other set

#### Scenario: The index is unreachable

@e2e exclude needs a host that does not answer; proven by tests/Unit/Service/ThemeGalleryServiceTest.php::testAnUnreachableIndexIsReported and tests/vitest/admin-gallery.spec.js 'says so when the gallery cannot be reached'

- GIVEN the gallery is on and the index host does not answer within 10 seconds
- WHEN an administrator opens the Gallery block
- THEN the block MUST state that the gallery could not be reached
- AND it MUST offer the custom token set upload instead
