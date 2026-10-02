@e2e exclude CSS-variable mapping and documentation spec. The scenarios describe a generated inventory, a generated table and a unit-test verdict; there is no browser surface.

## MODIFIED Requirements

### Requirement: Complete Nextcloud Variable Audit
The system MUST account for every CSS custom property in the generated Nextcloud variable inventory, covering the theming app's vocabulary, the properties Nextcloud's shipped code reads, and the Conduction `--cn-*` layer.

#### Scenario: All Nextcloud variables are accounted for
- GIVEN the inventory lists every custom property of the supported Nextcloud release
- WHEN `npm run test:inventory` runs
- THEN every entry MUST have a status of `mapped`, `settable` or `excluded`
- AND every `excluded` entry MUST carry a reason

#### Scenario: New Nextcloud variable is added upstream
- GIVEN Nextcloud adds a new CSS custom property in a future release
- WHEN the inventory is regenerated from that release
- THEN `npm run test:inventory` MUST fail until the new property has a status
- AND `mappings.md` MUST be regenerated to include it

### Requirement: Mappings Documentation
The system MUST include a `mappings.md` file, generated from the inventory, documenting the relationship between every inventory entry and thematiq.

#### Scenario: Developer looks up a Nextcloud variable
- GIVEN a developer wants to know how thematiq handles `--color-primary-element`
- WHEN they open `mappings.md`
- THEN they MUST find a row with the variable name, its class, its status, its `--nldesign-*` token if any, and the owning component

#### Scenario: Unmapped variable in documentation
- GIVEN an inventory entry has status `excluded`
- WHEN the developer looks it up in `mappings.md`
- THEN the row MUST show `excluded`
- AND the row MUST show the recorded reason

#### Scenario: The table cannot drift from the inventory
- GIVEN `mappings.md` was edited by hand
- WHEN the drift check runs
- THEN it MUST fail and MUST report the first differing row
