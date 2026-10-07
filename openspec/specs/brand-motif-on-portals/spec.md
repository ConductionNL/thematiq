# brand-motif-on-portals Specification

## Purpose
A portal site built on the shared component library reads its colours, logos and brand motif from Thematiq. This capability makes the public bridge name every role that site reads, so the active token set reaches the portal as it reaches Nextcloud: the brand stripe under the header and over the footer, the accent colours, the website logo and the footer logo. A set that declares none of these leaves the portal on its defaults.

## Requirements

### Requirement: The bridge names every role the portal site reads
`css/public-bridge.css` MUST declare every `--utrecht-*`, `--tilburg-*` and `--conduction-*` role
that the portal site reads and that an `example-*` set declares for itself: colours from the
`--nldesign-*` layer with a fallback, logos from `--nldesign-logo-url`, geometry as neutral
values. A set that declares a role itself MUST keep its own value, because it loads after the
bridge.

#### Scenario: A school set paints the whole site
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js resolves the bridge and each set the way a page does
- GIVEN a portal on `wilgenboom`, `vaartveld`, `esdoornveen`, `warmtepompacademie` or `zuiddrecht`
- WHEN the bridge and the set are resolved
- THEN every role in `tests/vitest/fixtures/portal-site-roles.json` MUST be declared
- AND the primary button and the header account button MUST resolve to the set's primary colour
- AND the header logo MUST be the set's own logo, 50px high

#### Scenario: A set with a role layer keeps it
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN a portal on `example-basisschool`
- WHEN the bridge and the set are resolved
- THEN the primary button MUST keep the set's own value

### Requirement: The brand stripe reaches the portal
The bridge MUST hand `--nldesign-brand-stripe-color-1..3`, `-ratio-1..3`, `-height` and `-image`
to the portal as `--cn-brand-stripe-*`, and `--cn-brand-stripe-image-inverse` from
`--nldesign-brand-stripe-image-inverse`, else from `-image`. None of them MAY carry a fallback,
so a set that declares no stripe draws none. `css/brand-stripe.css` MUST hand the two image names
to the component library on Nextcloud's pages, also without a fallback.

#### Scenario: Zuiddrecht keeps its three bands
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js and workplaceLayout.spec.js
- GIVEN a portal on `zuiddrecht`
- WHEN the stripe tokens are resolved
- THEN the colours MUST be #CC0000, #3669A5 and #CC0000 in the ratio 6 : 3 : 1, 5px high
- AND no stripe image MUST resolve

#### Scenario: A school motif and its inverse
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN a portal on `wilgenboom`
- WHEN the stripe tokens are resolved
- THEN `--cn-brand-stripe-image` MUST be the twigs as an SVG data URI
- AND `--cn-brand-stripe-image-inverse` MUST be the light green twigs

#### Scenario: A set without a stripe draws none
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN a portal on `vng`
- WHEN the stripe tokens are resolved
- THEN `--cn-brand-stripe-height` and `--cn-brand-stripe-image` MUST be the guaranteed-invalid value

### Requirement: The accent is vocabulary
A set MAY name its accent in `--nldesign-color-accent`, `--nldesign-color-accent-light` and
`--nldesign-color-accent-text`. The bridge MUST read them into `--thematiq-accent-color`,
`--thematiq-accent-light-color` and `--thematiq-accent-text-color`, each falling back to the
primary's matching step, and the set's navigation badge tokens into
`--thematiq-badge-background-color` and `--thematiq-badge-color`. The accent MUST NOT be the
colour of a button or a link.

#### Scenario: The school accents
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN a school set
- WHEN its accent tokens are read
- THEN they MUST equal the set's palette entries `--<set>-color-accent`, `-accent-light` and `-accent-text`
- AND `--thematiq-accent-color` MUST resolve to the accent, not the primary

#### Scenario: A set without an accent
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN a portal on `vng`
- WHEN `--thematiq-accent-color` is resolved
- THEN it MUST be the set's primary colour

### Requirement: The website logo and footer
A set MAY name `--nldesign-website-logo-width` and `-height` (the logo box in the site header and
footer) and `--nldesign-website-logo-text-size` (0 when the logo carries the name). The bridge
MUST map them onto the logo roles and `--thematiq-logo-text-font-size`, and MUST show
`--nldesign-logo-inverse-url` on the footer when the portal names it, else the set's logo.

#### Scenario: The school logo carries the name
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN a school set
- WHEN the logo roles are resolved
- THEN the header logo MUST be 50px high and `--thematiq-logo-text-font-size` MUST be 0
