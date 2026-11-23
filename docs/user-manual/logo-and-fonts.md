---
title: Logo and fonts
sidebar_position: 5
description: Set the logo, the mobile logo, the home screen icon and the fonts of your Nextcloud.
---

# Logo and fonts

## The logo

The logo comes with your theme. When you [make your own theme](./make-your-own-theme.md), add it with **Choose logo**. When you apply the theme with **Also update Nextcloud theming** ticked, Thematiq copies the logo into Nextcloud's own theming, so it also appears on the login page, in emails and in the mobile apps.

You can also set the logo directly in Nextcloud's own **Logo** setting under **Background and color**, at the top of the Theming page.

## Mobile logo and home screen icon

Under **Mobile logo and home screen icon**:

- **Mobile logo** — shown in the top bar on screens narrower than 1024 pixels. Use a compact version of your logo.
- **Home screen icon** — shown on a phone when someone adds Nextcloud to their home screen.

Click **Choose image** next to each. Without them, Nextcloud uses the normal logo and the favicon.

## Fonts

Under **Custom fonts** you can give body text and headings their own font.

**From the font library:** the **Font library** lists fonts that come with the app, each with its licence. Click **Use for body text** or **Use for headings** next to a font.

**Your own font file:**

1. Make sure your organisation holds a licence to host the font itself.
2. Type a **Font display name**, for example *Rijks Sans*.
3. Choose the **Font role**: **Body text** or **Heading**.
4. Click **Choose font and upload** and pick a WOFF2 file of at most 2 MB.

Fonts are served from your own Nextcloud; no request goes to an outside font service. You can upload up to 20 fonts. A font from the library takes over the role from an uploaded font.

More detail: [Custom fonts](../features/custom-fonts.md) and [Theming sync](../features/theming-sync.md).
