# Spec delta: Token Set Converter (nlds-theme-converter)

New capability. Turning a published design-system theme into a Nextcloud token set is its own
contract: what may be fed in, what comes out, which mapping decides it, and what the caller is told
about everything that did not survive. `custom-token-sets` (an admin upload) and `token-sets` (a
shipped file) both consume this capability and neither owns it.

## ADDED Requirements

### Requirement: Accepted Conversion Inputs
The converter MUST accept four input shapes and MUST determine the shape from the CONTENT, not from a
file name, because the primary surface is a paste with no file name. It MUST reject content matching
none of them rather than store an empty or partial set.

The accepted shapes are: (A) built theme CSS with one or more class-scoped blocks of custom
properties, (B) a W3C DTCG document, (C) a Style Dictionary `tokens.json` tree, and (D) an existing
`:root`-scoped `--nldesign-*` token set.

#### Scenario: Built theme CSS is converted from a class-scoped block
@e2e exclude Pure conversion — vitest on tokenConverter and PHPUnit on TokenSetConverterService
- GIVEN content whose only selector block is `.openwoo-theme { --utrecht-button-primary-action-background-color: var(--openwoo-color-primary); --openwoo-color-primary: #23845c; }`
- WHEN the content is converted with slug `openwoo`
- THEN the input MUST be detected as input A
- AND `--nldesign-color-primary` MUST be `#23845c`
- AND the `var()` chain MUST be resolved to a literal in the emitted file

#### Scenario: A DTCG document is routed to the existing mapper
@e2e exclude Pure conversion — vitest and PHPUnit parity fixtures
- GIVEN content that parses as JSON and contains at least one leaf with a `$value` key
- WHEN the content is converted
- THEN the input MUST be detected as input B
- AND the DTCG semantics (alias `{a.b.c}` resolution, `$type` dispatch, the suffix table) MUST be
  those of `DesignTokensMapper`
- AND no second DTCG parser MUST be introduced

#### Scenario: An existing token set is detected as input D
@e2e exclude Pure conversion — vitest and PHPUnit parity fixtures
- GIVEN content whose selector is `:root` and whose declarations are predominantly `--nldesign-*`
- WHEN the content is converted
- THEN the input MUST be detected as input D
- AND the conversion MUST run in add-only mode

#### Scenario: Unrecognised content is refused
- GIVEN pasted content that is neither parseable JSON nor contains a selector block with custom
  properties
- WHEN the conversion is requested
- THEN the converter MUST return an error naming the four accepted shapes
- AND no token set file MUST be written
- AND no `token-sets.json` entry MUST be added

### Requirement: Converted Output Shape And Provenance
The converter MUST emit exactly one flat `:root { }` block with no at-rules, in four commented
sections in a fixed order — brand palette under `--{slug}-*`, component layer, semantic
`--nldesign-*` layer, provenance — so that the output passes `TokenCssShapeTest` and two runs of the
same input diff cleanly.

#### Scenario: The four sections are emitted in order with a provenance block
@e2e exclude Generated file shape — vitest and PHPUnit
- GIVEN any accepted input converted with slug `zwolle`
- WHEN the emitted CSS is inspected
- THEN it MUST contain exactly one `:root {` block and no `@media`, `@supports` or `@import`
- AND the brand palette section MUST declare its raw steps as `--zwolle-*`, never as `--nldesign-*`
- AND the provenance comment MUST record the input kind, the source name and version when present,
  the converter version, the mapping table SHA-256, and the applied / adapted / skipped counts

#### Scenario: Palette steps found under the app vocabulary are re-prefixed
@e2e exclude Generated file shape — vitest and PHPUnit
- GIVEN an input that declares `--nldesign-color-blue-40: #1b3d6b` (a raw upstream palette step)
- WHEN it is converted with slug `nijmegen`
- THEN the emitted file MUST declare `--nijmegen-color-blue-40`
- AND MUST NOT declare `--nldesign-color-blue-40`
- AND the report MUST carry the move as `adapted` with reason `palette-reprefixed`

#### Scenario: A var() chain that leaves the input is not emitted as-is
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN a declaration whose value is `var(--some-foreign-token)` that the input does not define
- WHEN the content is converted
- THEN the value MUST be reported with reason `unresolved-var`
- AND MUST NOT be emitted into the semantic layer, because a token set is also served on the login
  page and in e-mails where the source theme's variables do not exist

### Requirement: Semantic Mapping Is Table-Driven And Shared
The `--nldesign-*` semantic layer MUST be produced by the ordered rule list in
`scripts/mapping/nlds-to-nextcloud.json` — first matching source wins — and BOTH runtimes (the PHP
service and the JS module) MUST load that same file. The transform set MUST be closed:
`copy`, `darken`, `mix`, `rgbTriplet`, `alpha`, `radiusScale`.

#### Scenario: The first matching source wins
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN a rule whose sources are `--utrecht-button-primary-action-background-color` then `--{p}-color-primary`
- AND an input that declares both with different values
- WHEN the content is converted
- THEN the target MUST take the value of the first source
- AND the report MUST name that source

#### Scenario: A missing source falls back and is reported as adapted
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN an input that declares no hover colour for its primary action
- WHEN the content is converted
- THEN `--nldesign-color-primary-hover` MUST be derived by `darken` from the primary
- AND the report entry MUST have action `adapted`, not `applied`

#### Scenario: The contrast guard cannot emit a set that fails the contrast audit
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN a theme whose primary and primary-text pair measures below 4.5:1
- WHEN the content is converted
- THEN the guarded value MUST be darkened in steps until the ratio is met, to a maximum of 10 steps
- AND the report MUST carry reason `contrast-adjusted` with the original value retained for reference
- AND the emitted set MUST pass the pairs `ShippedTokenSetAuditService` audits

#### Scenario: Both runtimes produce the same result for the same input
@e2e exclude Parity — vitest writes the expectation, PHPUnit asserts against it
- GIVEN any fixture in `tests/Unit/fixtures/converter/`
- WHEN it is converted by `js/lib/tokenConverter.js` and by `TokenSetConverterService`
- THEN the emitted CSS MUST be byte-equal
- AND the reports MUST be structurally equal

### Requirement: Nothing Is Dropped Silently
Every source token MUST end up in the report with an action of `applied`, `adapted`, `skipped` or
`kept`, and every non-`applied` entry MUST carry a reason code that exists in the mapping table.
Tokens deliberately not applied to Nextcloud MUST be distinguished from tokens kept in the file for
NL Design System components.

#### Scenario: Layout, type scale and clickable-area tokens are refused with a reason
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN an input declaring `--utrecht-page-max-inline-size`, `--utrecht-document-font-size` and
  `--utrecht-button-padding-block-start`
- WHEN the content is converted
- THEN each MUST be reported as `skipped` with reason `layout-fixed-by-nextcloud`,
  `typography-scale-locked` and `clickable-area-locked` respectively
- AND none of them MUST appear in the semantic layer

#### Scenario: Component tokens Nextcloud does not have are kept, not skipped
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN an input declaring accordion, breadcrumb and skip-link component tokens
- WHEN the content is converted
- THEN they MUST be emitted in the component section
- AND MUST be reported with action `kept` and reason `kept-for-nlds-components`
- AND MUST NOT be counted as skipped

#### Scenario: The page background is routed to core theming rather than the content background
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN an input declaring `--utrecht-page-background-color: #f5f5f5`
- WHEN the content is converted
- THEN the manifest entry's `theming.background_color` MUST be `#f5f5f5`
- AND `--color-main-background` MUST NOT be targeted, so Nextcloud keeps owning dark mode
- AND the report MUST carry reason `routed-to-core-theming`

#### Scenario: An external url() value is dropped before the validator sees it
@e2e exclude Security path — PHPUnit on TokenSetConverterService and CustomTokenSetValidator
- GIVEN an input declaring a logo or background token whose value points at a remote host
- WHEN the content is converted
- THEN the declaration MUST NOT be emitted
- AND the report MUST carry reason `external-url-blocked`

#### Scenario: A source token with no rule is reported rather than forgotten
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN a source token that matches no rule and no policy entry
- WHEN the content is converted
- THEN it MUST be reported with reason `unmapped`, so the mapping table can be extended

### Requirement: The Theme's Logo Becomes A File, Not A Data URI

A design-system theme ships its wordmark inline, as a `data:` URI on a logo token. Nextcloud's core
theming takes a logo as a FILE — `ImageManager::updateImage()` copies one, and the `theming-sync`
spec requires `theming.logo` to be a path under `img/logos/` that exists on disk — so the converter
MUST decode that payload into an image the caller can write, and MUST point both the token set and
the manifest entry at the written file. Without this a converted theme can never update the
Nextcloud logo, however complete its colours are.

#### Scenario: An inline logo is decoded into an asset
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN an input declaring a logo token whose value is `url("data:image/svg+xml;base64,…")`
- WHEN the content is converted with asset name `custom-openwoo`
- THEN the result MUST carry a `logoAsset` of `{path: "img/logos/custom-openwoo.svg", contents}`
  whose contents are the decoded bytes
- AND the manifest entry's `theming.logo` MUST be `img/logos/custom-openwoo.svg`
- AND `--nldesign-logo-url` MUST be emitted as `url('../../img/logos/custom-openwoo.svg')`
- AND the report MUST carry reason `logo-extracted`

#### Scenario: The payload is not repeated across the theme's other logo slots
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN a theme declaring the same artwork on its header, navbar and footer logo tokens
- WHEN the content is converted
- THEN each remaining slot MUST be emitted as `var(--nldesign-logo-url)`
- AND the emitted file MUST NOT contain the base64 payload, which is served to every anonymous
  visitor on the login page

#### Scenario: A logo already stored as a file keeps its path
@e2e exclude Pure conversion — vitest and PHPUnit
- GIVEN an input declaring `--nldesign-logo-url: url('../../img/logos/openwoo.svg')`
- WHEN the content is converted
- THEN `theming.logo` MUST be `img/logos/openwoo.svg`
- AND no `logoAsset` MUST be produced, because there are no bytes to write

#### Scenario: An unusable logo is reported, never written
@e2e exclude Security path — PHPUnit on TokenSetConverterService
- GIVEN a logo token whose value is an image type Nextcloud does not accept, a payload over
  256 KB, or a path outside `img/logos/`
- WHEN the content is converted
- THEN no `logoAsset` MUST be produced and `theming.logo` MUST be absent
- AND the report MUST carry reason `logo-format-unsupported`
- AND the reported value MUST be truncated, so a rejected data URI never reaches the admin panel
  or the audit log

#### Scenario: An extracted logo cannot overwrite a shipped one
@e2e exclude Security path — PHPUnit on CustomTokenSetService
- GIVEN an admin uploads a theme named "Amsterdam", for which `img/logos/amsterdam.svg` is shipped
- WHEN the upload is stored
- THEN the written file MUST be `img/logos/custom-amsterdam.svg`, named after the set id
- AND `img/logos/amsterdam.svg` MUST be unchanged
- AND deleting the custom set MUST remove the file it wrote

### Requirement: Re-Conversion Never Overwrites A Chosen Value
When the input is an existing token set (input D), a declaration the file already carries MUST win
and the converter MUST only add what is missing.

#### Scenario: A hand-authored set gains only additions
@e2e exclude Pure conversion — vitest and PHPUnit over css/tokens/openwoo.css
- GIVEN `css/tokens/openwoo.css`, which declares `--nldesign-color-primary: #23845c` by hand
- WHEN it is re-converted with slug `openwoo`
- THEN `--nldesign-color-primary` MUST still be `#23845c`
- AND every previously declared value MUST be unchanged
- AND each untouched declaration MUST be reported with action `kept` and reason `kept-existing-value`
