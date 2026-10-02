---
sidebar_position: 22
---

# Brand the system emails

Password resets, share notifications and other system emails can carry your house style. Thematiq adds your logo and primary colour, plus a footer that links your accessibility and privacy statements.

## Turn it on

1. In the Thematiq admin settings, find **Email template**.
2. Tick **Use NL Design email template**.
3. Fill in the **Organization name**, the **Accessibility statement URL** and the **Privacy statement URL**.
4. Click **Save email template settings**.

The next email Nextcloud sends uses the template. Leave a footer field empty and that line is left out. The footer appears in both the HTML part and the plain-text part. Both URLs must start with `http://` or `https://`.

## When config.php is read-only

The toggle writes `mail_template_class` to `config.php`. On a read-only `config.php` the save fails for that part, and the panel shows the command to run instead:

```bash
occ config:system:set mail_template_class --value "OCA\\Thematiq\\Mail\\NLDesignEMailTemplate"
```

To switch back to the stock template:

```bash
occ config:system:delete mail_template_class
```

The footer fields are app settings, so they save even when `config.php` cannot be written.

## Another template is already set

If `mail_template_class` already names a different class, Thematiq leaves it alone. The toggle is disabled and the panel names the class it found.

## What the email takes from the theme

The logo and colours come from the instance token set. [Group theming](./group-theming.md) does not reach email: every recipient gets the instance house style.

If a part of the theme cannot be read, that part falls back to the stock Nextcloud email. A theming problem never stops an email from being sent. If you disable or remove Thematiq while the setting is on, Nextcloud falls back to its stock template and the mail goes out unbranded.

The footer fields travel in the [configuration bundle](./configuration-bundle.md). The `config.php` setting does not, so switch the template on per server.

Next, send yourself a password reset to see the result.
