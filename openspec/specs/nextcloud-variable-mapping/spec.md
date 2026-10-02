---
status: done
---

# Nextcloud Variable Mapping Specification

## Purpose
Provides a complete, audited mapping between all Nextcloud CSS custom properties and `--nldesign-*` design tokens, with comprehensive documentation and a defaults layer that ensures all tokens always have a value.

## Requirements

### Requirement: Complete Nextcloud Variable Audit
The system MUST include a mapping for every CSS custom property defined by Nextcloud's theming system (DefaultTheme.php, CommonThemeTrait.php, and core SCSS files).

#### Scenario: All Nextcloud variables are accounted for
- GIVEN the Nextcloud server defines CSS custom properties in its theming system
- WHEN the nldesign app is installed
- THEN the `overrides.css` file MUST contain an entry for every Nextcloud CSS variable
- AND each entry MUST either map to a `--nldesign-*` token or be commented out with a reason

#### Scenario: New Nextcloud variable is added upstream
- GIVEN Nextcloud adds a new CSS custom property in a future release
- WHEN the nldesign maintainers review the change
- THEN the `mappings.md` documentation MUST be updated to include the new variable
- AND the `overrides.css` MUST be updated with a mapping or commented entry

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
- WHEN the CSS is loaded in order (defaults, then the token set)
- THEN `--nldesign-color-favorite` MUST have the default value from `defaults.css`

#### Scenario: Token is overridden by organization
- GIVEN an organization token set that defines `--nldesign-color-primary` (e.g. Gemeente Amsterdam: `#004699`)
- WHEN the CSS is loaded in order
- THEN `--nldesign-color-primary` MUST have the token set's value (`#004699`), overriding the default

#### Scenario: New nldesign token is added
- GIVEN a developer adds a new `--nldesign-*` token
- WHEN they update the system
- THEN the new token MUST be added to `defaults.css` with a default value
- AND existing organization token sets MUST continue to work without modification

### Requirement: Mappings Documentation
The system MUST include a `mappings.md` file documenting the complete relationship between Nextcloud variables and NL Design tokens.

#### Scenario: Developer looks up a Nextcloud variable
- GIVEN a developer wants to know which NL Design token maps to `--color-primary-element`
- WHEN they open `mappings.md`
- THEN they MUST find a table row with the Nextcloud variable name, its `--nldesign-*` mapping, the category, and any notes

#### Scenario: Unmapped variable in documentation
- GIVEN a Nextcloud variable has no NL Design mapping
- WHEN the developer looks it up in `mappings.md`
- THEN the table row MUST show "unmapped" in the mapping column
- AND the notes column MUST explain why

### Requirement: CSS Load Order
The nldesign app MUST load CSS files in the following order to ensure correct cascading.

#### Scenario: CSS files load in correct order
- GIVEN the nldesign app is enabled
- WHEN the page loads
- THEN the design system's stylesheets MUST be injected first, in the order `design-systems.json` declares: fonts, defaults, utrecht-bridge, theme, overrides, element-overrides
- AND the token set file `tokens/{org}` MUST follow them, and the admin's `custom-overrides` MUST come after the token set
- AND later files MUST be able to override values from earlier files (the token set's `--nldesign-*` values win over `defaults.css`)
