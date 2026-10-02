---
sidebar_position: 3
---

# The Nextcloud variable inventory

thematiq keeps a list of every CSS custom property Nextcloud and `@conduction/nextcloud-vue` declare or read. The list says what "every variable" means for one Nextcloud release. A test then checks that thematiq's stylesheets match what the list claims.

## The three files

| File | What it holds | Who writes it |
|---|---|---|
| `scripts/mapping/nextcloud-variables.json` | Every variable, its class, its owning family, where it is declared, and Nextcloud's own value per theme | The extractor. Never edit it by hand. |
| `scripts/mapping/variable-status.json` | Per variable: `mapped`, `settable` or `excluded`, with a reason for every exclusion | You. These are decisions. |
| `scripts/mapping/variable-baseline.json` | The exact count per class and status | You, in the same commit as the status change. |

[The mappings page](./mappings.md) is generated from the first two.

## Classes

| Class | Meaning |
|---|---|
| `theme` | Declared by Nextcloud's theming app: the vocabulary a theme is meant to set. |
| `component` | Declared and read inside one Nextcloud component. |
| `slot` | Read by a component with its own fallback, declared nowhere. |
| `conduction` | A `--cn-*` variable of the shared Conduction library. |
| `icon` | An image URL. Never a token. |
| `runtime` | Written by Nextcloud's JavaScript at render time. A stylesheet value would be overwritten. |
| `unread` | Declared, read by nothing Nextcloud ships. |

## After a Nextcloud release

Run these on a machine with the dev stack up:

```bash
npm run inventory:fetch     # copies Nextcloud's css/js and the four theming stylesheets
npm run inventory:extract   # rewrites nextcloud-variables.json
npm run test:inventory      # fails on every new or vanished variable
```

For every failure, add or remove the entry in `variable-status.json`. Then update the counts in `variable-baseline.json` and run `npm run generate:mappings`.

`npm run inventory:check` compares the committed inventory with a fresh extraction without writing anything. It needs the sources from `inventory:fetch`, so CI does not run it. CI does run `test:inventory` as part of the unit suite.

## What the test refuses

- A variable with no status, or a status for a variable Nextcloud does not have.
- An exclusion without a reason.
- A `mapped` claim the stylesheets do not make. Comments and `--x: var(--x)` do not count.
- A theme variable the stylesheets set but the status file does not record.
- A Nextcloud-style name, such as `--color-...`, that Nextcloud does not have. The four that summer-breeze sets today are listed under `deadAssignments`, and the list can only shrink.
- Any change in the counts that the baseline does not repeat, upwards or downwards.
