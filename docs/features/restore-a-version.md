---
sidebar_position: 17
---

# Restore an earlier version

Every theming change you make leaves a version behind: the complete configuration as it was right after that change. When a change goes wrong, for example a custom token set that makes every button unreadable, you put an earlier version back instead of rebuilding the theme by hand.

## From the settings page

Open Settings > Administration > Theming and scroll to the theming audit log. Each row that kept a version has a **Restore** button. Choosing it first shows what the restore will change: the token set, the toggles, custom token sets that come back or go away, and fonts that are no longer uploaded. Nothing is written until you confirm.

A restore goes through the same checks as uploading a configuration bundle. A version that no longer passes them, for example a custom token set that today's CSS rules refuse, is refused whole and nothing changes.

A restore is itself a change, so it keeps a version too. If the restore was the wrong call, restore the row just before it.

## From the command line

```bash
occ nldesign:config:versions
occ nldesign:config:restore 20260929164000-0001 --dry-run
occ nldesign:config:restore 20260929164000-0001
```

The first command lists the kept versions, newest first. `--dry-run` prints the changes and writes nothing. The audit log records a command-line restore with actor `cli`.

## What is kept

The last 50 versions, and at most 20 MB of them, oldest removed first. A version holds font names, not font files: a font you deleted after the version was kept stays on the default font after a restore. Nextcloud's own theming values (logo, colours in the core Theming app) are not part of a version; the theming sync dialog re-applies them.
