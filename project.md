# NL Design — Dutch Government Theming for Nextcloud

## Overview

NL Design is a Nextcloud theming app that applies Dutch government design standards (NL Design System) to the Nextcloud interface. It provides 57 token sets covering national government (Rijkshuisstijl), municipalities, organizations, and the European sovereign-workplace La Suite numérique (Cunningham) design system, plus its published blue base as an optional sibling. Admins select a token set via a settings panel, and the app injects a design-system-driven CSS architecture that overrides Nextcloud's default styling with the selected organization's brand identity.

## Architecture

- **Type**: Nextcloud App (PHP backend + vanilla JS admin panel)
- **Data layer**: Nextcloud IConfig (key-value, no own database tables)
- **Pattern**: CSS injection — reads config on boot, loads CSS files via `\OCP\Util::addStyle()`
- **License**: EUPL-1.2 (app), SIL OFL 1.1 (Fira Sans font)
- **No Vue/webpack build**: Admin UI is vanilla PHP template + vanilla JS

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.1+, Nextcloud App Framework |
| Admin UI | Vanilla PHP template + vanilla JS (13.5 KB) |
| CSS | Pre-compiled token CSS, 7-layer cascade |
| Font | Fira Sans (open-source alternative to RijksoverheidSansWebText) |
| Token source | NL Design System themes repo (upstream sync) |

## 7-Layer CSS Architecture

| Order | File | Purpose |
|-------|------|---------|
| 1 | `css/fonts.css` | Fira Sans @font-face declarations |
| 2 | `css/defaults.css` | All `--nldesign-*` tokens with Rijkshuisstijl defaults |
| 3 | `css/tokens/{org}.css` | Organization-specific token overrides |
| 4 | `css/utrecht-bridge.css` | Maps `--utrecht-*` to `--nldesign-component-*` |
| 5 | `css/theme.css` | Maps `--nldesign-*` to Nextcloud element selectors |
| 6 | `css/overrides.css` | Maps Nextcloud `--color-*` vars to `--nldesign-*` |
| 7 | `css/element-overrides.css` | Low-level element styling (fonts, containers) |

## Token Sets (57)

- **National**: Rijkshuisstijl (default)
- **Major cities**: Amsterdam, Utrecht, Rotterdam, Den Haag
- **Municipalities**: 33 others (Haarlem, Leiden, Nijmegen, Tilburg, etc.)
- **Organizations**: Demodam, Duo, Noaberkracht, VNG, xxllnc
- **Provinces**: Zuid-Holland
- **Fictional examples**: (EXAMPLE) Basisschool, (EXAMPLE) Voortgezet onderwijs, (EXAMPLE) College, (EXAMPLE) Opleider, (EXAMPLE) Gemeente, for demos and example data
- **European sovereign-workplace**: La Suite numérique (Cunningham design system, MinBZK/mijn-bureau EDIC bundles) — deployed violet brand
- **Published design system**: Cunningham (blue base) — the same Cunningham foundation as La Suite, without the deployed violet override

## Features

### Implemented

| Feature | Description | Status |
|---------|-------------|--------|
| Token Set Selection | Admin selects from 41 org token sets with live preview | Done |
| 7-Layer CSS | Cascading token system with defaults for incomplete sets | Done |
| Theming Sync | Optionally sync Nextcloud primary color, background, logo | Done |
| Hide Slogan | Toggle to hide login page slogan | Done |
| Show Menu Labels | Toggle to show text labels instead of icons in app menu | Done |
| Fira Sans Font | Open-source government-style font bundled with app | Done |
| Utrecht Bridge | Maps NL Design System component tokens to app tokens | Done |
| Token Generation | Script to generate CSS from upstream NL Design themes | Done |

## Configuration (IConfig)

| Key | Default | Description |
|-----|---------|-------------|
| `nldesign:token_set` | `rijkshuisstijl` | Active token set ID |
| `nldesign:hide_slogan` | `0` | Hide login page slogan (0/1) |
| `nldesign:show_menu_labels` | `0` | Show text labels in app menu (0/1) |

## Key Directories

```
nldesign/
├── appinfo/          # App manifest and routes
├── lib/              # PHP backend (controllers, services, settings)
├── css/              # 7-layer CSS + 42 token files
│   └── tokens/       # Organization-specific CSS token files
├── js/               # Vanilla admin JS
├── img/              # SVG logos (17 organizations)
├── templates/        # Admin settings PHP template
├── scripts/          # Token generation, font/icon build scripts
├── docs/             # Token docs, compliance, mappings
├── openspec/         # OpenSpec specs
└── token-sets.json   # Token set manifest with metadata
```

## Development

- **Local URL**: http://localhost:8080/settings/admin/theming (admin settings)
- **Docker**: Part of openregister/docker-compose.yml
- **Enable**: `php occ app:enable nldesign`
- **Token regeneration**: `npm run generate:tokens` (requires upstream themes repo)
