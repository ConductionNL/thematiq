# Several brands in one token source

## Why

An organisation with several brands keeps them in one token source. A municipality has a main
house style plus a style per district or per service. A design team keeps those brands in one
Tokens Studio file or one built stylesheet, and switches between them there. Thematiq cannot read
that shape today. Each brand has to be cut out by hand and uploaded as its own set.

Worse, a source that holds several brands is not refused. It is merged into one set, and one brand
silently wins:

- Built theme CSS: every class-scoped block is merged in source order, later declarations winning
  (`openspec/changes/nlds-theme-converter/design.md` decision 4, implemented in
  `TokenSetConverterService::parseCssBlocks()` at `lib/Service/TokenSetConverterService.php:609-617`).
  A file with `.noord-theme { }` and `.zuid-theme { }` becomes one set coloured like `zuid`.
- DTCG JSON: `DesignTokensMapper::assignSingleTarget()` keeps the first path that reaches a target
  and skips the rest as `duplicate-target` (`lib/Service/DesignTokensMapper.php:463-469`). The
  `$themes` and `$metadata` keys are never read, because every `$` key is skipped
  (`lib/Service/DesignTokensMapper.php:228-230`).

The upstream NL Design System themes already come from such sources. `css/tokens/nijmegen.css:1052-1054`
still carries `--nldesign-tokenSetOrder-0: brand`, `-1: common` and `-2: component`, the token set
order of a Tokens Studio file that leaked through the generator.

Matrix evidence (quoted from `rowblock.py aut-multi-brand-themes`):

### Row `aut-multi-brand-themes` (thematiq matrix, area authoring)

- Capability: Maintain several brands or themes in one token source and switch between them.
- Own rating: partial; built.state `built`. Built evidence: lib/Service/GroupThemingService.php maps
  Nextcloud groups to token sets, stored as an ordered JSON array under appconfig key
  group_token_sets (comment at top of file); lib/Controller/SettingsController.php:907
  getGroupTheming(), :934 setGroupTheming(); UI at templates/settings/admin.php:339
  (#nldesign-group-theming) via js/admin.js:3692/3891. Several house styles CAN coexist (each an
  independent uploaded/shipped token set) and be switched per group, but there is no single token
  SOURCE that holds multiple brands together (Style-Dictionary style); each brand is a separate
  uploaded set.
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint
  brand center) rated `yes`:
  https://learn.microsoft.com/en-us/microsoft-365/admin/setup/customize-your-organization-theme :
  'you can create multiple themes for the people in your organization... add or update a default
  theme... and create up to four additional group themes'
- Liferay DXP (style books, themes, client extensions) rated `yes`:
  https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/using-a-style-book-to-standardize-site-appearance :
  a site holds several style books per theme and each page picks one;
  https://learn.liferay.com/w/dxp/sites/site-appearance/design-libraries keeps shared style books in
  one place for many sites
- Tokens Studio (Figma plugin and platform) rated `yes`: tokens-studio/figma-plugin@2.12.1
  packages/tokens-studio-for-figma/src/types/ThemeObject.ts:3-10: themes group token sets;
  app/components/ThemeSelector/ThemeSelector.tsx:27-40 keeps one active theme per group
  (multi-dimensional), pro-gated at :131-135
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, openDesk theming `no`

The row carries no demand link (`origin` is empty). The demand is the three competitor cells above.

The row is partial. The switching half is built: an admin picks the instance set in the token set
dropdown, and maps groups to sets in the group theming section. The missing half is the source:
one upload that holds several brands, and keeps them linked so one new version updates them all.

## What changes

- The upload recognises a multi-brand source by its content. Two shapes count: a Tokens Studio
  document with a `$themes` list of two or more themes, and built theme CSS with two or more
  `.{brand}-theme` class blocks.
- The first upload of such a source stores nothing. The response lists the brands it found, with a
  token count per brand. The admin ticks the brands to import and confirms.
- Each chosen brand becomes a normal custom token set, `custom-{source}-{brand}`, named
  "{source name}: {brand name}". The dropdown, group theming, preview, export, dark variant and
  contrast warnings work on it unchanged.
- Each brand is converted by the existing pipeline of the open change `nlds-theme-converter`. For a
  Tokens Studio theme, the token sets it enables are merged in the file's set order first. There is
  no second parser.
- A new appconfig key `custom_token_sources` records each source: its name, input kind, content
  hash and the brands imported from it. Every brand set's manifest entry points back at its source.
- The admin can upload a new version of a source. Every imported brand is re-converted and replaced
  in one step. A brand that is gone from the new version is reported and left alone.
- The custom token sets list groups brand sets under their source name, with an "Update source"
  action per source.
- Switching between brands needs no new control. The admin picks another brand set in the existing
  dropdown, or maps a group to it.
- Every import and every source update is written to the theming audit log.

## Capabilities

### New capabilities

- `multi-brand-token-sources`: detecting a source that holds several brands, choosing which brands
  to import, storing them as linked custom sets, and updating them together from a new version.

### Modified capabilities

- `theming-audit` (`openspec/specs/theming-audit/spec.md`): the closed action vocabulary gains
  `custom_source_updated`. The spec says extending the vocabulary requires a spec change, so this
  is one.

The capability builds on `custom-token-sets` (`openspec/specs/custom-token-sets/spec.md`) and on
`token-set-converter`, which the open change `openspec/changes/nlds-theme-converter` adds. Neither
contract changes: a single-brand upload behaves exactly as before.

## Impact

- **Code**: `lib/Service/TokenSetConverterService.php` (brand detection before input detection,
  per-brand conversion), `lib/Service/DesignTokensMapper.php` (an optional reference-only path list,
  so tokens from a Tokens Studio `source` set resolve aliases without being emitted),
  `lib/Controller/CustomTokenSetController.php` (`brands` parameter on `upload()`, new
  `updateSource()`), `lib/Service/CustomTokenSetService.php` (source record, grouped listing, reuse
  of `replace()` at `:296`), `appinfo/routes.php` (one route), `templates/settings/admin.php` and
  `js/admin.js` (brand picker, grouped list, update action), `l10n/en.json` and `l10n/nl.json`.
- **Endpoints**: `POST /apps/thematiq/settings/tokensets/upload` gains an optional `brands[]`
  parameter and a `multiBrand` response. New `POST /apps/thematiq/settings/tokensets/sources/{sourceId}`.
  Both are admin-only (`#[AuthorizedAdminSetting(Admin::class)]`).
- **Depends on**: the open change `nlds-theme-converter` for the paste surface, the four input kinds
  and the conversion report. This change must land after its tasks 6.1 to 6.3.
- **Sibling repos**: none. No other app reads `custom_token_sources`.
- **No new dependency**, no network at convert time, no OpenRegister schema.

## Rows

- `aut-multi-brand-themes`, from the thematiq matrix (`openspec/parity/capabilities.json`).
