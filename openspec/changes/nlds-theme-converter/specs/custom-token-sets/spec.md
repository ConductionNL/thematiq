# Spec delta: Custom Token Sets (nlds-theme-converter)

The upload keeps every guarantee it has — a name, a slug, validation before storage, contrast
warnings, an audit entry — and gains the two things an admin actually has in hand: a theme instead of
a finished token set, and a clipboard instead of a file. Conversion (`token-set-converter`) runs
before the existing validator, never instead of it.

## ADDED Requirements

### Requirement: Theme Sources Are Accepted, Not Only Token Sets
The upload MUST accept the four input shapes defined by `token-set-converter` and MUST convert them
to a token set before validation and storage. It MUST NOT require the admin to pre-bake
`--nldesign-*` CSS.

#### Scenario: A design system's built CSS becomes a selectable token set
- GIVEN an admin picks a `design-tokens.css` whose declarations sit in a `.{prefix}-theme` block
- AND enters the name "OpenWOO"
- WHEN the upload is submitted
- THEN the content MUST be converted before validation
- AND the stored `css/tokens/custom-openwoo.css` MUST declare the required semantic tokens
- AND the response MUST confirm the set was added and is selectable

#### Scenario: A file whose content contradicts its extension is handled by content
- GIVEN a file named `tokens.json` whose content is CSS
- WHEN the upload is submitted
- THEN the input MUST be detected as CSS from the content
- AND the file name MUST be used only as a provenance hint

### Requirement: Pasted Content Is A First-Class Input
The "Custom token sets" section MUST offer a textarea beside the file picker, and
`CustomTokenSetController::upload()` MUST accept a `content` parameter as an alternative to `file`,
with an optional `sourceName` for the provenance block. Everything after reading the input MUST be
identical for both paths.

#### Scenario: Pasting the contents of a theme file creates the same set as uploading it
- GIVEN an admin pastes the full text of a `design-tokens.css` into the textarea
- AND enters a token set name
- WHEN Convert is submitted
- THEN the resulting stored CSS MUST be identical to uploading the same bytes as a file
- AND the validator MUST have run on the emitted CSS

#### Scenario: An empty paste and an empty file picker are the same error
- GIVEN neither a file nor pasted content is supplied
- WHEN the form is submitted
- THEN the response MUST be a 400 naming that a file or pasted content is required
- AND no set MUST be created

#### Scenario: Pasted content over the size limit is refused
- GIVEN pasted content larger than `CustomTokenSetValidator::MAX_SIZE`
- WHEN Convert is submitted
- THEN the response MUST refuse it with the same 512 KB limit the file path enforces

### Requirement: The Conversion Report Is Returned And Rendered
The upload response MUST carry the conversion `report` and its `counts`, and the admin panel MUST
render it grouped by reason using the existing diagnostics grouping, so an admin can see what a theme
asked for that Nextcloud will not do.

#### Scenario: The report groups skipped tokens by reason
- GIVEN a converted theme that declared page width, font sizes and button paddings
- WHEN the upload response is rendered
- THEN the panel MUST show a grouped block per reason code
- AND each group MUST show the human sentence for that code
- AND the applied / adapted / skipped counts MUST be shown

#### Scenario: The new set appears in the dropdown without a page reload
- GIVEN a successful conversion
- WHEN the response is handled
- THEN the new set MUST be appended to the token-set dropdown client-side
- AND selecting it MUST NOT require reloading the settings page

### Requirement: Component-Prefix Tokens Are Accepted In Stored Sets
`CustomTokenSetValidator` MUST accept `--utrecht-*`, `--ams-*` and `--denhaag-*` names beside
`--nldesign-*` and `--{slug}-*`, because the converted file carries a component layer that
`utrecht-bridge.css` and Conduction's own apps read. The value rules MUST NOT be relaxed.

#### Scenario: A component-prefix declaration is stored
- GIVEN a converted set declaring `--utrecht-button-border-radius: 3px`
- WHEN it is validated
- THEN the declaration MUST be accepted and stored

#### Scenario: The value gate is unchanged for the widened vocabulary
- GIVEN a declaration `--utrecht-button-border-radius: 3px; background: url(x)`
- WHEN it is validated
- THEN it MUST be rejected by `isForbiddenValue()` exactly as a `--nldesign-*` declaration would be

#### Scenario: An external url() never reaches the validator
- GIVEN a theme declaring a logo token pointing at a remote host
- WHEN it is converted and validated
- THEN the converter MUST have dropped the declaration with reason `external-url-blocked`
- AND the stored file MUST contain no remote URL
