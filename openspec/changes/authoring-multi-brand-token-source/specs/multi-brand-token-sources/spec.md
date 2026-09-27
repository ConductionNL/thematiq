# Spec delta: multi-brand token sources (authoring-multi-brand-token-source)

One upload can hold several brands. The administrator chooses which brands to import. Each brand
becomes a custom token set that stays linked to its source, so one new version of the source
updates every brand.

## ADDED Requirements

### Requirement: A multi-brand source is recognised by its content

The upload MUST recognise a source that holds two or more brands before it decides the input kind.
It MUST count two shapes as multi-brand: a JSON document with a top-level `$themes` array of two or
more entries, and CSS with two or more selector blocks whose selector is exactly one `.{key}-theme`
class. Any other content MUST be handled as one brand, exactly as before this change.

#### Scenario: A Tokens Studio file with three themes is recognised
@e2e exclude Content detection is a pure function, covered by PHPUnit on TokenSetConverterService
- GIVEN an administrator on Settings > Administration > Theming
- AND a JSON file whose `$themes` array holds the themes `noord`, `zuid` and `centrum`
- WHEN the administrator uploads it with the name "Gemeente Voorbeeld"
- THEN `POST /apps/thematiq/settings/tokensets/upload` MUST answer 200 with `multiBrand: true`
- AND the `brands` list MUST hold `noord`, `zuid` and `centrum` with their names and token counts

#### Scenario: Built CSS with two brand classes is recognised
@e2e exclude Content detection is a pure function, covered by PHPUnit on TokenSetConverterService
- GIVEN pasted CSS with a `:root { }` block, a `.noord-theme { }` block and a `.zuid-theme { }` block
- WHEN the administrator submits it
- THEN the response MUST list the brands `noord` and `zuid`
- AND the `:root` block MUST NOT be listed as a brand

#### Scenario: A single-brand source behaves as before
- GIVEN a file with one `.openwoo-theme { }` block
- WHEN the administrator uploads it
- THEN the set MUST be converted and stored in the same request
- AND the response MUST NOT contain `multiBrand`

### Requirement: The administrator chooses which brands to import

When a multi-brand source is uploaded without a `brands` parameter, the upload MUST store nothing
and MUST return the brand list with `stored: false`. The settings panel MUST show one checkbox per
brand, ticked by default, with its name and token count. Confirming MUST repeat the upload with
`brands[]` set to the ticked keys. An import MUST NOT exceed 20 brands.

#### Scenario: The administrator imports two of three brands
- GIVEN the administrator uploaded a source with the brands `noord`, `zuid` and `centrum`
- AND the panel shows three ticked checkboxes
- WHEN the administrator unticks `centrum` and confirms
- THEN the sets "Gemeente Voorbeeld: Noord" and "Gemeente Voorbeeld: Zuid" MUST appear in the token
  set dropdown without a page reload
- AND no set MUST exist for `centrum`

#### Scenario: Nothing is written before the administrator confirms
@e2e exclude Storage side effect, covered by PHPUnit on CustomTokenSetController
- GIVEN a multi-brand source uploaded without `brands`
- WHEN the response returns
- THEN no file under `css/tokens/` MUST have been written
- AND the appconfig keys `custom_token_sets` and `custom_token_sources` MUST be unchanged

#### Scenario: An unknown brand key is refused
@e2e exclude API validation branch, covered by the Newman collection
- GIVEN a multi-brand source
- WHEN an administrator posts it with `brands[]=west`, a key the source does not hold
- THEN the response MUST be 422 naming `west`
- AND nothing MUST be stored

### Requirement: Each brand is converted by the existing pipeline

Each chosen brand MUST be turned into one input for `TokenSetConverterService::convert()` and MUST
NOT be parsed by a second parser. For built CSS, the brand block MUST follow every shared block in
source order. For a Tokens Studio theme, the token sets the theme marks `enabled` or `source` MUST
be merged in `$metadata.tokenSetOrder` order, later sets winning. A token defined only by a `source`
set MUST be usable as an alias target and MUST NOT appear in the brand's output.

#### Scenario: A brand inherits the shared values
@e2e exclude Conversion output, covered by PHPUnit on TokenSetConverterService
- GIVEN CSS where `:root` sets `--voorbeeld-font` and `.noord-theme` sets `--voorbeeld-primary: #00689e`
- WHEN the brand `noord` is converted
- THEN the stored set MUST carry `--nldesign-color-primary: #00689e`
- AND it MUST carry the font from the shared block

#### Scenario: A reference-only token resolves an alias but is not emitted
@e2e exclude Conversion output, covered by PHPUnit on DesignTokensMapper
- GIVEN a Tokens Studio theme `zuid` with set `palette` as `source` and set `zuid` as `enabled`
- AND `zuid` defines `color.primary` as `{blue.60}`, which only `palette` defines
- WHEN the brand is converted
- THEN `--nldesign-color-primary` MUST hold the value of `blue.60`
- AND no declaration MUST be emitted for `blue.60` itself

### Requirement: A brand is a custom token set linked to its source

Each imported brand MUST be stored as a custom token set with the display name
"{source name}: {brand name}" and the id `custom-{source slug}-{brand slug}`. Its manifest entry
MUST carry `source: {id, brand}`. The appconfig key `custom_token_sources` MUST hold one record per
source with its name, input kind, content hash, brand-to-set map and update time. Every brand id
MUST be checked for a collision before the first file is written. One collision MUST refuse the
whole import with 409 and store nothing.

#### Scenario: A brand set works like any custom set
- GIVEN the brand set "Gemeente Voorbeeld: Noord" was imported
- WHEN the administrator selects it in the token set dropdown
- THEN the instance MUST render with the `noord` colours
- AND its contrast warnings, dark variant and export MUST work as for any custom set

#### Scenario: A group is switched to another brand
- GIVEN the brand sets for `noord` and `zuid` exist
- AND the group `stadsdeel-zuid` exists
- WHEN the administrator maps `stadsdeel-zuid` to "Gemeente Voorbeeld: Zuid" in group theming
- THEN a member of `stadsdeel-zuid` MUST see the `zuid` brand
- AND a user outside the group MUST see the instance default set

#### Scenario: A collision refuses the whole import
@e2e exclude Atomic storage branch, covered by PHPUnit on CustomTokenSetService
- GIVEN the set `custom-gemeente-voorbeeld-zuid` already exists
- WHEN the administrator imports `noord` and `zuid` from "Gemeente Voorbeeld"
- THEN the response MUST be 409 naming the brand `zuid`
- AND no set for `noord` MUST have been written

#### Scenario: The set list groups brands under their source
- GIVEN two brands were imported from "Gemeente Voorbeeld"
- WHEN the administrator opens the custom token sets list
- THEN both sets MUST be listed under the heading "Gemeente Voorbeeld"
- AND the source MUST offer an "Update source" action

### Requirement: A new version of a source updates every brand together

`POST /apps/thematiq/settings/tokensets/sources/{sourceId}` MUST accept a file or pasted content,
MUST re-convert every brand the source record lists, and MUST replace each brand set and its dark
variant. It MUST report per brand whether it was `updated`, `missing` from the new version, or
`new` in it. A `missing` brand set MUST be left untouched. A `new` brand MUST NOT be imported. If
any listed brand fails validation, no brand set MUST be replaced. The endpoint MUST be admin-only.

#### Scenario: The administrator updates a source with a changed primary colour
- GIVEN the brands `noord` and `zuid` were imported from "Gemeente Voorbeeld"
- AND the new version changes the primary colour of `zuid`
- WHEN the administrator uses "Update source" and picks the new file
- THEN both brand sets MUST be replaced
- AND a member of a group mapped to `zuid` MUST see the new primary colour on the next page load

#### Scenario: A brand that disappeared is kept and reported
- GIVEN the new version of the source no longer holds `noord`
- WHEN the source is updated
- THEN the response MUST list `noord` as `missing`
- AND the set "Gemeente Voorbeeld: Noord" MUST still exist and be selectable

#### Scenario: One failing brand replaces nothing
@e2e exclude Atomic replace branch, covered by PHPUnit on CustomTokenSetController
- GIVEN the new version gives `zuid` a value the validator refuses
- WHEN the source is updated
- THEN the response MUST be 422 naming `zuid` and the reason
- AND the stored files for `noord` and `zuid` MUST be byte-identical to before

#### Scenario: A non-administrator cannot update a source
@e2e exclude Auth posture, covered by the Newman collection
- GIVEN a logged-in user who is not an administrator
- WHEN they post to `/apps/thematiq/settings/tokensets/sources/gemeente-voorbeeld`
- THEN the response MUST be 403
- AND no set MUST change
