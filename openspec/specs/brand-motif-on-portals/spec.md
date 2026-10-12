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

### Requirement: A set may draw an info melding without a line

The public bridge MUST carry `--nldesign-website-alert-info-border-width` into
`--utrecht-alert-info-border-width`. When a set does not name it, the info border width MUST equal
the width every melding has.

#### Scenario: A school set
@e2e exclude Token resolution checked in vitest: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN the wilgenboom set, which names `--nldesign-website-alert-info-border-width: 0`
- WHEN a portal renders an info melding
- THEN it has no line, and a warning melding keeps its line

#### Scenario: A set names nothing
@e2e exclude Token resolution checked in vitest: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN the zuiddrecht set
- WHEN a portal renders an info melding
- THEN its line is 1px, as every melding on that set

### Requirement: The school sets name what their boards draw

The wilgenboom, vaartveld and esdoornveen sets MUST name the attention strip's background and
border from their boards, and every school set a 1px notice line. The esdoornveen and academy sets
MUST name a semibold hero search label, and the esdoornveen set the hero photo's clip path.

#### Scenario: Wilgenboom's "Let op"
@e2e exclude Token resolution in vitest: tests/vitest/publicBridgeRoleLayer.spec.js; measured on :8092
- GIVEN the wilgenboom set
- WHEN the home page renders its notice strip
- THEN it is `#FDF3D7` with a 1px `#ECD391` line under it

### Requirement: The school sets name the type scale of their boards

Each school set MUST name its boards' page title size (44px for wilgenboom and esdoornveen, 48px for
vaartveld and warmtepompacademie) as the website's level 1 heading and page title, 17px as the
content text and 21px as the lead. The warmtepompacademie set MUST name capitals for the month of a
date tile; the bridge MUST pass it on without a fallback.

#### Scenario: De Wilgenboom's content page
@e2e exclude Token resolution in vitest: tests/vitest/publicBridgeRoleLayer.spec.js; measured on :8092 in the PR
- GIVEN the wilgenboom set on a public portal
- WHEN "Uw kind afwezig melden" renders
- THEN its title is 44px and its paragraphs 17px

#### Scenario: The academy's course days
@e2e exclude Token resolution in vitest: tests/vitest/publicBridgeRoleLayer.spec.js
- GIVEN the warmtepompacademie set
- WHEN the bridge resolves `--thematiq-date-month-text-transform`
- THEN it is `uppercase`, and nothing for every other set

### Requirement: The site's page title, lead, notice text and surface are vocabulary
A set MAY name, for its portal only, `--nldesign-website-page-title-size` and
`--nldesign-website-page-title-line-height` (a content page's title, drawn larger than the
heading 1 role), `--nldesign-website-lead-font-size` (the lead paragraph),
`--nldesign-website-notice-color` (the text on a plain notice) and `--nldesign-color-surface`
(the site's grey band and a boxed table's header row). `css/public-bridge.css` MUST read each:
the lead size into `--utrecht-paragraph-lead-font-size` with 20px as its fallback, the notice
text into `--utrecht-alert-color`, the page title into `--thematiq-page-title-font-size` and
`--thematiq-page-title-line-height`, and the surface into `--thematiq-surface-color`, the last
four without a fallback so a consumer's own fallback applies where a set names none. No
stylesheet an instance page loads MUST read any of them.

#### Scenario: Zuiddrecht draws its site
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js resolves the bridge and the set the way a page does
- GIVEN a portal on `zuiddrecht`
- WHEN the bridge and the set are resolved
- THEN the page title role MUST be 2.75rem on a 1.15 line, the lead 18px, the notice text
  #1A1A1A and the surface #F4F6F9

#### Scenario: A set that names none keeps every value
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js (the control)
- GIVEN a portal on a school set or on `vng`
- WHEN the same roles are resolved
- THEN the lead MUST be 20px
- AND the page title roles, the notice text and the surface MUST resolve to nothing

#### Scenario: Every pair on the new grounds reaches AA
@e2e exclude Computed from the files: tests/vitest/zuiddrechtTokenSet.spec.js measures the pairs in the light and in both dark scopes
- GIVEN the `zuiddrecht` tokens in the light scheme and in the generated dark one
- WHEN the notice text is measured on the notice ground, and the text, muted text, link and
  link hover on the surface
- THEN each pair MUST reach 4.5:1

### Requirement: The attention strip has names of its own
A set MAY name, for its portal only, `--nldesign-website-attention-background-color`,
`--nldesign-website-attention-border-color` and `--nldesign-website-attention-color` for an
attention strip ("Let op"), apart from the plain notice `--nldesign-website-notice-*` names.
`css/public-bridge.css` MUST read each into `--thematiq-attention-background-color`,
`--thematiq-attention-border-color` and `--thematiq-attention-color`, without a fallback.

#### Scenario: Zuiddrecht draws a yellow strip next to its blue notice
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js resolves the bridge and the set the way a page does
- GIVEN a portal on `zuiddrecht`
- WHEN the bridge and the set are resolved
- THEN the attention roles MUST be #FFF4DE, #E8C77D and #1A1A1A
- AND the plain notice ground MUST stay #EAF0F7

#### Scenario: A set that names none draws no strip colours
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js (the control)
- GIVEN a portal on a school set or on `vng`
- WHEN the attention roles are resolved
- THEN each MUST resolve to nothing

#### Scenario: The strip text reaches AA in both schemes
@e2e exclude Computed from the files: tests/vitest/zuiddrechtTokenSet.spec.js measures the pair in the light and in both dark scopes
- GIVEN the `zuiddrecht` tokens in the light scheme and in the generated dark one
- WHEN the attention text is measured on the attention ground
- THEN the pair MUST reach 4.5:1

### Requirement: The website type scale, controls and marks are vocabulary
A set MAY name, for its portal only, `--nldesign-website-heading-1-font-size`, `-2-font-size`
and `-3-font-size`; `--nldesign-website-control-border-width`;
`--nldesign-website-badge-border-radius`; `--nldesign-website-step-marker-size`,
`-step-done-color`, `-step-done-mark-color`, `-step-current-color` and
`-step-current-background-color`; `--nldesign-website-notice-background-color`, `-border-color`
and `-border-width`; and `--nldesign-website-tab-line-color` and `-tab-current-color`.
`css/public-bridge.css` MUST read each into the role it refines (the Utrecht heading sizes, button
and text box border widths, the plain alert, the Den Haag data badge radius and step marker roles,
and `--thematiq-tab-line-color` and `--thematiq-tab-current-color`), and no stylesheet an instance
page loads MUST read any of them. Every role that had a value MUST keep that value as its fallback;
the notice ground and border and the two tab roles MUST carry no fallback, so a consumer's own
fallback applies where a set names none, as does the data badge radius, which no pinned Den Haag
stylesheet reads. The step marker roles MUST be wrapped by the generator from the `website`
section of the mapping, never by hand.

#### Scenario: Zuiddrecht draws its boards
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js resolves the bridge and the set the way a page does
- GIVEN a portal on `zuiddrecht`
- WHEN the bridge and the set are resolved
- THEN the heading sizes MUST be 44px, 40px and 26px, the button and text box borders 2px, the
  data badge radius 14px, the step marker 36px, a done marker filled #3669A5 with a white tick and
  a blue line after it, the current marker white with a #CC0000 ring and number, the plain alert
  #EAF0F7 with a 1px #B9CBE2 border, and the tab roles #D3D8DF and #CC0000

#### Scenario: A set that names none keeps every value
@e2e exclude Static file check: tests/vitest/publicBridgeRoleLayer.spec.js (the control)
- GIVEN a portal on a school set or on `vng`
- WHEN the same roles are resolved
- THEN the heading sizes MUST be 36px, 32px and 24px, the button border 1px, the alert border 2px,
  the step marker 32px in the colours it had
- AND the plain alert ground and border and the two tab roles MUST resolve to nothing

#### Scenario: The generator refuses a website rule nothing reads
@e2e exclude Static file check: scripts/generate-denhaag-bridge.mjs --check
- GIVEN a `website` rule for a property no pinned component reads, or one naming a token outside `--nldesign-website-*`
- WHEN the section is built
- THEN the generator MUST report it as a problem
