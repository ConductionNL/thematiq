---
sidebar_position: 19
---

# Set the theme from the command line

You switch the house style with `occ`, so a deployment script or a configuration tool can do what the Design token set dropdown does. The command checks the set exactly as the dropdown does, and the theming audit log records the change with actor `cli`.

## Find the id of a set

```bash
occ nldesign:theme:list
```

Each line shows the id, whether the set is `shipped` or `custom`, and its name. Use the id in the other commands.

## See what is active

```bash
occ nldesign:theme:get
```

The first line names the active instance-wide set. Below it you see the group mappings, highest priority first, as on the settings page.

## Switch the active set

```bash
occ nldesign:theme:set amsterdam
```

An unknown id stops the command with an error that names the id, and nothing changes. Setting the set that is already active changes nothing and writes no audit entry, so a script can run the command on every deploy.

Two options:

- `--dry-run` checks the id and prints what would change, without writing.
- `--sync-core` also applies the set's primary colour, background and logo to Nextcloud theming, as the theming-sync dialog does. Without it, Nextcloud theming stays as it is. The output says which of the two happened.

## A scripting example

```bash
#!/usr/bin/env bash
set -e
occ nldesign:theme:set "$HOUSE_STYLE" --dry-run
occ nldesign:theme:set "$HOUSE_STYLE" --sync-core
occ nldesign:theme:get
```

The first call fails the script on a typo before anything changes. Group mappings are not set here: `occ nldesign:config:import` sets them as part of a configuration bundle.
