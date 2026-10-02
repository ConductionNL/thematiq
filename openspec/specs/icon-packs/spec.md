# icon-packs Specification

## Purpose
TBD - created by archiving change theme-switchable-iconography. Update Purpose after archive.
## Requirements
### Requirement: Icon Pack Declared on the Design System

The app MUST support an optional `icon_pack` field on each design system in `design-systems.json`
naming the icon directory (or directories) under `img/icons/<pack>/` that the design system serves.
The field MUST accept either a single pack directory name (a string) or an ordered list of pack
directory names (a JSON array); a string MUST be interpreted as a one-element list. A design system that omits
`icon_pack` serves **no pack** — consumers fall back to Nextcloud's stock icons and the icon switch
is a no-op for that design system. The shipped mapping MUST be: `nldesign` →
`["rvo", "open-gemeenten", "den-haag"]` (the existing Dutch-government packs), `lasuite` → `"dsfr"`
(the French-government pack), and `none` / `summer-breeze` / `high-contrast` → no `icon_pack`.

#### Scenario: NL design system declares the Dutch packs
@e2e exclude manifest-model — verified by DesignSystemServiceTest against design-systems.json
- GIVEN the `nldesign` entry in `design-systems.json`
- WHEN its `icon_pack` field is read
- THEN it MUST resolve to the ordered list `["rvo", "open-gemeenten", "den-haag"]`
- AND every named directory MUST exist under `img/icons/`

#### Scenario: La Suite design system declares the French pack
@e2e exclude manifest-model — verified by DesignSystemServiceTest against design-systems.json
- GIVEN the `lasuite` entry in `design-systems.json`
- WHEN its `icon_pack` field is read
- THEN it MUST resolve to the single-element list `["dsfr"]`
- AND `img/icons/dsfr/` MUST exist

#### Scenario: A design system without an icon pack serves NC stock icons
@e2e exclude manifest-model — verified by DesignSystemServiceTest
- GIVEN a design system entry with no `icon_pack` field (e.g. `none`, `summer-breeze`, `high-contrast`)
- WHEN its icon pack is resolved
- THEN the resolved pack list MUST be empty
- AND nldesign MUST serve no icon pack for that design system (Nextcloud stock icons apply)

### Requirement: Active Icon Pack Resolution

The app MUST resolve the active icon pack from the active token set by the chain
`active token set → its design_system → that design system's icon_pack`, with an admin override
taking precedence. The resolution precedence MUST be, highest first: (1) the appconfig `icon_pack`
value when it is set to a directory that exists under `img/icons/`; (2) the active token set's own
`icon_pack` field, if present (a per-set override — reserved, honored when present); (3) the
`icon_pack` of the design system named by the token set's `design_system`; (4) an empty list (no
pack). Resolution MUST be safe: an unknown design system, an unknown token set, or an `icon_pack`
naming a non-existent directory MUST degrade to an empty list and MUST NOT throw. Which token set is
"active" MUST follow the caller's context — the public capability uses the instance appconfig
`token_set` (as `theming-capability` already does for `designSystem`); render-time callers use
`GroupThemingService::resolveTokenSetForRequest()` (preview → group → instance default).

#### Scenario: Active French theme resolves the DSFR pack
@e2e exclude resolver logic — PHPUnit on DesignSystemService::resolveActiveIconPacks
- GIVEN the active token set has `design_system: "lasuite"`
- AND no appconfig `icon_pack` override is set
- WHEN `resolveActiveIconPacks()` is called for that token set
- THEN it MUST return `["dsfr"]`

#### Scenario: Active Dutch theme resolves the Dutch packs
@e2e exclude resolver logic — PHPUnit on DesignSystemService::resolveActiveIconPacks
- GIVEN the active token set resolves to `design_system: "nldesign"` (e.g. `utrecht`, `denhaag`, `rijkshuisstijl`)
- AND no appconfig `icon_pack` override is set
- WHEN `resolveActiveIconPacks()` is called
- THEN it MUST return `["rvo", "open-gemeenten", "den-haag"]`

#### Scenario: Admin override wins over the design system default
@e2e exclude resolver logic — PHPUnit on DesignSystemService::resolveActiveIconPacks
- GIVEN the appconfig `icon_pack` for app `nldesign` is set to `dsfr`
- AND `img/icons/dsfr/` exists
- WHEN `resolveActiveIconPacks()` is called for ANY active token set
- THEN it MUST return `["dsfr"]` regardless of the token set's design system
- AND an override naming a non-existent directory MUST be ignored (fall through to the design-system default)

#### Scenario: Unknown or pack-less theme resolves to no pack
@e2e exclude resolver safety — PHPUnit on DesignSystemService::resolveActiveIconPacks
- GIVEN the active token set has `design_system: "none"` OR references a design system absent from `design-systems.json`
- AND no valid appconfig `icon_pack` override is set
- WHEN `resolveActiveIconPacks()` is called
- THEN it MUST return an empty list
- AND it MUST NOT throw

### Requirement: Icon Path Resolver by Name

The app MUST expose a resolver that, given an icon name and the active token set, returns the
`imagePath`-relative path of that icon in the active pack, so the theme and other apps can request
"the active pack's icon by name". `DesignSystemService::resolveIconPath(name, tokenSetId)` MUST
return `icons/<pack>/<name>.svg` for the first pack in `resolveActiveIconPacks(tokenSetId)` whose
`img/icons/<pack>/<name>.svg` exists on disk, and `null` when no active pack contains the name (or
no pack is active). The returned value MUST be consumable directly by
`IURLGenerator::imagePath('nldesign', <returned path>)`. The resolver MUST reject a name containing a
path separator (`/`, `\`) or `..` by returning `null` (no path traversal outside `img/icons/`).

The resolver MUST be reachable by other apps and by the theme's CSS through the public route
`GET /apps/thematiq/icons/{name}` (`IconController::show()`, `#[PublicPage]` + `#[NoCSRFRequired]`,
like the font routes, because an `<img>` or CSS `url()` load carries no CSRF token). The route MUST
resolve the token set the way the theme does for that request
(`GroupThemingService::resolveTokenSetForRequest()`: preview, then group mapping, then the instance
default), redirect to `imagePath()` of the resolved path, and answer 404 when the resolver returns
`null`.

#### Scenario: The route serves the active pack's icon
@e2e exclude route wiring, covered by PHPUnit IconControllerTest which drives the real DesignSystemService behind the controller
- GIVEN the request's token set resolves to the `lasuite` design system (pack `dsfr`)
- AND `img/icons/dsfr/home.svg` exists
- WHEN `GET /apps/thematiq/icons/home` is requested
- THEN it MUST redirect to the `imagePath` of `icons/dsfr/home.svg`
- AND the same request on an `nldesign` token set MUST redirect to the `rvo` pack's `home.svg`
- AND a name no active pack has MUST answer 404

#### Scenario: Name resolves within the active pack
@e2e exclude resolver logic — PHPUnit on DesignSystemService::resolveIconPath
- GIVEN the active theme resolves to the `["dsfr"]` pack
- AND `img/icons/dsfr/arrow-right-line.svg` exists
- WHEN `resolveIconPath('arrow-right-line', tokenSetId)` is called
- THEN it MUST return `icons/dsfr/arrow-right-line.svg`
- AND `imagePath('nldesign', 'icons/dsfr/arrow-right-line.svg')` MUST serve that file

#### Scenario: First matching pack wins across an ordered list
@e2e exclude resolver logic — PHPUnit on DesignSystemService::resolveIconPath
- GIVEN the active theme resolves to `["rvo", "open-gemeenten", "den-haag"]`
- AND the name exists in more than one of those packs
- WHEN `resolveIconPath(name, tokenSetId)` is called
- THEN it MUST return the path in the FIRST pack (in declared order) that contains the name

#### Scenario: Missing name and traversal input return null
@e2e exclude resolver safety — PHPUnit on DesignSystemService::resolveIconPath
- GIVEN an active pack that does not contain `does-not-exist`
- WHEN `resolveIconPath('does-not-exist', tokenSetId)` is called
- THEN it MUST return `null`
- AND `resolveIconPath('../../secret', tokenSetId)` MUST return `null` without touching the filesystem outside `img/icons/`

### Requirement: Active Icon Pack Advertised on the Public Capability

The `nldesign` public capability (`theming-capability`) MUST advertise the resolved active icon
pack under an `iconPacks` key so unauthenticated clients and other apps can read which pack the
active theme serves. The value MUST be the ordered list returned by `resolveActiveIconPacks()` for
the instance's active token set (appconfig `token_set`) — an empty JSON array when no pack is
active. The capability MUST NEVER throw on account of this key: the minimal degrade payload MUST set
`iconPacks` to an empty array.

#### Scenario: Capability advertises the active pack
@e2e exclude OCS capability — server-side capabilities document, verified by curl + CapabilitiesTest
- GIVEN the instance active token set resolves to `design_system: "lasuite"`
- WHEN `/ocs/v2.php/cloud/capabilities` is read
- THEN `capabilities.nldesign.iconPacks` MUST equal `["dsfr"]`
- AND for an `nldesign`-active instance it MUST equal `["rvo", "open-gemeenten", "den-haag"]`

#### Scenario: Degrade payload keeps the key
@e2e exclude capability degrade — PHPUnit on Capabilities::minimalPayload
- GIVEN `Capabilities::buildPayload()` throws for any reason
- WHEN the minimal payload is returned
- THEN it MUST include `iconPacks` as an empty array
- AND the capabilities document MUST remain valid for every client

### Requirement: DSFR French-Government Pack Materialized at Build Time

The French-government icon pack MUST be materialized at build time from `@gouvfr/dsfr@1.15.1`
(`dist/icons/**/*.svg`) into `img/icons/dsfr/{name}.svg`, where `{name}` is the SVG file's basename
without extension (DSFR icon names are unique across the whole set — referenced globally as
`.fr-icon-<name>` — so a flat, category-free layout is collision-free). `@gouvfr/dsfr` MUST be a
devDependency consumed only by `scripts/build-icons.js`; no runtime PHP or JS may depend on it. The
build MUST fail (non-zero exit) if the DSFR source yields zero icons or if two source files share a
basename. `img/icons/dsfr/` MUST be self-cleaning build output (stale files from earlier builds MUST
NOT survive), exactly like the Dutch pack directories, and MUST NOT touch `img/logos/`. The Etalab
Open Licence 2.0 (`etalab-2.0`) and upstream attribution MUST travel with the assets in
`img/ICONS.md`, alongside the CC0/EUPL rows for the Dutch packs. This change materializes **icons
only** from `@gouvfr/dsfr`; the Marianne font files in the same package are FR-state-restricted and
MUST NOT be bundled by this change.

#### Scenario: DSFR icons materialized to the dsfr pack directory
@e2e exclude build tooling — verified by running npm run build:icons and by IconAssetsTest
- GIVEN `@gouvfr/dsfr` is installed and `npm run build:icons` runs
- WHEN the script processes the DSFR `dist/icons` tree
- THEN every DSFR source SVG MUST exist as `img/icons/dsfr/{basename}.svg`
- AND the `dsfr` file count on disk MUST equal the number of source SVGs
- AND the build MUST fail if the source yields zero icons or a duplicate basename

#### Scenario: DSFR attribution travels with the assets
@e2e exclude licensing artifact — PHPUnit asserts the attribution strings in img/ICONS.md
- GIVEN the regenerated `img/ICONS.md`
- WHEN it is inspected
- THEN it MUST attribute the `dsfr` set to `@gouvfr/dsfr` (Système de Design de l'État) with licence `Etalab-2.0`
- AND no Marianne font file MUST be introduced by this change

#### Scenario: DSFR pack is build output, logos untouched
@e2e exclude build tooling — script-level check plus git status inspection
- GIVEN a previous build left files in `img/icons/dsfr/`
- WHEN `npm run build:icons` runs again
- THEN `img/icons/dsfr/` MUST contain exactly the current DSFR source basenames — stale files MUST NOT survive
- AND no file under `img/logos/` MUST be created, modified, or deleted

### Requirement: Read-Only Admin Indicator for the Active Icon Pack

The nldesign admin settings panel MUST surface, read-only, which icon pack the active theme uses and
where it comes from. The indicator MUST show the resolved ordered pack list (or "Nextcloud stock
icons" when no pack is active) and its source — the active design system, or an admin override when
the appconfig `icon_pack` override is set. This change MUST NOT add a write control for the override
(occ/appconfig only); keeping the pack tied to the design system is the recommended, coherent
default. The indicator MUST state the honest limitation that this switches nldesign's bundled icon
assets served through `imagePath`, and does NOT force-replace Nextcloud's built-in core icons beyond
what the active theme's CSS already restyles.

#### Scenario: Indicator shows the design-system pack
- GIVEN the active theme resolves to the `nldesign` design system with no appconfig override
- WHEN an admin opens the nldesign settings page
- THEN a read-only indicator MUST show the pack list `rvo, open-gemeenten, den-haag`
- AND it MUST show the source as the design system (not an override)
- AND it MUST show the core-icons limitation note

#### Scenario: Indicator shows an active admin override
- GIVEN the appconfig `icon_pack` override is set to `dsfr`
- WHEN an admin opens the nldesign settings page
- THEN the indicator MUST show `dsfr`
- AND it MUST label the source as an admin override

### Requirement: Backwards Compatibility of Existing Icon Consumers

Introducing icon packs MUST NOT break any existing icon consumer. The existing
`img/icons/{rvo,open-gemeenten,den-haag}/` files, the legacy Amsterdam top-level aliases, and every
`imagePath('nldesign', 'icons/{set}/{key}.svg')` URL MUST keep resolving byte-for-byte. The
`nldesign` design system's default `icon_pack` MUST map to the existing Dutch packs so every current
NL theme resolves to the packs it serves today. Adding the DSFR pack MUST be additive — no existing
icon key is renamed or removed by this change.

#### Scenario: Existing Dutch icon URLs are unaffected
@e2e exclude static asset contract — curl against the dev container + IconAssetsTest
- GIVEN the change is applied and icons rebuilt
- WHEN `imagePath('nldesign', 'icons/rvo/rvo-home.svg')` is requested
- THEN the server MUST return HTTP 200 with the same SVG as before this change
- AND the legacy alias URLs and the Dutch pack inventories MUST be unchanged

#### Scenario: Adding the DSFR pack is additive
@e2e exclude inventory diff — enforced in review, backed by IconAssetsTest
- GIVEN the DSFR pack is materialized
- WHEN the icon inventory is compared to the pre-change state
- THEN the only difference MUST be the added `dsfr` pack (plus the new `icon_pack` manifest fields)
- AND no `rvo`/`open-gemeenten`/`den-haag` key MUST be renamed or removed

