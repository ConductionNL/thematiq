---
title: Emails and documents
sidebar_position: 7
description: Brand the emails Nextcloud sends and the letters and PDFs apps generate.
---

# Emails and documents

## Emails

Password resets, share notifications and other emails from Nextcloud can carry your theme's colour and logo. Under **Email template**:

1. Tick **Use NL Design email template**.
2. Fill in the footer: **Organization name**, **Accessibility statement URL** and **Privacy statement URL**.
3. Click **Save email template settings**.

If another app already set its own email template, Thematiq says so and leaves it alone. If Thematiq is disabled later, Nextcloud goes back to its own email template by itself.

When Nextcloud's `config.php` is read-only, the section shows the `occ` commands your server administrator can run instead.

## Documents

Apps that make letters and PDF exports use the values under **Documents**:

- **Document logo** and **Cover image** — PNG, JPEG, WebP or SVG of at most 2 MB. Click **Choose image**; **Remove document logo** and **Remove cover image** remove them again.
- **Extra footer line** — a line of text at the bottom of every document. Click **Save footer line**.

Without uploads, documents use the theme's logo and the email footer.

More detail: [Email theming](../features/email-theming.md).
