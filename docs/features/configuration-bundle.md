---
sidebar_position: 26
---

# Move a configuration between servers

Build the house style on test, then put the same configuration on acceptance and production. The configuration bundle is one JSON file with everything Thematiq needs to reproduce it.

This is not the overrides file from [import and export](./import-export.md). That file holds your token overrides only. The bundle holds the whole configuration.

## What the bundle carries

| In the bundle | Not in the bundle |
|---|---|
| The active token set | Nextcloud's own theming values (logo, background) |
| Hide slogan, show menu labels, primary colour drives components | The environment marker in `config.php` |
| Per-app exclusions | The `mail_template_class` setting in `config.php` |
| Your token overrides (`custom-overrides.css`) | Font files |
| Every custom token set, CSS included | Group mappings |
| The email footer fields | Dark variants, Marianne and icon pack settings |
| The upstream update check toggle | Theme previews and counters |
| Planned switches | |

The bundle lists your custom fonts, but not the font files. Upload those again on the target server.

## From the settings page

In the Thematiq admin settings, find **Configuration bundle (OTAP promotion)**.

- **Download configuration** saves `nldesign-config.json`.
- **Upload configuration** reads a bundle file. The file may be up to 256 KB.

## From the command line

```bash
occ thematiq:config:export bundle.json
occ thematiq:config:import bundle.json --dry-run
occ thematiq:config:import bundle.json
```

Export without a file name writes to standard output. `--dry-run` checks the bundle and writes nothing.

## All or nothing

An import checks every part of the bundle first, with the same rules as the settings page. One invalid part stops the whole import, and nothing changes. You get a list of what failed, per part.

Unknown token names in the overrides are the exception. They are skipped and counted, not refused.

Importing the same bundle twice gives the same result. Bundles from an older version of the format still import. A version 1 bundle predates planned switches and leaves them alone.

Download a bundle from the target server before you import, so you can put it back.

## Share with other servers through OpenRegister

When OpenRegister runs on your server, Thematiq offers the theme as a shareable configuration type, `nldesign.theme`. OpenRegister moves it between servers. The receiving side applies it through the same import checks. Without OpenRegister, Thematiq works as before.

Next, export a bundle on test and run a dry run on acceptance.
