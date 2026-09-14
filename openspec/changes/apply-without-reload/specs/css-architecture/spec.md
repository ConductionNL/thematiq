# Spec delta: CSS Architecture (apply-without-reload)

The cascade gains no layer. What changes is that the set-dependent part of it is now one ordered
list with two consumers — the page render and a manifest endpoint — so the admin panel can replace a
set's stylesheets on the page it is on without a reload, and can never disagree with the server
about which files a set consists of.

## ADDED Requirements

### Requirement: The Set-Dependent Cascade Has One Owner
`CssInjectionService` MUST build the set-dependent layers — the design-system stylesheets, the
Marianne layer, the set's token file, the logo `<style>`, the set's element overrides, the dark
variant, the contrast fixes — as one ordered list, and MUST emit the page render from that list.
The set-independent layers (`custom-overrides.css`, `custom-css.css`, the hide-slogan and
show-menu-labels stylesheets, the preview banner) MUST stay outside the list and MUST be emitted
after it.

#### Scenario: The page render is the list, verbatim
@e2e exclude Pure service logic — PHPUnit on CssInjectionService with the emit seams stubbed
- GIVEN the active set is `rijkshuisstijl` on the `nldesign` design system
- WHEN `inject('user')` runs
- THEN the emitted files MUST be, in order, the six design-system stylesheets,
  `tokens/rijkshuisstijl`, `icon-contrast`, `error-contrast`, then `custom-overrides`
- AND the emitted order MUST be unchanged from before this change

### Requirement: Stylesheet Manifest Endpoint
The app MUST expose `GET /settings/tokenset-stylesheets/{tokenSetId}` (admin-only, read-only,
no side effects) answering `{tokenSet, designSystem, layers}` where `layers` is the set-dependent
list for that set resolved to `{layer, kind: "file", href}` or `{layer, kind: "inline", id, css}`
entries, in cascade order, plus the custom-font link when the design system reads token variables
and fonts exist.

#### Scenario: Manifest equals the injected set layers
@e2e exclude Pure service logic — PHPUnit on CssInjectionService
- GIVEN the same configuration as the page render
- WHEN `getStylesheetManifest('rijkshuisstijl')` is called
- THEN its `file` layers, reduced to their path under `css/`, MUST equal the files `inject()` emitted
  minus the set-independent ones, in the same order
- AND every `file` href MUST carry `?v=<installed app version>`
- AND the inline layer MUST carry `id` `nldesign-logo-url`

#### Scenario: Stock Nextcloud has an empty manifest
@e2e exclude Pure service logic — PHPUnit on CssInjectionService
- GIVEN the set `nextcloud` (design system `none`)
- WHEN its manifest is requested
- THEN `layers` MUST be `[]`, so a client switching to stock removes every Thematiq layer

#### Scenario: Unknown set
- GIVEN an id `TokenSetService::isValidTokenSet()` rejects
- WHEN the endpoint is called
- THEN the response MUST be HTTP 404 and nothing MUST be computed for it

### Requirement: The Logo Style Is Addressable
The inline `<style>` that re-declares `--nldesign-logo-url` MUST carry `id="nldesign-logo-url"` so
a client can replace it when the set changes; a `<link>` is found again by its pathname, an inline
style has nothing else to be found by. The logo layer MUST accept `img/logos/<set>.<ext>` for
`svg`, `png`, `jpg`, `gif` and `webp`, first match wins, so a converter-extracted raster logo is
served the same way as a shipped SVG.

### Requirement: Client Swaps The Run, Never Rewrites Variables
The admin panel MUST apply a set to the current page by inserting the new manifest's elements at
the place the current set's run occupies, waiting for each `<link>` to load, and then removing the
current run; it MUST match existing elements to manifest layers by pathname (never by the
cache-busting query) and inline styles by id. It MUST NOT apply a set by writing resolved variables
inline on `<html>`.

#### Scenario: No flash, no gap
@e2e exclude Timing — covered by the workflow spec's outcome assertions
- GIVEN a set is on the page
- WHEN another set is applied
- THEN at no moment MUST the page carry neither set's stylesheets
- AND when both are present the new run MUST be later in the document, so it wins

#### Scenario: Leaving and returning to stock
- GIVEN `nextcloud` is on the page (no Thematiq set layers)
- WHEN `amsterdam` is applied and then `nextcloud` again, without navigation
- THEN after the first swap the design-system files and `tokens/amsterdam.css` MUST be on the page,
  inserted before `custom-overrides.css`
- AND after the second swap no Thematiq set layer MUST remain
