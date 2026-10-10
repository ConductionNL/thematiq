# denhaag-component-tokens Specification

## Purpose
Den Haag's own component layer (case card, process steps, step marker, badge) ships as tokens, so a portal on the `denhaag` set draws those components in Den Haag's colours and any other set can restyle them. Built by the denhaag-component-tokens change.

## Requirements

### Requirement: Every Den Haag component property a portal reads has a value for every set
The app MUST give every `--denhaag-*` and `--nl-data-badge-*` property read by the pinned
`@gemeente-denhaag` component CSS for case card, process steps, step marker, action, file,
contact timeline, side navigation (both `side-navigation` and `sidenav`) and data badge a
value in `css/public-bridge.css`. Each value MUST end in a literal fallback. With the bridge
loaded before any shipped set, no such property MAY resolve to its initial value.

#### Scenario: A set with only the semantic layer paints a case card
- **GIVEN** the `denhaag` set, which declares `--nldesign-*` tokens and no case card token
- **WHEN** the bridge and the set are loaded and a `denhaag-case-card` is rendered
- **THEN** the card background, border, title and subtitle colours MUST resolve to the set's background, border, text and muted text colours

#### Scenario: Every property the package reads is covered
- **GIVEN** the pinned package CSS files
- **WHEN** the coverage test lists their `var(--denhaag-…)` and `var(--nl-data-badge-…)` names
- **THEN** every name MUST be declared in the generated section of `css/public-bridge.css`

### Requirement: Colour follows the token set, geometry follows Den Haag
A colour property MUST be mapped from the `--nldesign-*` semantic layer, through the matching
`--utrecht-*` role first where one exists. A geometry property MUST carry the value of
`@gemeente-denhaag/design-tokens-components` at the pinned version, except radii, which MUST
read the set's `--nldesign-border-radius` first.

#### Scenario: The primary colour reaches the current step
- **GIVEN** the `tilburg` set, which declares a primary colour and no Den Haag property
- **WHEN** a process step marked current renders
- **THEN** its marker background MUST resolve to the set's primary colour and its number to the set's primary text colour

### Requirement: The Den Haag mapping is data, and the bridge is generated from it
The mapping MUST live in `scripts/mapping/denhaag-component-tokens.json`, and a generator
MUST write it into a delimited section of `css/public-bridge.css` whose header names the
package versions and the mapping file's SHA-256. A check mode MUST fail when the committed
section differs from what the mapping produces.

#### Scenario: A hand edit in the generated section is caught
- **GIVEN** a property value edited by hand inside the generated section
- **WHEN** `npm run test:denhaag-bridge` runs
- **THEN** it MUST fail and name the property

### Requirement: A token set's own Den Haag value wins over the bridge
A shipped or uploaded set that declares a `--denhaag-*` or `--nl-data-badge-*` property MUST
keep its own value when the bridge is loaded before it.

#### Scenario: Rotterdam keeps its process steps
- **GIVEN** the `rotterdam` set, which declares its own `--denhaag-process-steps-*` values
- **WHEN** the bridge and the set are loaded in that order
- **THEN** every process steps property MUST resolve to the value in `css/tokens/rotterdam.css`

### Requirement: The bridge documents the real coverage
The header of `css/public-bridge.css` and `docs/features/public-portals-as-consumers.md` MUST
state the measured coverage (how many listed sets declare the `--utrecht-*` role layer, how
many declare only `--nldesign-*`, and how many declare Den Haag component tokens), and MUST
name the portaliq change that links the bridge. A test MUST recompute the three numbers from
`token-sets.json` and `css/tokens/` and fail when the documented numbers differ.

#### Scenario: A new set changes the numbers
- **GIVEN** a new set added with only `--nldesign-*` tokens
- **WHEN** the coverage test runs without the documentation updated
- **THEN** it MUST fail and print the measured numbers
