# Living token reference for every house style

## Why

A supplier who builds a form or a portal page for a municipality asks one question first: which tokens does this house style have, and what are their values? Thematiq can answer it for contrast (`docs/reference/contrast-report.md`, generated per shipped set) and for the token architecture in general (`docs/reference/tokens.md`), but not per set. The answer lives in a CSS file of up to a thousand lines. Custom sets an organisation uploaded have no reference at all.

#### Row `cat-living-token-docs` (thematiq matrix, area catalogue)

- Capability: Generate living documentation of the house style's tokens as a core feature.
- Own rating: partial; built.state `built`. Built evidence: docs/reference/contrast-report.md is generated per shipped set by ShippedTokenSetAuditService::renderReport() and docs/reference/tokens.md documents the token architecture on thematiq.conduction.nl, but no generated per-set token reference is published
- Demand: featureRequest at https://github.com/tokens-studio/figma-plugin/issues/3355 (suppliers building for a municipality need a readable reference of the tokens they must use)
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/using-a-style-book-to-standardize-site-appearance : the colour picker lists existing colours by category and token set with values on hover and search, and the Style Guide Sample widget renders UI elements with the current tokens; no generated token documentation
- Tokens Studio (Figma plugin and platform) rated `partial`: tokens-studio/figma-plugin@2.12.1 packages/tokens-studio-for-figma/src/app/components/GenerateDocumentationButton.tsx:9-24 draws token documentation into a Figma frame; no published documentation page
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `unknown`, openDesk theming `no`

## What changes

- A generated reference page per shipped token set on the documentation site: every token the set declares or inherits, its value, a swatch for colours, what it paints, and whether it came from the set or from the defaults layer.
- The pages are generated from the token files and checked in CI, like the contrast report, so they cannot go stale.
- Inside Nextcloud, a signed-in user can open the reference of any available set, custom sets included, and download it as Markdown or HTML to hand to a supplier.

## Capabilities

### New capabilities

- `token-reference`: generated per-set token documentation, on the docs site and in the app.

### Modified capabilities

- None.

## Impact

- New `lib/Service/TokenReferenceService.php`, reusing `ShippedTokenSetAuditService::resolveDeclarations()` (`:109`) for the layered values and the token registry for what each token paints.
- `lib/Controller/CatalogController.php`: `GET /api/token-sets/{id}/reference` next to `tokenSets()` (`:82-83`, `#[NoAdminRequired]`).
- New generated pages under `docs/reference/token-sets/`, and a staleness test next to `tests/Unit/TokenSetContrastAuditTest.php` (`:230-248`).
- A reference link per set in the custom token sets list and next to the Design token set dropdown.

## Rows

- `cat-living-token-docs` (thematiq matrix): the missing half, a generated per-set token reference.
