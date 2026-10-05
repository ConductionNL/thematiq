# Spec: School token sets

Four demo schools in Zuiddrecht, each with a token set built the way `zuiddrecht` is.

## ADDED Requirements

### Requirement: Each school set declares its design's palette
`css/tokens/<set>.css` MUST be one flat `:root` block on the `--nldesign-*` vocabulary, for
`wilgenboom`, `vaartveld`, `esdoornveen` and `warmtepompacademie`. The interactive colour and its
hover MUST be the design's main and main-deep colours (#2F6B4A and #1F4A33, #1F4FD8 and #14338F,
#5B2E91 and #43206E, #0B6E7A and #084F58). The accent MUST colour the selected navigation entry
and the navigation count badge, with the design's accent text colour as the entry's label, and
MUST NOT be the primary colour. `token-sets.json` MUST list each set on the `nldesign` design
system with its primary colour, background colour #F5F6F8, a logo and a dark logo.

#### Scenario: The vocabulary audit passes
@e2e exclude Static file check: PHPUnit tests/Unit/TokenSetVocabularyTest.php and `npm run audit:token-sets:check` audit every shipped set
- GIVEN the shipped token sets
- WHEN the vocabulary audit runs
- THEN each school set MUST be complete: no missing required token, no name nothing reads, and a primary colour equal to the manifest's

#### Scenario: The palette is the design's
@e2e exclude Static file check: tests/vitest/schoolTokenSets.spec.js reads each token file and the manifest
- GIVEN a school set's token file
- WHEN its colours are read
- THEN the primary, its hover, the primary-light tint, the link, the primary button and the accent tokens MUST equal the design's values

#### Scenario: The example sets stay
@e2e exclude Static file check: tests/vitest/schoolTokenSets.spec.js reads the manifest
- GIVEN installed instances that reference `example-basisschool`, `example-voortgezet`, `example-college` or `example-opleider`
- WHEN the school sets are added
- THEN those four sets MUST still be listed under their own ids with their own token files

### Requirement: Every text pair reaches AA
Every text and background pair a school set names MUST reach 4.5:1 in the light scheme and in
both dark scopes, and a control border MUST reach 3:1 against a card. Where the accent is too
light to carry white (wilgenboom, vaartveld), the solid selected entry MUST carry ink instead.
Where the generated dark variant falls short, `css/token-overrides/<set>.css` MUST set the value
by hand for the two dark scopes.

#### Scenario: Light and dark pairs
@e2e exclude Computed from the token files: tests/vitest/schoolTokenSets.spec.js resolves the light file, the generated dark file and the overrides, and computes each ratio
- GIVEN a school set's light file, generated dark file and overrides
- WHEN each named pair is computed against the main background and the workspace
- THEN text, muted text, link, status text, the primary button label, the header text, the selected entry's label on its wash and on its solid fill, the badge number and each status pill MUST reach 4.5:1

#### Scenario: The dark workspace sits below the cards
@e2e exclude Static file check: tests/vitest/schoolTokenSets.spec.js reads the overrides file
- GIVEN a school set's overrides file
- WHEN its dark scopes are read
- THEN the page background and the content surface MUST be #121315, darker than Nextcloud's dark cards

### Requirement: Each school set wears the light workplace
Each school set's manifest entry MUST carry `workplace_layout: light`. `wilgenboom` and
`esdoornveen`, whose designed Nextcloud login shows the motif along the login card, MUST also
carry `brand_stripe: true`; `vaartveld` and `warmtepompacademie` MUST NOT. The workplace MUST keep
Nextcloud's radius scale: 8px controls, 12px containers.

#### Scenario: The layout follows the set
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php resolves each set with nothing stored
- GIVEN an instance with no stored layout choice
- WHEN a school set is active
- THEN the workplace layout MUST be `light`, and the brand stripe MUST be on for `wilgenboom` and `esdoornveen` only

### Requirement: Each school set ships its logos and a login watermark
`img/logos/` MUST hold `<set>.svg`, `<set>-dark.svg`, `<set>-emblem.svg` and
`<set>-emblem-grey.svg` for each school set, each a self-contained SVG. The token file MUST name
the grey emblem as the login watermark, and the overrides file MUST give the wordmark 200 by 44
pixels on the login card.

#### Scenario: The logos are there
@e2e exclude Static file check: tests/vitest/schoolTokenSets.spec.js and PHPUnit tests/Unit/Service/SetLogoReachTest.php read the logo directory
- GIVEN the logo directory
- WHEN a school set's four files are read
- THEN each MUST start with `<svg` and carry no `<style>` and no `href`

### Requirement: Each school set's faces are bundled
The faces each design uses MUST be served from the app's own files in both font layers, with
the SIL Open Font License beside them: Lexend 400 to 700; Red Hat Text 400 to 700 and Red Hat
Display 600 to 800; IBM Plex Sans 400 to 700 and IBM Plex Mono 400 and 500; Barlow 400 to 700 and
Barlow Semi Condensed 600 and 700. A set with a heading face MUST name it in
`--nldesign-component-heading-font-family`.

#### Scenario: A weight the design uses is served
@e2e exclude Static file check: tests/vitest/schoolTokenSets.spec.js and tests/vitest/fontLicences.spec.js read the stylesheets and the files
- GIVEN `css/fonts.css` and `css/systems/nldesign/fonts.css`
- WHEN their `@font-face` rules are read
- THEN each weight of each face MUST have a rule whose file exists

### Requirement: A set may draw its motif in the brand stripe
`css/brand-stripe.css` MUST draw `--nldesign-brand-stripe-image` when a set names it, in place of
the three bands, and the three bands when it names none. Each school set MUST name its motif
there: the twigs of `wilgenboom`, the canal of `vaartveld`, the slanted cut of `esdoornveen` and
the temperature line of `warmtepompacademie`, at the motif's height.

#### Scenario: The image replaces the bands
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the stripe rule, tests/vitest/schoolTokenSets.spec.js reads each set's image
- GIVEN the stripe rule
- WHEN its background is read
- THEN it MUST be the set's image with the three-band gradient as the fallback

### Requirement: The website keeps its own corners and heading face
`css/public-bridge.css` MUST map the website's control roles onto
`--nldesign-website-border-radius` and its card roles onto
`--nldesign-website-border-radius-large`, with no fallback, and no stylesheet an instance page
loads MUST read either token. The heading roles MUST read
`--nldesign-component-heading-font-family` before the text face.

#### Scenario: The workplace does not see the website corners
@e2e exclude Static file check: tests/vitest/schoolTokenSets.spec.js scans css/ for readers of the website radius tokens
- GIVEN the stylesheets under `css/`
- WHEN each is searched for a reader of the website radius tokens
- THEN only `css/public-bridge.css` MUST read them
