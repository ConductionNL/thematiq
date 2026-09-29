# Theme gallery: install a house style that someone else already built

## Why

A municipality that is not in the shipped catalogue has two options today: build its own token set, or ask Conduction to ship one in a release. Many organisations already publish their tokens through the NL Design System, and suppliers build sets for their customers. There is no place where an administrator can browse those sets and install one.

Ready-made house styles are the core of thematiq (the catalogue area). Liferay has a marketplace of themes.

#### Row `cat-marketplace` (thematiq matrix, area catalogue)

- Capability: Download additional themes from a marketplace or gallery maintained by the vendor or community.
- Own rating: no; built.state `none`. Built evidence: grep -rniF 'marketplace|gallery' across lib/, js/, docs/GOVERNMENT-FEATURES.md, openspec/specs found nothing; the only additive mechanism is admin-side upload of the admin's own file (customTokenSet#upload, /settings/tokensets/upload), not browsing/downloading themes from a vendor or community-run catalogue
- Liferay DXP (style books, themes, client extensions) rated `yes`: https://learn.liferay.com/w/dxp/getting-started/changing-your-sites-appearance : "There are many themes available on the Liferay Marketplace"; https://marketplace.liferay.com/p/materialized-theme is one such listing
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `no`, openDesk theming `no`, Tokens Studio (Figma plugin and platform) `no`

## What changes

- An opt-in gallery: an index of community and supplier token sets, read from a URL an administrator can point at an internal mirror.
- A "Gallery" block in Settings > Administration > Theming lists the sets with name, organisation, swatches, licence, source and the contrast result.
- Installing a set downloads its file, checks it against the checksum in the index, and stores it through the same conversion and whitelist as an upload, as a custom token set that remembers where it came from.
- Nothing is downloaded or installed without an administrator choosing it. The gallery is off by default and its label names the host it contacts.
- A contribution guide: how a supplier or municipality gets a set into the default index.

## Capabilities

### New capabilities

- `theme-gallery`: the index, browsing, installing, provenance.

### Modified capabilities

- `upstream-freshness`: the settings hint no longer says the freshness check is the only outbound request.

## Impact

- New `lib/Service/ThemeGalleryService.php` (index fetch through `IClientService`, same bounded, opt-in pattern as `UpstreamFreshnessService`), new `lib/Controller/GalleryController.php`.
- `lib/Service/CustomTokenSetService.php` `store()` (`:191`) gains provenance fields in the manifest entry.
- `templates/settings/admin.php` and `js/admin.js`: the Gallery block and its opt-in toggle; the upstream-freshness hint (`:532-535`) is reworded.
- New `gallery/` directory in this repository holding the default index and the contribution guide.

## Rows

- `cat-marketplace` (thematiq matrix).
