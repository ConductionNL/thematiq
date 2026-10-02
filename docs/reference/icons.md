---
sidebar_position: 7
---

# NL-Government and French-Government Icons Integration

## Overview

The Thematiq app includes **1488 icons** materialized from `@conduction/nextcloud-vue`'s
EUPL-compatible NL-government icon packs (RVO, OpenGemeenten, Gemeente Den Haag), plus
**1038 icons** materialized from `@gouvfr/dsfr`'s French-government DSFR pack
(**Etalab-2.0**) — **2526 icons** total — plus **30 logos**, making them available for use
across all Nextcloud apps.

### Theme-switchable iconography

The icon pack an app should serve travels with the active **design system**, driven by
`design-systems.json`'s optional `icon_pack` field: `nldesign` ->
`["rvo", "open-gemeenten", "den-haag"]`, `lasuite` -> `["dsfr"]`. Resolve the active
pack via `DesignSystemService::resolveActiveIconPacks()` / `resolveIconPath()`, or read it
from the public capability (`capabilities.nldesign.iconPacks`) — see
`openspec/specs/icon-packs/spec.md`. This does **not** replace Nextcloud core's built-in
icons; it only switches Thematiq's own bundled assets served through `imagePath`.

**The proprietary City-of-Amsterdam icon set (`@amsterdam/design-system-assets`) is NOT
bundled.** Its `LICENSE.md` marks the set proprietary to the City of Amsterdam,
restricted to contexts where Amsterdam is the main communicator — Thematiq shipping it to
arbitrary Dutch-government instances was exactly the redistribution its notice forbids.
It was removed; see `CHANGELOG.md` for the full list of removed filenames and their
77 one-release legacy-name aliases.

## Availability and fallbacks

These icon and logo URLs only resolve while the thematiq app is installed **and enabled** on the instance. A consumer app that references `imagePath('thematiq', 'icons/...')` on an instance without thematiq will get a broken image. Consumers MUST either ship a fallback icon or declare a dependency on `thematiq` in their `appinfo/info.xml`.

## Naming stability

Icon and logo filenames are a public API consumed by other apps. Within an installed source pack version, renaming or removing a bundled icon or logo is a **breaking change**: it MUST be recorded in the changelog naming both the old and new filename, and `img/ICONS.md` MUST be regenerated in the same change so the inventory regression test (`tests/Unit/IconAssetsTest.php`) keeps passing. Upgrading a source dependency that can change pack contents — `@conduction/nextcloud-vue` (the `rvo`/`open-gemeenten`/`den-haag` packs) or `@gouvfr/dsfr` (the `dsfr` pack) — MUST be an explicit, reviewed change whose diff of added/removed keys is listed in the changelog — never a silent regenerate.

## Available Icons

View all available icons in the [icon documentation](https://github.com/ConductionNL/thematiq/tree/main/img/ICONS.md) or browse the files in:
- **NL-government icons:** `img/icons/{rvo,open-gemeenten,den-haag}/` (1488 SVG files across 3 sets)
- **DSFR (French-government) icons:** `img/icons/dsfr/` (1038 SVG files)
- **Legacy aliases:** `img/icons/*.svg` (77 one-release compatibility files — see CHANGELOG.md, removed next minor release)
- **Logos:** `img/logos/` (30 SVG files, static checked-in huisstijl assets — not build output)

## Usage in Nextcloud Apps

### Method 1: Direct SVG Reference (Recommended)

```php
<?php
// In your template file
$iconUrl = \OC::$server->getURLGenerator()->imagePath('thematiq', 'icons/den-haag/dh-communication-message.svg');
?>
<img src="<?php p($iconUrl); ?>" alt="Notifications" class="nldesign-icon" />
```

### Method 2: Background Image in CSS

```css
.my-icon {
    background-image: url('../../../thematiq/img/icons/den-haag/dh-functional-search.svg');
    background-size: contain;
    background-repeat: no-repeat;
    width: 24px;
    height: 24px;
}
```

### Method 3: Inline SVG (For Dynamic Styling)

```php
<?php
// Read and output SVG content directly
$iconPath = \OC::$SERVERROOT . '/apps/thematiq/img/icons/den-haag/dh-communication-message.svg';
if (file_exists($iconPath)) {
    echo file_get_contents($iconPath);
}
?>
```

### Vue-based consumers

Vue apps SHOULD import the packs from `@conduction/nextcloud-vue` directly rather than
going through the PHP image-path contract:

```js
import { rvoIcons } from '@conduction/nextcloud-vue/src/icons/rvo.js'
// <CnIconBrowser :url-icons="rvoIcons" … />
```

## Icon Sets

| Set | Directory | Upstream | Licence | Count |
| --- | --- | --- | --- | --- |
| RVO / ROOS | `img/icons/rvo/` | Rijksdienst voor Ondernemend Nederland | CC0-1.0 | 1163 |
| OpenGemeenten | `img/icons/open-gemeenten/` | OpenGemeenten Iconenset ("Line" style) | CC0-1.0 | 256 |
| Gemeente Den Haag | `img/icons/den-haag/` | Gemeente Den Haag icon set | EUPL-1.2 | 69 |
| DSFR | `img/icons/dsfr/` | Système de Design de l'État (`@gouvfr/dsfr`) | Etalab-2.0 | 1038 |

Filenames are the pack entry keys (kebab-case slugs already carrying a short set prefix,
e.g. `rvo-zoek`, `dh-functional-search`, `og-zoeken`) — confirm the exact filename in
`img/icons/{set}/` or `img/ICONS.md` before referencing it.

### Legacy Amsterdam filename aliases (one release only)

For exactly this release, 77 of the 344 removed Amsterdam filenames resolve at their old
top-level path (`img/icons/{Name}.svg`) to a byte-identical copy of a replacement icon
from the sets above — e.g. `Search.svg`, `Star.svg`, `House.svg`, `Mail.svg`. See
`img/ICONS.md` for the full alias table. **These aliases are removed in the next minor
release** — do not build new integrations against them; migrate to the set-prefixed path.

## Logos

The 30 logos in `img/logos/` cover government and municipal organizations, plus four fictional example sets. A representative sample:
- `amsterdam.svg` - City of Amsterdam logo
- `ggd-amsterdam.svg` - GGD Amsterdam logo
- `stadsarchief.svg` - Amsterdam City Archives
- `stadsbank-van-lening.svg` - Stadsbank van Lening
- `museum_weesp.svg` - Museum Weesp
- `vga-verzekeringen.svg` - VGA Verzekeringen
- `rijkshuisstijl.svg`, `vng.svg`, `provincie-zuid-holland.svg`, and other municipal logos (Utrecht, Rotterdam, Leiden, Nijmegen, Tilburg, Hoorn, Epe, and more)

Browse `img/logos/` for the complete set. These are static, checked-in huisstijl assets
tied to `token-sets.json` `theming.logo` entries — `scripts/build-icons.js` never
creates, modifies, or deletes anything under `img/logos/`.

## Styling Icons

Icons are designed to work with the NL Design System color tokens:

```css
.nldesign-icon {
    width: 24px;
    height: 24px;
    /* Apply NL Design colors */
    filter: invert(var(--nldesign-icon-invert, 0));
}

/* For dark backgrounds */
.nldesign-icon--light {
    filter: invert(1);
}
```

## Examples

### Button with Icon
```php
<button class="button-vue--vue-primary">
    <img src="<?php p(\OC::$server->getURLGenerator()->imagePath('thematiq', 'icons/rvo/rvo-plus.svg')); ?>"
         alt="" class="button-icon" />
    Toevoegen
</button>
```

### Menu Item with Icon
```php
<li>
    <a href="/path">
        <img src="<?php p(\OC::$server->getURLGenerator()->imagePath('thematiq', 'icons/den-haag/dh-objects-house.svg')); ?>"
             alt="Home icon" />
        Home
    </a>
</li>
```

### Status Indicator
```php
<div class="status-indicator">
    <img src="<?php p(\OC::$server->getURLGenerator()->imagePath('thematiq', 'icons/den-haag/dh-informational-checkcircle.svg')); ?>"
         alt="Success" class="status-icon status-icon--success" />
    <span>Voltooid</span>
</div>
```

## Browser Compatibility

All icons are SVG format and support:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11+ (with appropriate polyfills)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Accessibility

When using icons, always provide appropriate alt text or aria-labels:

```php
<!-- Decorative icon (no alt needed) -->
<img src="<?php p($iconUrl); ?>" alt="" aria-hidden="true" />

<!-- Functional icon (provide alt text) -->
<img src="<?php p($iconUrl); ?>" alt="Search" />

<!-- Icon button (use aria-label) -->
<button aria-label="Close dialog">
    <img src="<?php p($iconUrl); ?>" alt="" />
</button>
```

## License

Icons are sourced from `@conduction/nextcloud-vue` and `@gouvfr/dsfr`:
- **Package:** `@conduction/nextcloud-vue` (devDependency, build-time only)
- **Canonical licence record:** `src/icons/ATTRIBUTION.md` (https://github.com/ConductionNL/nextcloud-vue/tree/main/src/icons/ATTRIBUTION.md)
- **Licences:** RVO CC0-1.0, OpenGemeenten CC0-1.0, Gemeente Den Haag EUPL-1.2
- **Package:** `@gouvfr/dsfr` (devDependency, build-time only) — Système de Design de
  l'État, licensed under the Etalab Open Licence 2.0
  (https://github.com/etalab/licence-ouverte/blob/master/LO.md). Icons only: the
  Marianne typeface files shipped in the same package are FR-state-restricted and are
  never bundled by `scripts/build-icons.js`.

The proprietary `@amsterdam/design-system-assets` package is **not** a dependency of this
app and no shipped asset derives from it.

## Resources

- **nc-vue icon attribution:** https://github.com/ConductionNL/nextcloud-vue/tree/main/src/icons/ATTRIBUTION.md
- **RVO icon source:** https://github.com/nl-design-system/rvo
- **OpenGemeenten icon source:** https://github.com/OpenGemeenten/Iconenset
- **Gemeente Den Haag icon source:** https://github.com/nl-design-system/denhaag

## Building Icons

Icons are automatically built from `@conduction/nextcloud-vue`'s and `@gouvfr/dsfr`'s bundled icon packs. To rebuild:

```bash
npm run build:icons
```

This decodes the data-URI icon packs at `node_modules/@conduction/nextcloud-vue/src/icons/{rvo,openGemeenten,denHaag}.js` into standalone SVG files under `img/icons/{set}/`, copies every DSFR source SVG (`@gouvfr/dsfr/dist/icons/**/*.svg`, falling back to the pre-fetched `.dsfr-src/icons/` scratch source when the package cannot be installed) into `img/icons/dsfr/{basename}.svg`, materializes the one-release legacy aliases from `scripts/icon-aliases.json`, and regenerates `img/ICONS.md`. It never touches `img/logos/`.

## Theme-Switchable Icon Packs

Beyond the fixed `imagePath('thematiq', 'icons/{set}/{key}.svg')` contract above, the icon
pack an app should serve can also travel with the **active design system**:

- `design-systems.json` carries an optional `icon_pack` field per design system
  (string or ordered array): `nldesign` -> `["rvo", "open-gemeenten", "den-haag"]`,
  `lasuite` -> `["dsfr"]`. A design system without the field serves no pack (Nextcloud
  stock icons).
- `DesignSystemService::resolveActiveIconPacks(tokenSetId)` resolves the chain `active
  token set -> its design_system -> that design system's icon_pack`, honoring an
  appconfig `icon_pack` admin override (`occ config:app:set thematiq icon_pack
  --value=dsfr`) when it names a real pack directory.
- `DesignSystemService::resolveIconPath($name, $tokenSetId)` returns the
  `imagePath`-relative path of `$name` in the first pack of the resolved list that
  contains it, or `null`.
- The resolved list is advertised on the public capability:
  `/ocs/v2.php/cloud/capabilities` -> `capabilities.nldesign.iconPacks`.

See `openspec/specs/icon-packs/spec.md` for the full contract. **Honest limitation:**
this only switches Thematiq's own bundled icon assets — it does not force-replace
Nextcloud core's built-in icon set beyond what the active theme's CSS already restyles.
