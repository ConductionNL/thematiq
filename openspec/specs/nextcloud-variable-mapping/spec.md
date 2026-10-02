---
status: in-progress
---

# Nextcloud Variable Mapping Specification

## Purpose
Provides a complete, audited mapping between all Nextcloud CSS custom properties and `--nldesign-*` design tokens, with comprehensive documentation and a defaults layer that ensures all tokens always have a value.

@e2e exclude CSS-variable mapping / documentation spec — all scenarios describe CSS file content, variable mapping tables, and cascade ordering; no testable UI surface in the admin settings page.

## Requirements

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

### Requirement: Overrides CSS Structure
The `overrides.css` file MUST contain ALL Nextcloud CSS variables organized by category, with each variable either mapped to a `--nldesign-*` token or commented out with an explanation.

#### Scenario: Mapped variable
- GIVEN a Nextcloud CSS variable that has an appropriate NL Design equivalent
- WHEN the `overrides.css` is loaded
- THEN the variable MUST be overridden using `var(--nldesign-*)` syntax with `!important`

#### Scenario: Unmapped variable
- GIVEN a Nextcloud CSS variable that has no appropriate NL Design equivalent
- WHEN the `overrides.css` is loaded
- THEN the variable MUST be present as a CSS comment
- AND the comment MUST explain why no mapping exists

#### Scenario: Intentionally unoverridden variable
- GIVEN a Nextcloud CSS variable that is intentionally left to Nextcloud's control (e.g., `--color-main-background`)
- WHEN the `overrides.css` is loaded
- THEN the variable MUST be present as a CSS comment
- AND the comment MUST state "intentionally not overridden" with the reason

### Requirement: Defaults CSS Layer
The system MUST include a `defaults.css` file that defines sensible default values for ALL `--nldesign-*` tokens.

#### Scenario: Token has no organization-specific override
- GIVEN an organization token set that does not define `--nldesign-color-favorite`
- WHEN the CSS is loaded in order (defaults → tokens → theme → overrides)
- THEN `--nldesign-color-favorite` MUST have the default value from `defaults.css`

#### Scenario: Token is overridden by organization
- GIVEN an organization token set that defines `--nldesign-color-primary: #ec0000`
- WHEN the CSS is loaded in order
- THEN `--nldesign-color-primary` MUST have the value `#ec0000` (from the token set, overriding the default)

#### Scenario: New nldesign token is added
- GIVEN a developer adds a new `--nldesign-*` token
- WHEN they update the system
- THEN the new token MUST be added to `defaults.css` with a default value
- AND existing organization token sets MUST continue to work without modification

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

### Requirement: CSS Load Order
The nldesign app MUST load CSS files in the following order to ensure correct cascading.

#### Scenario: CSS files load in correct order
- GIVEN the nldesign app is enabled
- WHEN the page loads
- THEN CSS files MUST be injected in this order: fonts → defaults → tokens/{org} → utrecht-bridge → theme → overrides
- AND later files MUST be able to override values from earlier files
