# Add a house style to the theme gallery

The gallery lists token sets that municipalities, provinces and suppliers built, so another organisation can install one with a click. Nextcloud administrators see it under Settings > Administration > Theming, once they turn it on.

## What an entry needs

Open a pull request against `development` that adds your file and one entry to `gallery/index.json`:

```json
{
	"id": "provincie-utrecht",
	"name": "Provincie Utrecht",
	"organisation": "Provincie Utrecht",
	"description": "The house style of the province, from its NL Design System theme.",
	"licence": "EUPL-1.2",
	"sourceUrl": "https://github.com/your-organisation/your-tokens",
	"fileUrl": "https://raw.githubusercontent.com/your-organisation/your-tokens/v1.0.0/dist/theme.css",
	"sha256": "<sha256sum of the file at fileUrl>",
	"format": "css",
	"swatches": { "primary": "#cc0000", "background": "#ffffff", "text": "#1a1a1a" },
	"contrast": { "pass": 42, "fail": 0 },
	"addedOn": "2026-09-30"
}
```

- `id`: lowercase letters, digits and single hyphens. It never changes: installed copies are matched on it.
- `licence`: an SPDX expression that allows redistribution. An entry without one is not listed.
- `fileUrl`: a fixed version of the file, over https. Point it at a tag or a release, not at a branch, because the checksum must keep matching.
- `sha256`: the checksum of that exact file. An administrator's install refuses a file that does not match.
- `format`: `css` (a built theme or a `:root` token set), `dtcg` (a W3C Design Tokens document) or `design-tokens-css`.
- `swatches`: the primary, background and text colour, shown next to the name.
- `contrast`: the result of `npm run audit:token-sets` for the set.

## What we check before merging

1. The file converts: upload it under Custom token sets on a test instance and read the conversion report.
2. The contrast audit passes, or its failures are named in `contrast`.
3. The licence allows others to use and share the file.
4. `tests/Unit/Gallery/GalleryIndexSchemaTest.php` passes, so the index still matches `index.schema.json`.

## Updating an entry

Publish the new file at a new fixed URL and change `fileUrl` and `sha256`. Administrators who installed your set see that an update is available. Nothing changes on their instance until they choose update.

## Running a mirror

An organisation behind an egress filter copies `index.json` and the files it lists to an internal host, points each `fileUrl` there, and sets the index URL:

```bash
occ config:app:set thematiq gallery_index_url --value="https://intranet.example.nl/thematiq/index.json"
```
