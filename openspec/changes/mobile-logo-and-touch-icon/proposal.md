---
kind: code
---

# A mobile logo and a home screen icon for the workspace

## Why

thematiq's parity list decided no on `ast-mobile-logo` on 28 Sep 2026. Ruben reopened it on 12 Oct
2026: thematiq and the OpenProject plugin reach the same functionality by adding, never by removing,
and the plugin has a mobile logo and a touch icon since its #32. Wave T of the plugin's parity map
asks for them here.

Nextcloud draws one header logo at every width, and serves its favicon as the touch icon of every
app (`apps/theming/lib/Controller/IconController.php`), so a home screen icon cannot be set without
changing the tab icon.

## What changes

- An admin block **Mobile logo and home screen icon** with an upload and a remove button per image.
- The mobile logo (PNG, JPEG, WebP or a script-free SVG, at most 1 MB) is drawn in the header below
  1024 px, the width at which Nextcloud's header goes narrow. A brand per app keeps its own small logo.
- The home screen icon (a square PNG of at least 120 px) is linked as `apple-touch-icon` on every
  page and the login page. The favicon in the tab does not change.
- Both are served publicly at `/apps/thematiq/api/workspace-icons/{kind}`: a phone fetches the touch
  icon without a session.

## Not in this change

Android's web app manifest. Nextcloud's theming app writes it (`/apps/theming/manifest`) from the
favicon, and thematiq has no hook into it, so an Android home screen keeps Nextcloud's icon.
