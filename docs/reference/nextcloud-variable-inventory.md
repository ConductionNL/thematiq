---
sidebar_position: 3
---

# Nextcloud variable inventory

thematiq keeps a list of every CSS custom property that Nextcloud and `@conduction/nextcloud-vue` declare or read. The list lives in `scripts/mapping/nextcloud-variables.json`. A guard checks that thematiq has decided what to do with each one.

Use this page after a Nextcloud release, or after a bump of `@conduction/nextcloud-vue`.

## What the files hold

| File | Written by | Holds |
|---|---|---|
| `scripts/mapping/nextcloud-variables.json` | the extractor | one entry per property: class, owner, selectors, stock value per theme |
| `scripts/mapping/nextcloud-variables.sha` | the extractor | one hash per entry, so a hand edit fails the guard |
| `scripts/mapping/variable-status.json` | you | what thematiq does with each entry: `mapped`, `settable` or `excluded` with a reason |
| `scripts/inventory/sources/nextcloud-<version>/theming/` | you, once per release | the four theming stylesheets Nextcloud serves |
| [mappings.md](./mappings.md) | `generate-mappings.mjs` | the same data as a table |

Each entry has one class:

| Class | Rule |
|---|---|
| `theme` | declared by a theming stylesheet |
| `icon` | an `--icon-*` name, or a `url()` value |
| `runtime` | written from JavaScript, or a Vue `v-bind()` in a style block |
| `component` | declared and read in shipped CSS or JavaScript |
| `slot` | read with `var()`, declared nowhere |
| `unread` | declared, never read |
| `conduction` | a `--cn-*` property of the shared library |

## Regenerate after a Nextcloud release

1. Get the release files. The Docker image has them, so no server needs to run:

   ```bash
   mkdir -p ~/nc-release && docker run --rm --entrypoint sh nextcloud:<version>-apache -c \
     'cd /usr/src/nextcloud && tar -c version.php core apps dist' | tar -x -C ~/nc-release
   ```

2. Save the theming stylesheets of a stock instance of that release. Use a fresh container, because a themed instance serves its own colours:

   ```bash
   for theme in default dark light-highcontrast dark-highcontrast; do
     curl -s "http://localhost:<port>/apps/theming/theme/$theme.css" \
       -o "scripts/inventory/sources/nextcloud-<version>/theming/$theme.css"
   done
   ```

3. Run the extractor and the guard:

   ```bash
   npm run inventory:extract -- --nextcloud ~/nc-release
   npm run test:inventory
   ```

4. The guard names every new property without a status. Give each one a line in `variable-status.json`. An exclusion needs a reason.

5. Write the table and commit all files together:

   ```bash
   npm run inventory:mappings
   ```

To check that the committed inventory matches a release, without writing anything:

```bash
npm run inventory:extract -- --nextcloud ~/nc-release --check
```

It exits 1 and names the first entry that differs. Without a release directory it exits 2.

## When the guard fails

| Message | What to do |
|---|---|
| `has no status` | Add a status line for the property. |
| `is excluded without a reason` | Write the reason. |
| `has a status but ... has no such variable` | Nextcloud dropped it. Remove the line. |
| `lost its "mapped" status` | Restore the mapping, or record why in `removals`. |
| `the ... baseline does not list it` | Coverage went up. Add the name to `baseline`. |
| `is assigned in ... but ... has no such variable` | Fix the typo, or list the name in `foreign` with the reason thematiq sets it. |
| `differs from what the extractor wrote` | Someone edited the inventory by hand. Run the extractor instead. |

Next: open [mappings.md](./mappings.md) and look up the property you want to theme.
