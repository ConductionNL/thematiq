# Design: create a house style from two colours and a logo

## Where it fits (development b4e7568)

- `css/systems/nldesign/overrides.css:19-24`: `:root { --color-primary: var(--nldesign-color-primary) !important; }`. With a design system active, the core Theming primary colour is overridden, so the core form is not a way to brand a thematiq instance.
- `lib/Service/CustomTokenSetService.php:191-230` `store(displayName, description, declarations, version, importWarnings, css, theming, logoAsset)` writes `css/tokens/custom-<slug>.css`, a manifest entry and an optional logo (`:222-223`), refuses duplicates with 409, and derives theming metadata (`deriveTheming()`, `:451`).
- `lib/Service/TokenSetConverterService.php:1967` `darken()` and `:1997` `mix()` mirror `js/lib/tokenTransforms.js`, so PHP and the browser preview compute the same shades.
- `lib/Service/TokenSetVocabularyAuditService.php:97` `REQUIRED_TOKENS` is the list of semantic tokens a correct set declares.
- `openspec/specs/custom-token-sets/spec.md:227-253`: contrast failures are warnings, never blocks. `:256` the theming bridge feeds the theming-sync dialog.
- `templates/settings/admin.php:155-190`: the Custom token sets block with the upload form.

## Decisions

### 1. Generate a complete set, not a partial one

`BrandFormService::derive(primary, background)` returns declarations for every token in `REQUIRED_TOKENS`. Tokens that need a colour the form did not ask for (status colours, neutral greys) take the values of the `defaults` layer, so an incomplete result is impossible. The derivation rules live in one data file, `scripts/mapping/brand-form.json`, read by PHP and by the browser preview, so the preview shows exactly what will be stored.

Rejected: storing only the two colours and letting the defaults layer fill the rest at runtime. Hover, pressed and text-on-primary would then stay Rijkshuisstijl blue on a red brand.

### 2. Text colour chosen by contrast

Text on primary is black or white, whichever reaches 4.5:1 against the primary colour; when neither does (mid-tone colours), the form picks the higher one and shows the warning. The form also shows primary against background at 3:1 for UI components. Both follow the warn-only rule of `custom-token-sets` (`:227-253`): a poor contrast is shown, not blocked.

### 3. Stored as an ordinary custom set

The result goes through `CustomTokenSetService::store()` with `theming` set from the two colours and the logo as `logoAsset`. From there it behaves like any upload: token editor, export, preview, group mapping, bundle.

### 4. The form lives next to the upload

It is a second tab of the Custom token sets block ("From colours" next to "Upload a file"), because both end in the same list.

## Risks

- A derived palette is plainer than a designed one. The docs say so and point to the token editor for refinement.
- The logo upload reuses the logo asset path of the converter; its size and type limits apply unchanged.

## Out of scope

- Deriving a set from a logo image (colour extraction).
- More than two input colours.

## Design change at build (30 Sep 2026)

- The endpoint is `POST /settings/tokensets/from-colours` on a new `BrandFormController`, not on `CustomTokenSetController`, whose constructor is at its limit and pinned by its tests. It stores through `CustomTokenSetService::store()` as decision 3 says. `GET` on the same URL serves the rules and the defaults to the preview, so the Admin settings class needs no new dependency.
- Decision 4: the form is a section, "Start from your colours", under the upload form in the Custom token sets block, not a tab. Both still end in the same list.
- Decision 2: with pure black and pure white, one of the two always reaches at least 4.58:1 (the lowest maximum, at a relative luminance of about 0.18), so "neither reaches 4.5:1" cannot happen. The mid-tone scenario now warns about primary on background under 3:1, which does happen (`#ffd200` on white).
- Links take the primary colour only when it reaches 4.5:1 on the background, else the defaults layer's link colour (rule `legible`).
- The defaults layer is read with `CssParserService::parseRootBlock()`, which stopped at a brace inside a comment in `defaults.css`; fixed on the token reference branch (#756) this change stacks on.
