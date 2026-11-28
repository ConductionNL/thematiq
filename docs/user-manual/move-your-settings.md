---
title: Move your settings
sidebar_position: 8
description: Copy your complete Thematiq configuration from a test environment to production in one file.
---

# Move your settings

Set up and test your theme on a test environment, then copy the result to production in one go.

## Step 1 — Download from the test environment

On the test environment, scroll to **Configuration bundle (OTAP promotion)** and click **Download configuration**. You get one JSON file with the active theme, the display options, the per-app settings, your overrides, your custom themes, the email footer and the other Thematiq settings.

## Step 2 — Upload on production

On production, open the same section, click **Upload configuration** and choose the file.

## Only the theme

To hand over just one theme rather than the whole configuration:

- pick it in the **Design token set** list and click **Download as design tokens**, or
- click **Download** next to it under **Your custom token sets**.

Upload that file on the other server as described in [Upload a theme file](./upload-a-theme-file.md).

More detail: [Configuration bundle](../features/configuration-bundle.md) and [Theme as code](../features/theme-as-code.md).
