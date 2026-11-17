# token-editor-ui Specification (delta)

## MODIFIED Requirements

### Requirement: Functional Tab Groups
The token editor MUST organize theme tokens and the curated component tokens into four functional tabs, and MUST list every internal and Conduction token in a group named after the component or app that owns it.

Tabs and their primary tokens:

**Login page & Branding**: `--color-primary`, `--color-primary-text`, `--color-primary-hover`, `--color-primary-light`, `--color-primary-light-hover`, `--color-primary-element`, `--color-primary-element-text`, `--color-primary-element-hover`, `--color-primary-element-light`, `--color-primary-element-light-text`, `--color-primary-element-light-hover`

**Content area**: `--color-background-hover`, `--color-background-dark`, `--color-background-darker`, `--color-border`, `--color-border-dark`, `--color-border-maxcontrast`, `--color-scrollbar`, `--color-placeholder-light`, `--color-placeholder-dark`, `--color-main-background`, `--color-box-shadow`, `--color-mark`, `--color-text-selection`, `--color-background-selection`, border radius tokens, animation timing tokens

**Buttons & Status**: `--color-error`, `--color-error-hover`, `--color-warning`, `--color-warning-hover`, `--color-success`, `--color-success-hover`, `--color-info`, `--color-info-hover`, `--color-favorite`, status RGB values, semantic element/border variants, the assistant colours

**Typography**: `--color-main-text`, `--color-text-maxcontrast`, `--color-text-light`, `--color-text-lighter`, `--color-text-error`, `--color-text-success`, `--color-text-warning`, `--font-face`, `--font-weight-default`, `--font-weight-element`, `--font-weight-heading`

Component groups follow the tabs, one per owning component or app, each under a translated heading. An Advanced group holds every token the registry flags `advanced`, and those tokens leave their tab.

#### Scenario: Admin selects Login page tab
- GIVEN the token editor is open
- WHEN the admin clicks the "Login page & Branding" tab
- THEN all primary-color variables MUST be shown as editable fields
- AND no variables from other functional areas MUST appear in this tab

#### Scenario: Every editable token appears in exactly one tab
- GIVEN the full list of editable tokens is defined in the token registry
- WHEN all tabs and groups are rendered
- THEN every editable token MUST appear in exactly one tab or group
- AND no token MUST appear in more than one

#### Scenario: The date picker has its own group
- GIVEN the registry holds `--nldesign-nc-dp-hover-color` owned by the date picker
- WHEN the admin opens the "Date picker" group
- THEN the token MUST be listed there with `--dp-hover-color` as its row label


## ADDED Requirements

### Requirement: Font family, size and weight come from the house style
Each design system SHALL define the typography tokens a token set may override: the font family (`--nldesign-font-family`), the three weights (`--nldesign-font-weight-default`, `--nldesign-font-weight-element`, `--nldesign-font-weight-heading`) and the component font sizes (`--nldesign-component-heading-1-font-size` to `-6-`, `--nldesign-component-textbox-font-size`, `--nldesign-component-button-font-size`). A token set that declares one of them SHALL win over the design system default for that token. The design system SHALL map the family to Nextcloud's `--font-face` and the weights to `--font-weight-default`, `--font-weight-element` and `--font-weight-heading`. Nextcloud's body size `--default-font-size` SHALL stay unmapped, so body text keeps Nextcloud's 15px. The token editor's Typography tab SHALL offer the family and the three weights as overrides; the heading and paragraph sizes SHALL be edited in the Typography component group drawn on TqComponenten.

#### Scenario: A token set brings its own heading size
- GIVEN the Rijkshuisstijl token set declares `--nldesign-component-heading-1-font-size: 1.75rem`
- WHEN an administrator applies that set
- THEN a level 1 heading in the workspace renders at 1.75rem
- AND a set that declares no heading size renders it at the design system default

#### Scenario: A token set brings its own weights
- GIVEN a token set declares `--nldesign-font-weight-heading: 600`
- WHEN the set is active
- THEN Nextcloud's `--font-weight-heading` resolves to 600 on every page

#### Scenario: An administrator overrides the heading weight
- GIVEN the token editor is open on the Typography tab
- WHEN the administrator sets "Heading font weight" to 800 and saves
- THEN `--font-weight-heading` is 800 in `custom-overrides.css`
- AND the token set's own value is used again after a reset of that row

#### Scenario: Body text keeps Nextcloud's size
- GIVEN any shipped token set is active
- WHEN a user opens the Files app
- THEN body text renders at Nextcloud's `--default-font-size` of 15px
