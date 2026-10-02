---
status: in-progress
---

# Nextcloud Variable Mapping Specification

## Purpose
Provides a complete, audited mapping between all Nextcloud CSS custom properties and `--nldesign-*` design tokens, with comprehensive documentation and a defaults layer that ensures all tokens always have a value.

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
Every `theme` entry in the Nextcloud variable inventory MUST be either mapped to a `--nldesign-*` token, settable through a `--nldesign-*` token, or excluded with a reason, and no `theme` entry outside the `icon` class MUST be excluded.

@e2e exclude The four scenarios below assert stylesheet content and the inventory status; the observable effect of a settable variable is proven in the browser by "A settable variable keeps Nextcloud's value until set" and "A set value reaches the page".

#### Scenario: Mapped variable
- GIVEN a Nextcloud CSS variable that has an appropriate NL Design equivalent
- WHEN the `overrides.css` is loaded
- THEN the variable MUST be overridden using `var(--nldesign-*)` syntax with `!important`

#### Scenario: Unmapped variable
- GIVEN a Nextcloud CSS variable that has no NL Design equivalent, such as `--color-mark`
- WHEN the theme stylesheets are loaded
- THEN the variable MUST be redeclared from an `--nldesign-nc-*` token that has no default value
- AND the redeclaration MUST fall back to the value Nextcloud declared for the current theme

#### Scenario: Intentionally unoverridden variable
- GIVEN a Nextcloud CSS variable that Nextcloud calculates per theme, such as `--color-text-selection`
- WHEN the theme stylesheets are loaded
- THEN the variable MUST be settable in the same way as an unmapped variable
- AND the reason it was once left alone MUST be kept as the token's note for theme authors

#### Scenario: Excluded variable
- GIVEN a Nextcloud CSS variable whose inventory class is `icon`
- WHEN the inventory guard runs
- THEN the variable MUST carry status `excluded` with a reason
- AND no thematiq stylesheet MUST assign it

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
@e2e exclude Proven by `node scripts/inventory/generate-mappings-doc.mjs --check`, which `npm run test:inventory` runs; a hand edit to a file is not something a browser can observe.

- GIVEN `mappings.md` was edited by hand
- WHEN the drift check runs
- THEN it MUST fail and MUST report the first differing row

### Requirement: CSS Load Order
The nldesign app MUST load CSS files in the following order to ensure correct cascading.

#### Scenario: CSS files load in correct order
- GIVEN the nldesign app is enabled
- WHEN the page loads
- THEN the design system's stylesheets MUST be injected first, in the order `design-systems.json` declares: fonts, defaults, utrecht-bridge, theme, overrides, element-overrides
- AND the token set file `tokens/{org}` MUST follow them, and the admin's `custom-overrides` MUST come after the token set
- AND later files MUST be able to override values from earlier files (the token set's `--nldesign-*` values win over `defaults.css`)

### Requirement: A settable variable keeps Nextcloud's value until set
A settable Nextcloud variable MUST resolve to exactly the value Nextcloud declares for the active theme whenever no token set and no admin override provides its `--nldesign-*` token.

@e2e exclude Proven in a real Chrome by `tests/css/check-theme-scopes-cascade.mjs` (`npm run test:theme-scopes`), which loads Nextcloud's per-theme values from the inventory and thematiq's real stylesheets; no live instance is needed.

#### Scenario: Unset under the dark theme
- GIVEN the active token set does not declare `--nldesign-nc-color-text-selection`
- AND the user has chosen Nextcloud's dark theme
- WHEN a page renders
- THEN the computed `--color-text-selection` MUST equal the value Nextcloud's dark theme declares

#### Scenario: Unset under high contrast
- GIVEN no token set declares `--nldesign-nc-color-loading-light`
- AND the user has chosen the high-contrast theme
- WHEN a page renders
- THEN the computed `--color-loading-light` MUST equal Nextcloud's high-contrast value

#### Scenario: An instance with no new tokens renders as before
- GIVEN the active set and the admin overrides declare none of the newly settable tokens
- WHEN the computed value of every `theme` entry is compared before and after this change, in light and dark
- THEN every value MUST be identical

### Requirement: A set value reaches the page
When a token set or an admin override provides a settable variable's `--nldesign-*` token, the Nextcloud variable MUST take that value everywhere Nextcloud does not redeclare it in a deeper element. An admin override of a settable variable MUST be stored as its `--nldesign-*` token.

@e2e exclude Proven in a real Chrome by `tests/css/check-theme-scopes-cascade.mjs` (`npm run test:theme-scopes`), which loads Nextcloud's per-theme values from the inventory and thematiq's real stylesheets; no live instance is needed.

#### Scenario: A set gives the search highlight a colour
- GIVEN a token set declares `--nldesign-nc-color-mark: #ffe08a`
- WHEN a search result page renders
- THEN the background of a `<mark>` element MUST be `#ffe08a`

#### Scenario: A set gives a light value only
- GIVEN a token set declares `--nldesign-nc-color-warning-hover` in its light file only
- AND the user has chosen the dark theme
- WHEN a page renders
- THEN the computed `--color-warning-hover` MUST equal Nextcloud's dark value

#### Scenario: A size applies in both schemes
- GIVEN a token set declares `--nldesign-nc-header-height: 56px` in its light file only
- AND Nextcloud gives `--header-height` the same value in light and dark
- WHEN the user switches between light and dark
- THEN the computed `--header-height` MUST be `56px` in both

#### Scenario: A set gives a light and a dark value
- GIVEN a token set declares `--nldesign-nc-color-box-shadow` in its light file and in its dark variant file
- WHEN the user switches between light and dark
- THEN the computed `--color-box-shadow` MUST follow the set's value for each scheme

### Requirement: Structural variables are flagged advanced
The 13 structural variables MUST be settable and MUST carry an `advanced` flag in the token registry.

@e2e exclude Registry metadata; the warning the flag produces is specified and browser-tested in `token-editor-at-scale`.

#### Scenario: Header height is advanced
- GIVEN the token registry is read
- WHEN the entry for `--header-height` is inspected
- THEN it MUST be editable
- AND it MUST carry `advanced: true`
