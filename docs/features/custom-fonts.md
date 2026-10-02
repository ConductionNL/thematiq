---
sidebar_position: 23
---

# Use your own fonts

Upload your organisation's own typeface and Thematiq serves it from your server. Without an upload, every NL Design System set renders in Fira Sans, which ships inside the app.

## The font that ships

Most Dutch government typefaces, such as RijksoverheidSans, are proprietary. Thematiq cannot redistribute them. It bundles Fira Sans instead, under the SIL Open Font License, as an open stand-in.

The font files live in the app and load through app-relative URLs. No request goes to a font CDN, so the fonts work within Nextcloud's Content Security Policy and on servers without internet access.

## Upload a font

Only upload a font your organisation holds a licence to self-host. That responsibility rests with you, and the upload panel says so.

1. In the Thematiq admin settings, find **Custom fonts**.
2. Enter a **Font display name**, for example `Rijks Sans`.
3. Pick a **Font role**: **Body text** or **Heading**.
4. Choose a WOFF2 file and click **Choose font and upload**.

Thematiq checks the file content, not its name: the file must start with the WOFF2 signature. These limits apply:

| Limit | Value |
|---|---|
| Format | WOFF2 only |
| Size per file | 2 MB |
| Fonts per server | 20 |
| Font id | `custom-` plus a slug of the display name |

A name that is already taken is refused, and the existing font stays as it was.

## What changes on the page

A body font replaces `--nldesign-font-family`. A heading font replaces `--nldesign-typography-heading-font-family`. In both cases the bundled stack stays behind it as the fallback, so a font that fails to load gives you Fira Sans.

Thematiq serves uploaded fonts at `/apps/thematiq/fonts/{id}.woff2`. That address needs no login, because the login page uses the font before anyone signs in. Browsers may cache the file for a year.

## Moving to another server

The [configuration bundle](./configuration-bundle.md) lists your fonts but does not carry the font files. Upload them again on the target server.

For the French State typeface Marianne, see [La Suite design stack](./la-suite.md).

Next, pick the token set your font belongs with in [token sets](./token-sets.md).
