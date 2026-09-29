# Design: document house style

## Where it fits (development b4e7568)

- `lib/Service/EmailThemingService.php:161-167` `getFooterConfig()` returns the organisation name, accessibility URL and privacy URL used in the email footer.
- `lib/Service/FontService.php` stores custom fonts in app data (`fonts/custom-<slug>.woff2`) with roles; `appinfo/routes.php:83-84` serve them (`font#serve`, `font#css`).
- `lib/Capabilities.php` and `openspec/specs/theming-capability/spec.md:37-60` already publish the active set id, name, WCAG level and `logos.default` of the active set.
- `lib/Service/GroupThemingService.php` resolves a user's set by group priority; `openspec/specs/per-group-theming/spec.md:56` keeps sessionless pages on the instance default.
- `lib/Controller/CatalogController.php:82-83` is the `#[NoAdminRequired]` read pattern for leaf apps (app-token-set-selection).
- Siblings: filinq's `huisstijl` schema has `name`, `logo`, `primaryColor`, `headerHtml`, `footerHtml`, `defaultMargins`, loaded by `DocumentRenderPipeline::loadHuisstijl()` (filinq development `7af2f955`). OpenRegister renders PDF exports with Dompdf in `ExportService::exportToPdf()` (openregister development `555af721`, `:332`), with no logo, font or footer.

## Decisions

### 1. A profile, not a template

Thematiq does not render documents. It publishes the values a document needs and leaves layout to the app that generates the document. That keeps one owner per concern: thematiq for the house style, filinq for templates, OpenRegister for exports.

Profile shape (`DocumentStyleService::forUser(?string $uid): array`):
`{ tokenSet: {id, name}, organisation, logo: {url, mime}, cover: {url, mime}|null, colours: {primary, primaryText, text, background, accent}, fonts: {heading: {family, url|null}, body: {family, url|null}}, footer: {lines: [..], accessibilityUrl, privacyUrl} }`.
Colours come from the resolved declarations of the user's set, so a group-mapped set gives its own colours. Font URLs point at `font#serve` for custom fonts and are null for system fonts.

### 2. Two ways to read it

In-process: fleet apps resolve `OCA\Thematiq\Service\DocumentStyleService` from the server container when thematiq is installed (a duck-typed lookup that returns null when it is not). Over HTTP: `GET /apps/thematiq/api/document-style` for the signed-in user, `#[NoAdminRequired]`. Both return the same array.

### 3. Document-specific assets are optional

A document logo (often a print version of the logo) and a cover image are uploaded in the Documents block, stored in app data `documents/`, validated like the converter's logo asset (type and size limits). One extra footer line is free text, escaped. Without uploads the profile uses `logos.default` of the active set and the email footer values.

### 4. In the bundle

The footer line joins the bundle as a value; the logo and cover join as metadata only, like fonts, and travel as files in a branding package when change `governance-theme-as-code` lands.

## Risks

- A sibling that never reads the profile leaves the tender unmet. The proposal names both sibling halves so their lanes can pick them up.
- Font licences: the profile exposes font URLs to signed-in users only, the same audience that already downloads them to render pages.

## Out of scope

- Rendering, pagination, templates: filinq's and OpenRegister's.
- Office documents created by users (Collabora templates): the office editor's.
