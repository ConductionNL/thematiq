---
status: in-progress
---

# Component Tokens Specification

## Purpose
Introduces component-level NL Design System tokens using the `--nldesign-component-*` prefix, with a temporary bridge file that maps the current `--utrecht-*` component tokens to the nldesign namespace.

@e2e exclude CSS-variable and bridge-file spec — all scenarios are pure CSS cascade / file-structure assertions with no testable UI surface beyond the admin theming page already covered by admin-settings tests.

## Requirements

### Requirement: NLDesign Component Token Prefix
All component-level tokens MUST use the `--nldesign-component-*` prefix for consistency with the rest of the nldesign token system.

#### Scenario: Button component token
- GIVEN the NL Design System defines `--utrecht-button-primary-action-background-color`
- WHEN it is used in the nldesign system
- THEN it MUST be available as `--nldesign-component-button-primary-action-background-color`

#### Scenario: Heading component token
- GIVEN the NL Design System defines `--utrecht-heading-1-font-size`
- WHEN it is used in the nldesign system
- THEN it MUST be available as `--nldesign-component-heading-1-font-size`

### Requirement: Utrecht Bridge File
The system MUST include a `utrecht-bridge.css` file that maps `--utrecht-*` component tokens to `--nldesign-component-*` tokens.

#### Scenario: Bridge maps Utrecht tokens to nldesign
- GIVEN an organization token set defines `--utrecht-button-border-radius: 4px`
- WHEN the CSS is loaded with the bridge file
- THEN `--nldesign-component-button-border-radius` MUST resolve to `4px`

#### Scenario: Bridge falls back to defaults
- GIVEN an organization token set does NOT define `--utrecht-button-border-radius`
- WHEN the CSS is loaded with the bridge file
- THEN `--nldesign-component-button-border-radius` MUST fall back to the value from `defaults.css`

#### Scenario: Bridge file is clearly marked as temporary
- GIVEN a developer opens `utrecht-bridge.css`
- WHEN they read the file header
- THEN the header MUST contain a comment explaining that this file is temporary
- AND it MUST state that the file can be removed when the NL Design System adopts a vendor-neutral prefix
- AND it MUST reference the NL Design System themes repository

### Requirement: Component Token Categories
The system MUST support component tokens for the following NL Design System component types.

#### Scenario: Button tokens
- GIVEN the NL Design System defines button component tokens
- WHEN the nldesign app processes them
- THEN it MUST support tokens for: background-color, color, border-radius, border-width, border-color, font-family, font-size, padding, and state variants (hover, active, disabled, focus, primary-action, secondary-action)

#### Scenario: Form input tokens
- GIVEN the NL Design System defines form input component tokens
- WHEN the nldesign app processes them
- THEN it MUST support tokens for: textbox, form-field, form-select, and form-fieldset components
- AND each MUST support state variants (focus, hover, disabled, invalid)

#### Scenario: Typography tokens
- GIVEN the NL Design System defines heading and paragraph component tokens
- WHEN the nldesign app processes them
- THEN it MUST support tokens for heading levels 1-6 (font-size, font-weight, line-height, color)
- AND it MUST support paragraph tokens (font-size, line-height, color)

#### Scenario: Additional component tokens
- GIVEN the NL Design System defines tokens for link, table, badge, separator, ordered-list, and unordered-list
- WHEN the nldesign app processes them
- THEN these MUST be available as `--nldesign-component-{component}-*` tokens

### Requirement: Component Token Defaults
All component tokens MUST have sensible default values in `defaults.css`.

#### Scenario: Component token defaults reference brand tokens
- GIVEN `defaults.css` defines `--nldesign-component-button-primary-background-color`
- WHEN no organization token overrides it
- THEN it MUST default to `var(--nldesign-color-primary)` (referencing the brand token)

#### Scenario: Component token defaults are self-consistent
- GIVEN `defaults.css` defines all component token defaults
- WHEN loaded without any organization token file
- THEN all components MUST render with visually consistent styling using the default brand tokens
