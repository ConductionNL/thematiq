# Design: living token reference

## Where it fits (development b4e7568)

- `lib/Service/ShippedTokenSetAuditService.php:109` `resolveDeclarations()` layers `css/tokens/{id}.css` over `css/systems/nldesign/defaults.css`; `:302` `renderReport()` renders the deterministic contrast report that `tests/Unit/TokenSetContrastAuditTest.php:230-248` compares with the committed `docs/reference/contrast-report.md` and fails when stale.
- `lib/Service/TokenRegistry.php` names the editable tokens and the tabs they belong to; `js/playground/components.json` (open change `component-playground`) says which component reads which token.
- `lib/Controller/CatalogController.php:82-83` `tokenSets()` is `#[NoAdminRequired]`, authenticated, not public (`:33`); route `catalog#tokenSets` at `appinfo/routes.php:27`.
- `docs/reference/` is the Docusaurus reference section (`docs/sidebars.js`).
- The 2026-03-02 docs change listed "interactive demos or live token previews" as a future enhancement, not a non-goal.

## Decisions

### 1. One renderer, two outputs

`TokenReferenceService::render(string $id, string $format)` produces Markdown or HTML from the resolved declarations. Rows are grouped like the token editor tabs. Each row: token name, value, swatch for colours, "declared by this set" or "from defaults", and what it paints from the registry. Colour rows also show the contrast of the token against the set background where the registry pairs them.

### 2. Docs pages are generated and checked like the contrast report

A test renders every shipped set and compares with `docs/reference/token-sets/<id>.md`; a stale page fails the suite with the regenerate command. A generated index page lists all sets. Regeneration is `composer docs:token-reference`.

### 3. In the app, readable by signed-in users

`GET /api/token-sets/{id}/reference?format=md|html` is `#[NoAdminRequired]` like the catalogue, not public: a custom set is the organisation's own and may not be published yet. The reference opens from a link next to each set in the dropdown and the custom sets list, and offers a download.

Rejected: a public URL. Suppliers without an account get the downloaded file instead.

## Risks

- Page count: one page per shipped set (48 today). The docs sidebar gets a collapsed category.
- Sets that declare hundreds of foreign tokens (see the open `nlds-theme-converter` change) produce long pages. Foreign tokens nothing reads are listed in a separate collapsed section.

## Out of scope

- Token usage examples per component beyond "what it paints".
- Translating generated pages; they carry token names and values, with headings in English and Dutch.
