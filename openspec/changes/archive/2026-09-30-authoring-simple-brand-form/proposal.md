# Create a house style from two colours and a logo

## Why

A small municipality or a school board often has a logo and a brand colour, not a design system. Thematiq asks them for a complete token set: a CSS file with `--nldesign-*` variables or a W3C Design Tokens document, or a walk through dozens of tokens in the editor. Nextcloud's own Theming form asks for a primary colour, a background colour and a logo, but on a thematiq instance the active token set overrides the core primary colour (`css/systems/nldesign/overrides.css:24`), so that form does not make a house style.

Nextcloud Theming and Microsoft 365 both let an organisation brand itself from a short form. Liferay does it partly.

#### Row `aut-colour-only-ui` (thematiq matrix, area authoring)

- Capability: Set a brand with nothing more than a primary colour, a background colour and a logo in a simple form.
- Own rating: no; built.state `none`. Built evidence: No simple 3-field (primary colour + background + logo) brand form exists; the only path to a house style is either uploading a full CSS/DTCG token set (CustomTokenSetController.php:203, requiring a real token document) or hand-editing individual tokens one at a time in the tabbed token editor (js/admin.js:2415 renderTokenEditor); there is a primary-colour-drives-components toggle (admin.php:427) but it locks EXISTING component tokens to the current primary, it is not a brand-creation form
- Nextcloud Theming (built-in app) rated `yes`: nextcloud/server@v35.0.1 apps/theming/src/components/AdminSectionThemingAdvanced.vue:72-116: a simple form with primary colour, background colour, background image, favicon, logo and header logo
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `yes`: https://learn.microsoft.com/en-us/microsoft-365/admin/setup/customize-your-organization-theme : the theme form is essentially logo + a handful of colour fields (nav bar, accent, text/icon), matching a simple primary-colour/background/logo form
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/using-a-style-book-to-standardize-site-appearance : the style book editor can be used to set only a few colours, and https://learn.liferay.com/w/dxp/site-building/creating-pages/page-settings/configuring-page-sets sets the logo separately, but there is no single simple form with just a primary colour, background and logo
- Rated no or unknown: openDesk theming `no`, Tokens Studio (Figma plugin and platform) `no`

## What changes

- A "Create a house style from your colours" form in the Custom token sets block: name, primary colour, background colour, optional logo.
- Thematiq derives the full semantic layer from the two colours: text on primary, hover and pressed shades, borders, focus ring, links, status colours kept at their defaults. The derived set declares every token the vocabulary audit requires.
- Before saving, the form shows the contrast of text on primary and primary on background against WCAG AA, and chooses black or white text on the primary colour, whichever reaches 4.5:1.
- The result is an ordinary custom token set: it can be edited in the token editor, exported, previewed and applied like any other, and its theming metadata feeds the theming-sync dialog with the logo.

## Capabilities

### New capabilities

- `simple-brand-form`: deriving a complete token set from two colours and a logo.

### Modified capabilities

- None. The generated set is stored through the existing custom-token-sets requirements.

## Impact

- New `lib/Service/BrandFormService.php` that builds the declarations, reusing the colour helpers of `TokenSetConverterService` (`darken()` at `:1967`, `mix()` at `:1997`) and `ContrastService`.
- `lib/Controller/CustomTokenSetController.php`: a `fromColours` endpoint next to `upload()` (`:203`); storage through `CustomTokenSetService::store()` (`:191`), which already takes a logo asset (`:199`, `:222`).
- `templates/settings/admin.php` (Custom token sets block, `:155-190`) and `js/admin.js`.
- The dark variant comes from `DarkPaletteService::deriveDarkDeclarations()` (`:289`), as for every other set.

## Rows

- `aut-colour-only-ui` (thematiq matrix). The row had no `built.owner`; this pass fills it as `ConductionNL/thematiq`.
