---
sidebar_position: 3
---

# Custom Token Sets (eigen huisstijl)

Beyond the 40+ bundled government token sets, an administrator can upload their
**own house style** as a token set. This closes government-feature **F-04**
("Aangepaste token sets uploaden") and is the supported path for organizations
that are not bundled, or whose huisstijl has been refreshed, to bring their own
brand without forking the app or hand-editing files on the server.

Uploaded sets land alongside the bundled sets in the token-set dropdown,
participate in the live preview, the apply dialog, and the Nextcloud theming
sync — exactly like a shipped set.

## Where

Admin settings → **Thematiq** (`/settings/admin/theming`) →
**Custom token sets** section. The feature is admin-only (delegated theming
admins included); every endpoint is CSRF-protected.

## Supported formats

You can upload a single file in either of two formats.

### 1. NL Design CSS

A CSS file containing a single `:root { … }` block of `--nldesign-*` custom
properties — the same format as the bundled `css/tokens/*.css` files:

```css
:root {
  --nldesign-color-primary: #007bc7;
  --nldesign-color-primary-text: #ffffff;
  --nldesign-color-background: #ffffff;
}
```

Organization-palette extras using your own slug prefix
(`--{slug}-*`, e.g. `--gemeente-voorbeeld-color-accent`) are also accepted,
mirroring how bundled sets carry e.g. `--utrecht-color-*`.

### 2. W3C Design Tokens JSON

A file in the [Design Tokens Community Group](https://tr.designtokens.org/)
draft format (`$value` / `$type`, nested groups). Recognized tokens are mapped
onto the `--nldesign-*` vocabulary via the table below; unmapped tokens are
skipped and reported (`imported` / `skipped` counts).

```json
{
  "color": {
    "primary":    { "$type": "color", "$value": "#154273" },
    "on-primary": { "$type": "color", "$value": "#ffffff" }
  }
}
```

#### Mapping table (DTCG path suffix → `--nldesign-*`)

| Token path (suffix match) | `--nldesign-*` target |
|---|---|
| `color.primary` / `brand.primary` | `--nldesign-color-primary` |
| `color.primary-text` / `color.on-primary` | `--nldesign-color-primary-text` |
| `color.primary-hover` | `--nldesign-color-primary-hover` |
| `color.primary-light` | `--nldesign-color-primary-light` |
| `color.background` | `--nldesign-color-background` |
| `color.text` | `--nldesign-color-text` |
| `fontFamily.base` / `typography.font-family` | `--nldesign-font-family` |
| `border-radius.base` / `dimension.border-radius` | `--nldesign-border-radius` |

The longest matching suffix wins, so `color.primary-text` is preferred over
`color.primary`. The mapping is deliberately tolerant: format drift degrades to
a higher `skipped` count, never an error.

## Validation rules

The uploaded file is served as CSS to **every** user on every page (including
the login page), so it is validated strictly and the served file is always
**re-serialized from the parsed declarations** — never from the raw upload
bytes. An upload is rejected when:

- it is larger than **512 KB** (HTTP 413);
- it contains any selector other than a single `:root` block, or any at-rule
  (`@import`, `@font-face`, `@media`, …) (HTTP 422);
- a value contains `@import`, `expression(`, `javascript:`, raw markup (`<`),
  or a `url()` with a scheme or host (HTTP 422). Relative `url('../../img/…')`
  and `data:image/…` URIs are permitted, matching bundled logo usage;
- the display name slugifies to an empty string (HTTP 422);
- a set with the same name already exists (HTTP 409 — delete or rename first).

Properties that are not part of the supported vocabulary (e.g. Nextcloud
`--color-*` variables) are **skipped and listed** in the response so you can
move them to `custom-overrides.css` instead.

## Start from your colours

No token file yet? Under **Custom token sets**, fill in **Start from your colours**: a name, your primary colour, your background colour and, if you like, a logo. Choose **Create house style** and the set is stored like an upload. It appears in the dropdown, and you can edit, export, preview and map it to groups.

Thematiq fills in every token a complete set needs:

- the hover shade and the light tints come from your primary colour;
- the text on your primary colour is black or white, whichever reads better;
- links take your primary colour when it reaches 4.5:1 on your background, and keep the default link colour when it does not;
- status colours, greys, fonts and corner radii keep the default values.

While you pick colours, the preview shows a primary button and its hover shade, exactly as they will be stored. Below it you see two contrast ratios: text on primary against 4.5:1, and primary on background against 3:1. A ratio below its threshold is a warning. You can still save.

The logo may be SVG, PNG, JPG, GIF or WebP, up to 512 KB. It becomes the set's logo for the theming-sync dialog, together with your primary colour.

A set made from two colours is plainer than one a designer made. Refine it in the token editor.

## WCAG 2.1 AA contrast warnings

On upload, the server computes WCAG 2.1 relative-luminance contrast ratios for
two fixed token pairs:

- `--nldesign-color-primary` vs `--nldesign-color-primary-text` — threshold **4.5:1**;
- `--nldesign-color-primary` vs `--nldesign-color-background` — threshold **3:1**.

Failures are returned as **non-blocking warnings** (you can still upload and
apply an in-progress huisstijl), persisted with the set, and resurfaced in the
apply dialog above the change list. Pairs whose values are not literal colours
(e.g. `var(...)`) are reported as `unevaluated` — never as passing.

## Manage uploaded sets

Each uploaded set in the **Custom token sets** list offers:

- a **WCAG AA OK** / **Contrast warning** status badge;
- **Download** — exports the exact CSS that is served (`text/css` attachment);
- **Delete** — removes the file and its metadata. Deleting the **active** set
  resets the active token set to `nextcloud` in the same operation.

## ⚠️ Upgrade caveat

Uploaded sets are stored as files in the app directory
(`css/tokens/custom-{slug}.css`), the same accepted trade-off as
`custom-overrides.css`. An app-store upgrade that replaces the app directory
**removes** uploaded sets. **Export your custom sets (Download) before
upgrading** and re-upload them afterwards. Set metadata lives in the `thematiq`
appconfig key `custom_token_sets` and survives the upgrade, but a manifest entry
without a backing file is ignored by discovery.
