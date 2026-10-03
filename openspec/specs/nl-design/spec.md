---
status: done
---

# NL Design System Compliance — Delta Spec

## Purpose
Updates the existing nl-design shared spec to reflect expanded token set support and component-level tokens.

## Requirements

### Requirement: Supported Token Sets
The nldesign app MUST support all organization token sets available in the `nl-design-system/themes` repository. Token sets are auto-generated from upstream JSON files and synchronized nightly via GitHub Actions.

#### Scenario: Custom municipality theme
- GIVEN a municipality has a token set in the `nl-design-system/themes` repository
- WHEN their token set is selected in nldesign admin settings
- THEN all apps MUST adapt to that municipality's design tokens

#### Scenario: Incomplete token set renders correctly
- GIVEN a municipality's token set defines only part of the token vocabulary (e.g. Gemeente Amsterdam defines its primary colors but no `--nldesign-color-favorite`)
- WHEN their token set is selected
- THEN the defined tokens MUST use the municipality's values
- AND undefined tokens MUST fall back to sensible defaults from `defaults.css`

### Requirement: Design Token Usage
All visual styling MUST use CSS variables from design tokens, NOT hardcoded values. Component-level tokens from the NL Design System MUST be available via the `--nldesign-component-*` prefix.

#### Scenario: Component uses color
- GIVEN a UI component that needs styling
- WHEN colors are applied
- THEN it MUST use CSS variables (e.g., `var(--nldesign-color-primary)` or `var(--nldesign-component-button-primary-action-background-color)`)
- AND it MUST NOT use hardcoded hex/rgb values

@e2e exclude not met on development: nine hardcoded colours in css/systems/nldesign/theme.css and element-overrides.css, tracked in #769. A test now could only fail; the served-stylesheet test lands with that fix.

#### Scenario: Component uses component-level token
- GIVEN a button component that needs styling
- WHEN button-specific styling is applied
- THEN it MUST use component tokens (e.g., `var(--nldesign-component-button-border-radius)`)
- AND these tokens MUST resolve to organization-specific values when available
