---
status: done
---

# VNG Token Set Specification

## Purpose
Define requirements for adding VNG (Vereniging Nederlandse Gemeenten) as a selectable design token set in the nldesign Nextcloud app.

@e2e exclude CSS token-set / colour-value spec — scenarios describe CSS custom property values, colour palette, typography tokens, and border-radius values; the manifest entry and dropdown visibility are covered by admin-settings tests. VNG tokens are manually converted from the tilburg-woo-ui project since they are not available in the upstream nl-design-system/themes repository.

## Requirements

### Requirement: VNG Token CSS File
The system MUST provide a `css/tokens/vng.css` file containing VNG design tokens in `:root` scope with `--nldesign-*` semantic tokens and `--vng-*` organization palette tokens.

#### Scenario: VNG token file exists and loads
- GIVEN the nldesign app is installed
- WHEN the VNG token set is selected in admin settings
- THEN `css/tokens/vng.css` is loaded into the page
- AND all `--nldesign-*` semantic tokens are defined with VNG-specific values

#### Scenario: VNG palette tokens are preserved
- GIVEN the VNG token file is loaded
- WHEN a developer inspects the CSS custom properties
- THEN `--vng-color-*` tokens exist for the full VNG color palette (blue, red, green, orange, pink, gray shades)
- AND these palette tokens use resolved hex values (not var() references to tilburg tokens)

### Requirement: VNG Semantic Color Mapping
The VNG token file MUST map the VNG color palette to all `--nldesign-*` semantic color tokens.

#### Scenario: Primary colors use VNG blue
- GIVEN the VNG token set is active
- WHEN the primary color tokens are evaluated
- THEN `--nldesign-color-primary` SHALL be `#003865` (VNG dark blue)
- AND `--nldesign-color-primary-hover` SHALL be `#026596` (VNG medium blue)
- AND `--nldesign-color-primary-text` SHALL be `#ffffff`
- AND `--nldesign-color-primary-light` SHALL be a light blue derived from the VNG palette

#### Scenario: Status colors use VNG palette
- GIVEN the VNG token set is active
- WHEN status color tokens are evaluated
- THEN `--nldesign-color-error` SHALL use VNG red (#bf1a12)
- AND `--nldesign-color-success` SHALL use VNG green (#01745a)
- AND `--nldesign-color-warning` SHALL use VNG orange (#d45f01)

#### Scenario: Text colors use VNG values
- GIVEN the VNG token set is active
- WHEN text color tokens are evaluated
- THEN `--nldesign-color-text` SHALL be `#333333` (VNG black-txt)
- AND `--nldesign-color-text-muted` SHALL be a gray from the VNG palette

### Requirement: VNG Typography Tokens
The VNG token file MUST define typography tokens based on VNG's Avenir font family.

#### Scenario: Font family is set to Avenir
- GIVEN the VNG token set is active
- WHEN typography tokens are evaluated
- THEN `--nldesign-typography-font-family` SHALL include 'Avenir' as the primary font
- AND a sans-serif fallback SHALL be specified

#### Scenario: Font sizes follow VNG scale
- GIVEN the VNG token set is active
- WHEN font size tokens are evaluated
- THEN heading and body font sizes SHALL be derived from the VNG typography scale (sm: 14px, md: 16px, lg: 20px, xl: 24px, etc.)

### Requirement: VNG Spacing and Border Tokens
The VNG token file MUST define spacing and border tokens derived from the VNG design system.

#### Scenario: Spacing tokens are defined
- GIVEN the VNG token set is active
- WHEN spacing tokens are evaluated
- THEN `--nldesign-spacing-*` tokens SHALL map to VNG spacing values

#### Scenario: Border radius uses VNG values
- GIVEN the VNG token set is active
- WHEN border tokens are evaluated
- THEN `--nldesign-border-radius` SHALL use VNG border-radius-md (8px)

### Requirement: VNG Header and Background Tokens
The VNG token file MUST define header and background tokens using VNG colors.

#### Scenario: Header uses VNG dark blue
- GIVEN the VNG token set is active
- WHEN header tokens are evaluated
- THEN `--nldesign-color-header-background` SHALL be `#003865` (VNG dark blue)
- AND `--nldesign-color-header-text` SHALL provide sufficient contrast (WCAG AA)

### Requirement: Token Set Manifest Entry
The `token-sets.json` manifest MUST include an entry for VNG.

#### Scenario: VNG appears in manifest
- GIVEN `token-sets.json` is read by TokenSetService
- WHEN the available token sets are listed
- THEN an entry with `"id": "vng"`, `"name": "VNG Vereniging Nederlandse Gemeenten"`, and a description SHALL exist

#### Scenario: VNG appears in admin dropdown
- GIVEN the VNG set is selectable (see the token-sets spec, "Only Fully Functional Brands Are Selectable": VNG is not on `SELECTABLE_SHIPPED_SETS` today, so it is offered only while it is active, mapped to a group, or once the vocabulary audit admits it)
- AND the admin opens nldesign settings
- WHEN the token set dropdown is rendered
- THEN "VNG Vereniging Nederlandse Gemeenten" SHALL appear as a selectable option

### Requirement: No Utrecht Component Token Duplication
The VNG token file MUST NOT include `--utrecht-*` component tokens, as these are handled by the `utrecht-bridge.css` layer.

#### Scenario: Utrecht tokens are absent from VNG file
- GIVEN the VNG token file is loaded
- WHEN the CSS custom properties are inspected
- THEN no `--utrecht-*` prefixed tokens SHALL be present
- AND component styling SHALL flow through the existing `--nldesign-component-*` → `--utrecht-*` bridge
