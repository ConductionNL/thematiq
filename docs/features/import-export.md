---
sidebar_position: 4.3
---

# Import & Export

The **Download** and **Upload** buttons in the Custom Token Overrides header let you export your current token overrides as a CSS file and import overrides from an existing CSS file.

![Custom Token Overrides section header showing the Download button and Upload button](../img/import-export-buttons.png)

## Download (Export)

Click **Download** to download your current custom token overrides as a CSS file named `custom-overrides.css`.

The exported file contains a `:root {}` block with only the tokens you have explicitly overridden — tokens that match the token set's defaults are not included.

**Example exported file:**

```css
:root {
  --color-primary: #c00000;
  --color-primary-hover: #a00000;
}
```

If you have no overrides, the downloaded file contains an empty `:root {}` block.

**Use cases:**
- Back up your customizations before switching token sets
- Transfer overrides from one Nextcloud instance to another
- Version-control your organization's token customizations
- Share a theme configuration with colleagues

## Upload (Import)

Click **Upload** to import token overrides from a CSS file. The app parses the `:root {}` block from the uploaded file and applies matching tokens.

**What happens during import:**

1. The CSS file is parsed to extract all CSS custom property declarations from `:root {}`
2. Each token is checked against the list of editable tokens
3. **Known tokens** are imported and applied as custom overrides
4. **Unknown tokens** (not in the editable token list) are silently skipped
5. **Excluded tokens** (read-only system tokens like `--color-main-background`) are rejected with an error

After a successful import, the page shows a brief summary:

```json
{ "status": "ok", "imported": 2, "skipped": 1 }
```

The imported values are applied immediately as a live preview. Click **Save overrides** to persist them.

**Use cases:**
- Restore a previously downloaded backup
- Import a token configuration from another instance
- Apply a pre-built token theme from a design team

## Supported CSS Format

The import accepts standard CSS files with a `:root {}` block:

```css
:root {
  --color-primary: #005A9C;
  --color-primary-hover: #004080;
  --font-face: 'Fira Sans', sans-serif;
}
```

- Each line must be in the format `--property-name: value;`
- Multiple values per line are not supported
- Only CSS custom properties (starting with `--`) are parsed
- Comments and other CSS rules outside `:root {}` are ignored

## Editable vs. Excluded Tokens

Import and export cover every token the token editor shows. A few Nextcloud variables stay out of reach:

- image variables such as `--image-logo`, which carry a picture, not a design value;
- variables Nextcloud's own JavaScript writes on every render, which a stylesheet value cannot hold.

The [variable inventory](../reference/variable-inventory.md) lists each one with its reason.

Attempting to import an excluded token via the API returns an HTTP 400 error. During file upload, excluded tokens are counted as skipped.

## Command Line Alternative

Your overrides are kept in Nextcloud's app data. To read them on the server:

```bash
cat "$(occ config:system:get datadirectory)/appdata_$(occ config:system:get instanceid)/thematiq/css/custom-overrides.css"
```

Do not edit that file by hand. Nextcloud keeps a file cache for app data, and it does not see a change made outside it. Reset or replace your overrides on the admin page, or through the REST API.

Or via the REST API:

```bash
# Get current overrides
curl -u admin:password http://nextcloud.example.com/apps/thematiq/api/settings/overrides

# Set a specific override
curl -u admin:password -X POST \
  -H 'Content-Type: application/json' \
  -d '{"overrides": {"--color-primary": "#005A9C"}}' \
  http://nextcloud.example.com/apps/thematiq/api/settings/overrides
```
