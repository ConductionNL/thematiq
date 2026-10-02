---
sidebar_position: 24
---

# Try a theme before you publish it

Try a token set on yourself before everyone sees it. A preview shows the set in your own session only, on every page you open. Other users keep the active house style.

## Start a preview

1. In the Thematiq admin settings, pick a set in **Design token set**.
2. Click **Preview in my session**.
3. Browse Nextcloud as you normally would.

A banner on every page names the set and says only you see it. It has two buttons: **Publish** and **Discard**.

## Publish or discard

**Discard** ends the preview. Your pages go back to the active house style.

**Publish** takes you to the admin settings. There the usual [apply dialog](./apply-dialog.md) opens, followed by the [theming sync](./theming-sync.md) dialog when the set carries theming values. The set goes live for everyone only when you confirm. Cancel either dialog and both the preview and the active set stay as they were.

## What a preview changes

A preview swaps the token set and nothing else. Your token overrides, the slogan and menu-label toggles, and the per-app exclusions keep working as they do for everyone.

A preview wins over [group theming](./group-theming.md) for your own session.

## Limits

- A preview ends by itself after 24 hours.
- Only administrators can preview. Thematiq checks this on every page, so an admin who loses the role stops seeing the preview.
- The preview is stored on your user account, not on the instance. It is not part of the [configuration bundle](./configuration-bundle.md).

Next, publish the set and check the [contrast evidence report](./compliance-report.md).
