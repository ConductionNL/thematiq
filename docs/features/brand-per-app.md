---
sidebar_position: 19
---

# Give an app its own brand

Some apps carry their own identity: a knowledge base, a participation platform, a service portal. You can give such an app its own house style and logo. On that app's pages everyone sees the app's brand; every other page keeps the house style.

## Set a brand

1. On Settings > Administration > Theming, open Brand per app.
2. Choose the app and the token set, and save the brand.
3. Optionally upload a large logo and a small logo on the app's row (PNG, JPEG, WebP or SVG, at most 1 MB; an SVG with script is refused).

The small logo is shown on screens narrower than 1024 pixels, where Nextcloud's header goes narrow. Without a small logo the large one is used at every width. Without logos the token set's own logo is used.

Each row shows the contrast result of the token set. A low result is a warning, not a block, as everywhere in the app.

## Which apps can have a brand

An app must be installed and themed. The settings pages (Thematiq, Settings and Theming) always follow the house style, so they cannot get a brand. An app you excluded under Theming per app must be included again first. A brand whose app was removed, or whose token set is gone, is ignored and marked on its row.

## What wins

On a page of a branded app:

1. A theme preview you started as an administrator still wins, so you can try a set anywhere.
2. Otherwise the app's brand applies, whatever group the user is in. A branded app looks the same for every municipality on a shared Nextcloud.
3. Pages of other apps follow the group mapping and the instance default, as before.

Pages without a session, such as the login page and public share pages, always use the instance default.

## What stays Nextcloud's

The app's name in the app menu and the page titles come from Nextcloud and the app itself. Thematiq does not change them. The favicon is also Nextcloud's.

## Moving brands between servers

Brands travel in the configuration bundle as `appBrands` (bundle version 3): the token set per app. Logos are not in the bundle; upload them again on the target server.
