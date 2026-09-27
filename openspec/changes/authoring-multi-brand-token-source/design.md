# Design: one token source, several brands

## Where it fits (development `b4e7568`)

The upload already has one entry point and one pipeline. This change adds a step in front of it and
a record behind it. It does not add a parser.

- **Entry point.** `CustomTokenSetController::upload()` (`lib/Controller/CustomTokenSetController.php:203-299`)
  reads the name, reads the input through `readInput()` (`:312-353`, file or pasted `content`, same
  512 KB limit), converts with `TokenSetConverterService::convert()` (`:220-229`), validates the
  emitted file (`:265`) and stores it through `persist()` (`:467-537`).
- **Conversion.** `TokenSetConverterService::convert()` (`lib/Service/TokenSetConverterService.php:246-252`)
  calls `detectInput()` (`:521-550`). JSON with a `$value` leaf is input B and goes to
  `DesignTokensMapper::map()` (`:639-657`). Other JSON is input C, a Style Dictionary tree
  (`:659-662`). CSS is input A or D, read by `parseCssBlocks()` (`:590-620`), which merges every
  selector block into one array (`:609-617`).
- **DTCG mapping.** `DesignTokensMapper::collectLeaves()` (`lib/Service/DesignTokensMapper.php:210-240`)
  skips every `$` key (`:228-230`), so `$themes` and `$metadata` are never seen.
  `assignSingleTarget()` keeps the first path per target (`:463-469`).
- **Storage.** `CustomTokenSetService::store()` (`lib/Service/CustomTokenSetService.php:191-270`)
  derives `custom-{slug}` (`:206`), refuses a collision with 409 (`:209-211`), writes the file,
  writes the manifest entry under appconfig `custom_token_sets` (`:252-254`), and generates the dark
  variant (`:259-264`). `replace()` (`:296`) writes a known id without the collision check. It
  exists for the config bundle and is what a source update reuses.
- **Listing and switching.** `TokenSetService::getAvailableTokenSets()` (`lib/Service/TokenSetService.php:226`)
  lists every `css/tokens/*.css`, reading custom metadata from `custom_token_sets` (`:561`). The
  dropdown (`templates/settings/admin.php:68`) and the group mapping
  (`GroupThemingService::setMapping()`, `lib/Service/GroupThemingService.php:172`, validated through
  `TokenSetService::isValidTokenSet()` at `:225`) both read that list. A brand stored as a custom
  set is therefore switchable with no change to either.
- **Admin UI.** The upload block is `templates/settings/admin.php:155-188`. `js/admin.js:4069`
  `initCustomTokenSets()`, `:4200` `uploadCustomTokenSet()` and `:4321` `renderCustomSetList()` drive it.
- **Audit.** `ThemingAuditService::log()` (`lib/Service/ThemingAuditService.php:189`) drops any
  action outside `VOCABULARY` (`:108-120`).

The open change `nlds-theme-converter` owns content detection, the four input kinds, the paste box
and the conversion report. This change builds on it and must land after its tasks 6.1 to 6.3.

## Decision 1: detect a multi-brand source before the input kind

`TokenSetConverterService` gains `detectBrands(string $content): array`. It runs before
`detectInput()` and returns a list of `{key, name, tokenCount}`. A list shorter than two means
"one brand", and the upload continues exactly as today.

Two shapes count as multi-brand:

1. **A Tokens Studio document.** JSON whose top level has a `$themes` array with two or more
   entries. Each entry is a ThemeObject (`id`, `name`, `selectedTokenSets`). The brand key is the
   `id`, the brand name is the `name`.
2. **Built theme CSS with several brand classes.** Two or more selector blocks whose selector is
   exactly one class of the form `.{key}-theme`. The brand key is `{key}`, the name is `{key}` with
   dashes turned into spaces and the first letter capitalised.

Anything else is one brand. A `:root` block, a `.{key}-theme--dark` modifier or a compound selector
is not a brand. It is shared (decision 3).

Rejected: detecting brands by file name or by a form checkbox. The paste has no name, which is why
`nlds-theme-converter` detects by content (its design decision 4), and a checkbox lets an admin
claim a shape the content does not have.

Rejected: treating Tokens Studio `$metadata.tokenSetOrder` alone as a brand list. A token set is a
layer (`brand`, `common`, `component` in `css/tokens/nijmegen.css:1052-1054`), not a brand. Only a
theme says which layers make a brand.

## Decision 2: the first upload stores nothing and lists the brands

When `detectBrands()` finds two or more brands and the request has no `brands` parameter,
`upload()` returns 200 with `{multiBrand: true, stored: false, brands: [...]}` and writes nothing.
The panel shows a checkbox per brand, all ticked, with its token count. The admin confirms, and the
panel repeats the same request with `brands[]` set to the chosen keys.

Rejected: a separate inspect endpoint. It would duplicate `readInput()` and its size checks, and
the two calls could disagree on what the content is.

Rejected: importing every brand silently. A source with twelve district brands would add twelve
sets to the dropdown that nobody asked for.

## Decision 3: how one brand is cut out of the source

The brand is turned into ordinary input for the existing pipeline. The pipeline never learns about
brands.

- **Built CSS.** The brand's own block is appended after every shared block, in source order. The
  result is one CSS document with the shared values first and the brand's values winning. It then
  goes through `convert()` as input A.
- **Tokens Studio.** Tokens Studio stores tokens per token set, and a theme names the sets it uses
  in `selectedTokenSets`, as `enabled`, `source` or `disabled`. For one brand, the `enabled` and
  `source` sets are merged in the order of `$metadata.tokenSetOrder`, later sets winning at the same
  path. A token path does not include the set name, so the merge is a recursive replace. The merged
  tree then goes through `convert()`, as input B when it has `$value` leaves and as input C when it
  has `value` leaves (the Tokens Studio legacy format).
- **Reference-only tokens.** A token that only a `source` set defines may be the target of an alias,
  but it is not part of the brand's output. `DesignTokensMapper::map()` gains an optional
  `referenceOnlyPaths` argument. A leaf on that list stays in the alias table and is never assigned
  to a target. This keeps DTCG semantics inside the mapper, as `nlds-theme-converter` requires.
  `collectStyleDictionaryLeaves()` (`lib/Service/TokenSetConverterService.php:684`) takes the same
  list for the legacy format.

Task 1.1 reads `ThemeObject.ts` and the token set status enum at tag 2.12.1 before any code, to
confirm the three status values and the set order rule. The matrix cites `ThemeObject.ts:3-10`.

## Decision 4: a brand is a normal custom set, linked to its source

Each chosen brand is stored through `CustomTokenSetService::store()` with the display name
"{source name}: {brand name}", so its id is `custom-{source slug}-{brand slug}`. It gets a file, a
dark variant, contrast warnings and an audit entry like any upload. Every existing surface works on
it unchanged: the dropdown, group theming, preview, export and delete.

The manifest entry gains `source: {id, brand}`. A new appconfig key `custom_token_sources` holds one
record per source:

```json
{
  "gemeente-voorbeeld": {
    "name": "Gemeente Voorbeeld",
    "inputKind": "tokens-studio",
    "contentHash": "sha256:1f2e3d4c5b6a",
    "brands": { "noord": "custom-gemeente-voorbeeld-noord", "zuid": "custom-gemeente-voorbeeld-zuid" },
    "updatedAt": "2026-09-27T12:00:00Z"
  }
}
```

The raw source is not stored. Its hash is enough to show whether a new upload differs.

All brand ids are checked for collisions before the first file is written. One collision refuses
the whole import with 409 and names the brand. This matches the atomic rule of
`GroupThemingService::setMapping()`.

Rejected: one set file holding every brand under a class selector. `CustomTokenSetValidator` allows
only `:root` (`CustomTokenSetController.php:422-427`), the injection loads one file per request, and
every consumer of a set id would have to learn a second key.

Rejected: grouping the brands in the dropdown with `<optgroup>`. The display name prefix already
sorts them together, and the dropdown keeps its alphabetical contract
(`openspec/specs/token-set-dropdown/spec.md`, "Token sets sorted alphabetically").

## Decision 5: updating a source updates every brand together

`POST /apps/thematiq/settings/tokensets/sources/{sourceId}` takes a new file or pasted content.
It detects brands again, converts every brand the record lists, and writes each through
`CustomTokenSetService::replace()` plus a fresh dark variant. It returns a per-brand report:
`updated`, `missing` (no longer in the source, left untouched) and `new` (in the source, not
imported). A `new` brand is not added. The admin imports it with the normal upload.

If any listed brand fails validation, nothing is replaced. The response names the brand and the
reason.

One `custom_source_updated` audit entry records the source id, the old and new content hash, and the
three brand lists. This needs a new action in the closed vocabulary.

Rejected: deleting a brand that disappeared from the source. A group may be mapped to it, and a
delete would silently move those users to the instance default.

## Decision 6: deleting

Delete stays per brand set, through the existing `CustomTokenSetController::delete()`. When the last
brand of a source is deleted, the source record is removed too. There is no "delete source" button:
deleting several sets at once, one of which may be active, is exactly the step that should stay
deliberate.

## Risks

- **Tokens Studio semantics drift.** The status values or the set order rule may differ from what
  decision 3 assumes. Task 1.1 checks them against the cited source before any code is written.
- **Themes with a `group`.** Tokens Studio pro can group themes by dimension, for example brand and
  mode. This change treats every theme as a brand. A source with a `mode` group lists light and dark
  as two brands. The panel shows the group name next to each theme so the admin can untick them.
  Dark values come from `DarkPaletteService` as today.
- **Many brands, many files.** Each brand adds a CSS file and a dark file. The 512 KB input limit
  bounds the source, not the output. The brand picker caps a single import at 20 brands.
- **Incomplete brands.** A brand that sets only a primary colour still gets the full semantic layer
  from the converter's fallbacks, and the contrast warnings show per brand.

## Out of scope

- Switching brands per page or per app. Switching stays the instance dropdown and group theming.
- Writing a multi-brand source back out (export stays per set, see `authoring-dtcg-export`).
- Syncing with Tokens Studio or Figma (rows `aut-figma-sync`, `aut-git-sync`).
- Multi-dimensional themes beyond one brand dimension.
