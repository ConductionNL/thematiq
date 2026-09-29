# Spec delta: token reference (token-reference)

A new capability. Every house style has a generated, current reference of its tokens.

## ADDED Requirements

### Requirement: The docs site has a reference page per shipped set

The documentation site MUST carry one generated page per shipped token set, listing every token the set declares or inherits with its value, a swatch and the value as text for colours, what the token paints, and whether the value comes from the set or from the defaults layer. The pages MUST be generated from the token files, and the test suite MUST fail when a committed page differs from what the generator produces.

#### Scenario: A supplier looks up the Amsterdam tokens

- GIVEN the documentation site built from `development`
- WHEN a supplier opens the reference page for `amsterdam`
- THEN they MUST see `--nldesign-color-primary` with its value and what it paints
- AND tokens the set does not declare MUST be marked as coming from the defaults layer

#### Scenario: A token change without a regenerated page fails the build

- GIVEN a developer changes a value in `css/tokens/amsterdam.css`
- WHEN the unit suite runs without regenerating the reference
- THEN the suite MUST fail and name the command that regenerates the pages

### Requirement: Signed-in users read the reference of any available set

`GET /api/token-sets/{id}/reference?format=md|html` MUST return the reference of any available token set, custom sets included, to any signed-in user, and MUST NOT be reachable without a session. An unknown id MUST answer 404. Settings > Administration > Theming MUST link to the reference of each set and offer it as a download.

#### Scenario: A supplier's developer opens the reference of a custom set

- GIVEN a custom set `custom-gemeente-x` and a signed-in developer account that is not an administrator
- WHEN the developer requests `GET /apps/thematiq/api/token-sets/custom-gemeente-x/reference?format=html`
- THEN the response MUST be the reference page of that set

#### Scenario: The reference is not public

- GIVEN no session
- WHEN a request is made to `GET /apps/thematiq/api/token-sets/amsterdam/reference`
- THEN the response MUST NOT contain the reference

#### Scenario: An administrator downloads a reference for a supplier

- GIVEN an administrator on Settings > Administration > Theming
- WHEN they choose "download reference" next to `custom-gemeente-x`
- THEN the browser MUST download a Markdown file listing that set's tokens

### Requirement: The reference does not rely on colour alone

Every colour row MUST show the value as text next to its swatch, and the HTML reference MUST meet WCAG 2.1 AA.

#### Scenario: A colour-blind reader reads the palette

- GIVEN the HTML reference of any set
- WHEN a reader who cannot tell the swatches apart reads the colour rows
- THEN each row MUST show the colour value as text
