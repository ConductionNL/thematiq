<p align="center">
  <img src="img/app-store.svg" alt="NL Design System Theme logo" width="80" height="80">
</p>

# NL Design System Theme for Nextcloud

Apply Dutch government design tokens (NL Design System) to your Nextcloud instance with open-source fonts and components.

## Features

- **47 token sets**: Choose from Dutch government design systems, including:
  - Rijkshuisstijl (Dutch national government)
  - Gemeente Utrecht
  - Gemeente Amsterdam
  - Gemeente Den Haag
  - Gemeente Rotterdam
  - La Suite numérique (Cunningham design system, European sovereign-workplace / MinBZK-mijn-bureau EDIC bundles) — plus the published Cunningham blue base as an optional sibling set
  - …and a broad set of community-maintained municipality and organization brands

- **Open Source Fonts**: Uses **Fira Sans** from `@fontsource/fira-sans` and **Inter** (self-hosted from `@fontsource/inter`) as professional alternatives to proprietary government fonts

- **Easy Configuration**: Select your preferred token set via the admin settings panel

- **CSS Variables**: Uses CSS custom properties for flexible theming that integrates with Nextcloud's existing theme system

- **No Build Required**: Tokens are pre-compiled CSS and fonts are bundled and self-hosted (no external CDN)

- **NL-Government and French-Government Icons**: Includes 1488 SVG icons sourced from `@conduction/nextcloud-vue`'s EUPL-compatible NL-government packs (RVO, OpenGemeenten, Gemeente Den Haag) plus 1038 SVG icons from the French-government DSFR pack (`@gouvfr/dsfr`, Etalab-2.0) — 2526 icons total — plus 23 organization logos, for use across all Nextcloud apps

- **Theme-switchable iconography**: The icon pack an app resolves through nldesign travels with the active design system — a French-government (`lasuite`) theme serves the DSFR pack, a Dutch-government theme serves the RVO/OpenGemeenten/Den Haag packs — resolved via `DesignSystemService` and advertised on the public capability (`iconPacks`). See `img/ICONS.md`.

## Icons

The app includes **2526 icons** across four government icon sets and **25 logos**:

- RVO, OpenGemeenten, Gemeente Den Haag — **1488 icons** (CC0-1.0 / CC0-1.0 / EUPL-1.2), materialized from `@conduction/nextcloud-vue`
- DSFR (Système de Design de l'État) — **1038 icons** (**Etalab-2.0**), materialized from `@gouvfr/dsfr`
- Government and municipal organization logos
- SVG format for scalability
- Accessible via Nextcloud's image path API, or via the theme-switchable icon-pack resolver (active design system's pack)

Icon and logo filenames are a public API: other apps reference them by name (e.g. `icons/rvo/rvo-zoek.svg`, `icons/dsfr/arrow-right-line.svg`), so renames and removals are breaking changes. See the [icon documentation](img/ICONS.md) for the naming-stability and licensing contract. The proprietary City-of-Amsterdam icon set is **not** bundled — see `img/ICONS.md` for the licensing rationale.

**[View Icon Documentation →](img/ICONS.md)**

## Installation

### Method 1: From Git Repository

1. Clone or download this app to your Nextcloud apps directory:
   ```bash
   cd /path/to/nextcloud/apps
   git clone https://github.com/ConductionNL/thematiq.git
   ```

2. Install npm dependencies (for fonts and icons):
   ```bash
   cd thematiq
   npm install
   npm run build
   ```

3. Enable the app in Nextcloud:
   ```bash
   occ app:enable nldesign
   ```

4. Configure the theme in **Settings → Administration → Theming**

### Method 2: Docker Environment

If you're running in the provided Docker environment:

```bash
# From the server directory
docker exec -u 33 nextcloud php occ app:enable nldesign
```

## Configuration

Navigate to **Settings → Administration → Theming** and find the "NL Design System Theme" section.

Select your preferred design token set and reload the page to see the changes.

### Configuring Background Color

The NL Design app does not set a background color - this allows you to use Nextcloud's built-in theming system to configure the background color to match your organization's needs.

**To set the background color:**

1. Navigate to **Settings → Administration → Theming** (Nextcloud's main theming section, not the NL Design section)
2. Scroll to **Background and color** section
3. Click on **Color** and enter your desired background color
4. **Important**: Also click on **Background image** and select **Remove background image** to ensure a solid color background

**Recommended colors by token set:**

| Token Set | Primary Color | Background Color |
|-----------|--------------|------------------|
| **Rijkshuisstijl** | `#154273` (donkerblauw) | `#F5F6F7` (light gray) |
| **Utrecht** | `#CC0000` (red) | `#FFFFFF` (white) |
| **Amsterdam** | `#EC0000` (red) | `#FFFFFF` (white) |
| **Den Haag** | `#1A7A3E` (green) | `#FFFFFF` (white) |
| **Rotterdam** | `#00811F` (green) | `#FFFFFF` (white) |

**Note**: The primary colors are automatically applied by the NL Design app when you select a token set. You only need to configure the background color manually in Nextcloud's theming settings.

**Why does NL Design not set the background color?**

By delegating background color management to Nextcloud's theming system, organizations can:
- Use their own custom background colors
- Easily change backgrounds without modifying app code
- Maintain compatibility with Nextcloud's theming API
- Allow different backgrounds for different user groups or instances

## Fonts

This app uses **Fira Sans** as an open-source alternative to the proprietary government fonts:

- **Source**: `@fontsource/fira-sans` npm package (woff2/woff committed under `css/fonts/`)
- **License**: SIL Open Font License 1.1 (free to use)
- **Weights**: Regular (400) and Bold (700), plus italic variants
- **Delivery**: Bundled and self-hosted — loaded from app-relative paths in `css/fonts.css`, never from an external CDN. Self-hosting keeps the fonts inside Nextcloud's default Content-Security-Policy and works on the air-gapped instances typical of Dutch government.
- **No permission needed**: Unlike RijksoverheidSansWebText, Fira Sans is freely available

### Why Fira Sans?

- Designed by Carrois Apostrophe for readability
- Used by Mozilla and other organizations
- Excellent legibility for government services
- Similar characteristics to official government fonts
- Officially recommended by Rijkshuisstijl Community as open-source alternative

## Marianne Font (La Suite numérique) — restricted, off by default

> ⚠️ **Marianne is the official typeface of the French State, reserved for
> French State administrations ("réservée aux administrations de l'État").**
> This app bundles it self-hosted, but it is **inert until an admin
> explicitly enables it** — enabling it is the operator's affirmation that
> their organisation is a French State agency. Every other instance, and
> every instance with the gate left off (the default), renders **Inter**.

The `lasuite` token set's own configured font stack is `Marianne, Inter,
Roboto Flex Variable, sans-serif` (Cunningham design system). This app:

- **Bundles** the 8 Marianne `woff2` weights (Light, Regular, Medium, Bold +
  italics) from `@gouvfr/dsfr@1.15.1` (the official French government Design
  System) under `css/systems/lasuite/fonts/marianne/`, self-hosted, no
  external request — licensed **Etalab Open Licence 2.0**, see
  [`MARIANNE-LICENCE.md`](MARIANNE-LICENCE.md).
- **Keeps Marianne inert by default.** The real `@font-face Marianne`
  declarations live in a separate stylesheet
  (`css/systems/lasuite/marianne.css`) that only loads when BOTH the
  `lasuite` design system is active AND an admin has ticked *"Our
  organisation is a French State agency (administration de l'État)"* in
  Administration Settings. Until then, no Marianne byte is ever requested
  and the `lasuite` set renders **Inter** (SIL OFL 1.1, also self-hosted).
- **Requires an explicit agreement** to enable — see
  [`AGREEMENT-MARIANNE.md`](AGREEMENT-MARIANNE.md) for the operator user
  agreement that ticking the checkbox constitutes.

This is **not** an unconditionally free/open font: do not enable it unless
your organisation is a French State agency.

## Architecture

```
nldesign/
├── appinfo/
│   ├── info.xml          # App metadata
│   └── routes.php        # API routes
├── css/
│   ├── fonts.css         # Fira Sans font declarations
│   ├── theme.css         # Maps NL Design tokens to Nextcloud variables
│   ├── tokens/           # Token set files per organization
│   │   ├── rijkshuisstijl.css
│   │   ├── utrecht.css
│   │   ├── amsterdam.css
│   │   ├── denhaag.css
│   │   └── rotterdam.css
│   └── admin.css         # Admin settings styles
├── js/
│   └── admin.js          # Admin settings JavaScript
├── lib/
│   ├── AppInfo/
│   │   └── Application.php  # Loads CSS files
│   ├── Controller/
│   │   └── SettingsController.php
│   └── Settings/
│       └── Admin.php
├── templates/
│   └── settings/
│       └── admin.php     # Settings UI
├── package.json          # NPM dependencies
└── node_modules/         # Fonts from npm
    └── @fontsource/fira-sans/
```

## How It Works

### Two-Layer CSS Variable System

1. **Token Layer**: Each organization has a token file (e.g., `rijkshuisstijl.css`) that defines design tokens as CSS variables:
   ```css
   :root {
       --nldesign-color-primary: #154273;
       --nldesign-font-family: 'Fira Sans', sans-serif;
   }
   ```

2. **Mapping Layer**: The `theme.css` file maps these to Nextcloud's CSS variables:
   ```css
   body {
       --color-primary: var(--nldesign-color-primary) !important;
       font-family: var(--nldesign-font-family) !important;
   }
   ```

3. **Font Loading**: The `fonts.css` file loads Fira Sans from bundled, self-hosted files (app-relative `url()`, no external CDN):
   ```css
   @font-face {
       font-family: 'Fira Sans';
       src: local('Fira Sans'),
            url('fonts/fira-sans-latin-400-normal.woff2') format('woff2'),
            url('fonts/fira-sans-latin-400-normal.woff') format('woff');
   }
   ```

### Loading Order

```php
// In Application.php
\OCP\Util::addStyle(self::APP_ID, 'fonts');         // 1. Load Fira Sans
\OCP\Util::addStyle(self::APP_ID, 'tokens/utrecht'); // 2. Load token set
\OCP\Util::addStyle(self::APP_ID, 'theme');         // 3. Map to Nextcloud
```

## Development

### Prerequisites

- Node.js 18+
- npm

### Setup

```bash
cd nldesign
npm install
```

### Updating Fonts

The fonts are bundled and self-hosted (committed under `css/systems/nldesign/fonts/`), so no build step and no network access are required at runtime. To refresh them from the npm package:

```bash
# Fonts are in node_modules/@fontsource/fira-sans/files/
cp node_modules/@fontsource/fira-sans/files/*.woff2 css/fonts/
```

Keep the `url()` references in `css/fonts.css` app-relative — never point them at an external CDN, which Nextcloud's Content-Security-Policy blocks and which fails on air-gapped instances.

### Creating New Token Sets

To add a new municipality or organization:

1. Create a new file in `css/tokens/` (e.g., `tilburg.css`)
2. Define the `--nldesign-*` variables following the existing pattern
3. Add the option to `templates/settings/admin.php`
4. Update `lib/Controller/SettingsController.php` to validate the new option

## NPM Packages Used

### Current Dependencies

- **`@fontsource/fira-sans`** (v5.0.0)
  - Open-source web fonts
  - Self-hosted option for Fira Sans
  - Includes all weights and styles

### Community Packages (Reference)

These packages inspired our token definitions but are not direct dependencies:

- `@rijkshuisstijl-community/design-tokens` - Official Rijkshuisstijl tokens
- `@rijkshuisstijl-community/font` - Font package (includes Fira Sans)
- `@utrecht/design-tokens` - Utrecht municipality tokens

Note: We maintain manual CSS token files for better compatibility with Nextcloud's asset pipeline, but they're based on the official NL Design System specifications.

## Compliance

### Open Source Implementation

✅ **What's Included (Free & Legal)**:
- Fira Sans fonts (SIL OFL 1.1 license)
- Design token values (colors, spacing, etc.)
- CSS mapping to Nextcloud variables
- All municipality color schemes

❌ **What's NOT Included (Requires Permission)**:
- Official Rijkslogo (crown logo)
- RijksoverheidSansWebText proprietary fonts
- Official government imagery

### Legal Usage

This implementation is **fully legal and open-source** for:
- Demonstrations and prototypes
- Educational purposes
- Municipal websites (with their respective themes)
- Any organization using open-source alternatives

**Permission Required** for:
- Official Rijksoverheid organizations using the Rijkslogo
- Using proprietary RijksoverheidSansWebText fonts
- Official government communications

## Resources

### Official Documentation
- [NL Design System](https://nldesignsystem.nl/)
- [Rijkshuisstijl Community](https://github.com/nl-design-system/rijkshuisstijl-community)
- [Utrecht Design System](https://github.com/nl-design-system/utrecht)
- [Rijkshuisstijl Online](https://www.communicatierijk.nl/vakkennis/rijkswebsites/verplichte-richtlijnen/rijkshuisstijl-online)

### Font Resources
- [Fira Sans on Google Fonts](https://fonts.google.com/specimen/Fira+Sans)
- [Fontsource Documentation](https://fontsource.org/fonts/fira-sans)
- [@fontsource/fira-sans on npm](https://www.npmjs.com/package/@fontsource/fira-sans)

### Community
- [NL Design System Community Slack](https://praatmee.codefor.nl/) - Join `#nl-design-system`
- [GitHub Discussions](https://github.com/nl-design-system/rijkshuisstijl-community/discussions)

## License

This project is licensed under the [EUPL-1.2](LICENSE).

### Dependency license policy

All dependencies (PHP and JavaScript) are automatically checked against an approved license allowlist during CI. The following SPDX license families are approved for use in dependencies:

- **Permissive:** MIT, ISC, BSD-2-Clause, BSD-3-Clause, 0BSD, Apache-2.0, Unlicense, CC0-1.0, CC-BY-3.0, CC-BY-4.0, Zlib, BlueOak-1.0.0, Artistic-2.0, BSL-1.0
- **Copyleft (EUPL-compatible):** LGPL-2.0/2.1/3.0, GPL-2.0/3.0, AGPL-3.0, EUPL-1.1/1.2, MPL-2.0
- **Font licenses:** OFL-1.0, OFL-1.1, Etalab-2.0 (Marianne only, see below)

Dependencies with licenses not on this list will fail CI unless explicitly approved in `.license-overrides.json` with a documented justification.

### License exceptions

`@gouvfr/dsfr` (Etalab-2.0, devDependency, build-time only) is the source for **two** distinct, separately-governed assets, both approved via [`.license-overrides.json`](.license-overrides.json) — the Etalab Open Licence 2.0 is a free/open, attribution-only redistribution licence not yet on the general SPDX allowlist above:

- **DSFR icons** (`scripts/build-icons.js` → `img/icons/dsfr/`) — freely redistributable, bundled unconditionally.
- **Marianne typeface** (`scripts/build-fonts-marianne.js` → `css/systems/lasuite/fonts/marianne/*.woff2`, each mapped to `Etalab-2.0`; full text: [`LICENSES/etalab-2.0.txt`](LICENSES/etalab-2.0.txt)) — the FR-State-**restricted** typeface, bundled behind an admin acknowledgement gate ([off by default](#marianne-font-la-suite-numérique--restricted-off-by-default)); see [`MARIANNE-LICENCE.md`](MARIANNE-LICENCE.md) and [`AGREEMENT-MARIANNE.md`](AGREEMENT-MARIANNE.md) for the usage restriction.

`@conduction/nextcloud-vue` (EUPL-1.2, devDependency, build-time icon source only) is already covered by the EUPL-1.1/1.2 allowlist entry above, and the proprietary `@amsterdam/design-system-assets` / `@amsterdam/design-system-react-icons` packages that previously required an exception here have been removed — see [CHANGELOG.md](CHANGELOG.md).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Adding New Token Sets

1. Research the official design system
2. Create a new token file in `css/tokens/`
3. Follow the existing pattern with `--nldesign-*` variables
4. Add to admin UI
5. Test in Nextcloud
6. Submit PR with documentation

## Authors

- [Conduction](https://conduction.nl)

## Changelog

### v0.1.0 (2026-02-03)
- Initial release
- Support for 5 token sets (Rijkshuisstijl, Utrecht, Amsterdam, Den Haag, Rotterdam)
- Fira Sans font integration via @fontsource
- Bundled, self-hosted font loading (no external CDN)
- Full CSS variable mapping
- Admin settings panel
- Background image removal for clean Rijkshuisstijl compliance
- **Amsterdam Design System Icons**: 344 SVG icons integrated from @amsterdam/design-system-assets (MPL-2.0), plus 23 organization logos
