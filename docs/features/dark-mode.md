---
sidebar_position: 20
---

# Dark mode

Users who run Nextcloud in dark mode keep your house style. Thematiq ships a dark variant of every token set and loads it when Nextcloud is already dark. You decide only whether the variants load at all.

## What users see

Thematiq follows the theme Nextcloud has already chosen. It never sets one.

| User setting in Nextcloud | Operating system | Result |
|---|---|---|
| System default | Dark | Dark variant |
| System default | Light | Light house style |
| Dark | Any | Dark variant |
| Light | Any | Light house style |

The login page has no user yet, so it follows the operating system. Thematiq never touches `enforce_theme` or a user's own theme preference.

## How a dark variant is made

Each dark variant is a static file in `css/tokens/dark/`. Thematiq derives it from the light set: hue stays the same, lightness flips per token class. Backgrounds go dark and text goes light. Sizes, fonts and radii stay as they are.

Before a file is written, every text pair is checked at WCAG AA, 4.5:1. A derived pair that fails is pushed lighter or darker until it passes.

A token set can also declare its own dark values in a `@media (prefers-color-scheme: dark)` block. Those win over the derived values. Thematiq still checks them, but only warns when one fails: it never rewrites a value you chose.

Two design systems get no dark variant: the stock Nextcloud base and the high-contrast set.

## Turn dark variants off

Dark variants are on by default. To turn them off, clear **Enable dark mode variants for the active token set** in the Thematiq admin settings. Or use the command line:

```bash
occ config:app:set thematiq dark_variants --value=0
```

The generated files stay on disk, so turning the setting back on takes effect at once.

## Regenerate the files

Variants for shipped sets come with the app. A variant for a custom token set is written when you upload the set and removed when you delete it. To rebuild them by hand:

```bash
occ thematiq:generate-dark-variants
occ thematiq:generate-dark-variants --set=amsterdam --force
```

Without `--force`, a file that is still current is skipped. Each file records a hash of its source set, so the command knows.

Next, check how the active set scores on contrast with the [contrast evidence report](./compliance-report.md).
