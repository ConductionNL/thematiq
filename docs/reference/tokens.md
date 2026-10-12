---
sidebar_position: 1
---

# CSS Token Architecture

This document describes how NL Design System tokens are mapped to Nextcloud styling in the thematiq app.

## Three-Layer Architecture

```
NL Design System repos          thematiq app                 Nextcloud
(upstream source of truth)      (translation layer)          (target)

ams.color.interactive.default   --nldesign-color-primary     --color-primary
utrecht.color.blue.35           --nldesign-color-link        --color-primary-text
rhc.color.lintblauw.500         --nldesign-color-header-*    #header { background }
...                             ...                          ...
```

1. **NL Design System repos** define tokens using their own namespaces (`ams.*`, `utrecht.*`, `rhc.*`)
2. **thematiq token files** (`css/tokens/*.css`) translate those into a unified `--nldesign-*` namespace
3. **theme.css** maps `--nldesign-*` tokens to Nextcloud's CSS variables and element styling

## Token Reference

### Core Tokens (required for every token set)

| Token | Purpose | Example |
|-------|---------|---------|
| `--nldesign-color-primary` | Interactive/accent color (buttons, links, accents) | `#004699` |
| `--nldesign-color-primary-text` | Text on primary color | `#ffffff` |
| `--nldesign-color-primary-hover` | Primary hover state | `#003677` |
| `--nldesign-color-primary-light` | Light tint of primary | `#e8f0f8` |
| `--nldesign-color-primary-light-hover` | Hover on light primary | `#d4e4f2` |

### Action colour (optional)

A token set may give the one main button of a page a colour of its own, apart from its primary. Declare these in the set's file:

| Token | Purpose | Without it |
|-------|---------|------------|
| `--nldesign-color-action` | Fill of the main button, and of everything Nextcloud draws in its primary element colour | The primary |
| `--nldesign-color-action-hover` | The same on hover | The action colour, 15% darker. Without an action colour: the primary hover |
| `--nldesign-color-action-text` | Text and icons on the action colour | The text on the primary |

No default is declared for them, on purpose. A set that leaves them out renders as it always did. Of the shipped sets only Cunningham declares them: a green main button (`#1f883d`) on its blue brand, as OpenProject draws it.

The action colour reaches further than buttons. Nextcloud draws checked boxes and switches, progress bars, the selected navigation entry, focus borders of inputs and counter badges in the same variable (`--color-primary-element`). They all take the action colour. Links, the header and light tints stay with the primary.

Check the text on your action colour: it needs 4.5:1. Thematiq warns when it does not reach that, as it does for the text on the primary. In a dark variant the action colour is treated as the primary is: it keeps its value when its text already reads on it, and its text is adjusted until it does.

The NL Design System and La Suite (Cunningham) bundles read these tokens. Summer Breeze and High contrast do not.

An imported theme brings them too. In a theme's CSS, name them `--<prefix>-color-action`, `--<prefix>-color-action-hover` and `--<prefix>-color-action-text`. In a design tokens (DTCG) file, use the paths `color.action`, `color.action-hover` and `color.action-text`. A theme that names only the action colour gets a readable text on it: white or black, whichever reads better.

### Header Tokens

| Token | Purpose | Example |
|-------|---------|---------|
| `--nldesign-color-header-background` | Nextcloud header bar background | `#154273` |
| `--nldesign-color-header-text` | Text/icon color in header | `#ffffff` |
| `--nldesign-header-icon-filter` | CSS filter for header SVG icons | `brightness(0) invert(1)` |

The default icon filter is `brightness(0) invert(1)` (white icons). Override to `none` for light-colored headers where dark icons are needed (e.g., Amsterdam's white header).

### Logo and Lint/Ribbon Tokens

The lint (ribbon) system follows the Rijkshuisstijl concept of `rhc.logo.image.*` tokens. A colored bar appears behind the logo **only when these tokens are defined**.

| Token | Purpose | Default (fallback) | Example (Rijkshuisstijl) |
|-------|---------|-------------------|--------------------------|
| `--nldesign-logo-url` | Logo image path | `none` (no logo) | `url('../../img/logos/rijkshuisstijl.svg')` |
| `--nldesign-color-logo-background` | Lint/ribbon background color | `transparent` (no ribbon) | `#154273` |
| `--nldesign-color-logo-text` | Text color on ribbon | _(unused)_ | `#ffffff` |
| `--nldesign-size-lint` | Ribbon width in header | `0px` (no ribbon) | `48px` |
| `--nldesign-size-lint-height` | Ribbon/bar height on login page | `0px` (no bar) | `96px` |
| `--nldesign-logo-filter` | CSS filter applied to logo image | `none` (natural colors) | `brightness(0) invert(1)` |

**Conditional behavior:**

- **Lint tokens defined** (currently only Rijkshuisstijl): Colored ribbon behind logo, logo displayed in white (inverted), login page shows colored bar
- **Only `--nldesign-logo-url` defined**: Logo displayed in natural/original colors, no ribbon, login page shows logo on white background
- **Neither defined**: No logo shown, Nextcloud's default logo behavior

### Text Tokens

| Token | Purpose | Example |
|-------|---------|---------|
| `--nldesign-color-text` | Default body text | `#202020` |
| `--nldesign-color-text-muted` | Secondary/muted text | `#767676` |
| `--nldesign-color-text-light` | Text on dark backgrounds | `#ffffff` |

### Status/Feedback Tokens

| Token | Purpose |
|-------|---------|
| `--nldesign-color-error` | Error states |
| `--nldesign-color-error-rgb` | RGB values for rgba() usage |
| `--nldesign-color-error-hover` | Error hover state |
| `--nldesign-color-warning` | Warning states |
| `--nldesign-color-warning-rgb` | RGB values |
| `--nldesign-color-success` | Success states |
| `--nldesign-color-success-rgb` | RGB values |
| `--nldesign-color-info` | Info states |
| `--nldesign-color-info-rgb` | RGB values |

### Link Tokens

| Token | Purpose |
|-------|---------|
| `--nldesign-color-link` | Link text color |
| `--nldesign-color-link-hover` | Link hover color |
| `--nldesign-color-link-visited` | Visited link color |

### Button Tokens

| Token | Purpose |
|-------|---------|
| `--nldesign-color-button-primary-background` | Primary button background |
| `--nldesign-color-button-primary-text` | Primary button text |
| `--nldesign-color-button-primary-border` | Primary button border |
| `--nldesign-color-button-primary-hover` | Primary button hover |

### Other Tokens

| Token | Purpose |
|-------|---------|
| `--nldesign-color-background-hover` | Hover background for elements |
| `--nldesign-color-background-dark` | Dark variant background |
| `--nldesign-color-background-darker` | Darker variant background |
| `--nldesign-color-border` | Standard border color |
| `--nldesign-color-border-dark` | Dark border color |
| `--nldesign-color-focus` | Focus ring color (rgba) |
| `--nldesign-color-focus-rgb` | Focus color RGB values |
| `--nldesign-color-nav-background` | Navigation panel background |
| `--nldesign-font-family` | Font stack |
| `--nldesign-border-radius` | Base border radius |
| `--nldesign-border-radius-small` | Small border radius |
| `--nldesign-border-radius-large` | Large border radius |
| `--nldesign-border-radius-rounded` | Rounded border radius |
| `--nldesign-border-radius-pill` | Pill/full border radius |

## Brand Color vs Interactive Color

Many Dutch municipalities have **different colors** for their brand identity and their interactive elements:

| Municipality | Brand/Identity | Interactive/Primary | Note |
|-------------|---------------|-------------------|------|
| **Amsterdam** | Red (#ec0000) | Blue (#004699) | Red is only for the logo emblem and error feedback |
| **Utrecht** | Red (#cc0000) | Blue (#24578F) | Red is the header/brand banner, blue is for buttons/links |
| **Den Haag** | Green (#1a7a3e) | Green (#1a7a3e) | Same color for both |
| **Rotterdam** | Green (#00811f) | Green (#00811f) | Same color for both |
| **Rijkshuisstijl** | Lintblauw (#154273) | Lintblauw (#154273) | Donkerblauw (#01689b) used for links |

`--nldesign-color-primary` always maps to the **interactive** color, not the brand color. This is because Nextcloud's `--color-primary` drives buttons, links, and accent styling throughout the interface.

The brand/identity color may appear in:
- `--nldesign-color-header-background` (e.g., Utrecht's red header)
- `--nldesign-color-logo-background` (lint/ribbon behind logo)
- Municipality-specific palette variables (e.g., `--amsterdam-color-red`)

## Official Sources

Each token file maps values from the upstream NL Design System repositories:

| Token file | Source repository | Token prefix |
|-----------|------------------|-------------|
| `rijkshuisstijl.css` | [nl-design-system/rijkshuisstijl-community](https://github.com/nl-design-system/rijkshuisstijl-community) | `rhc.*` |
| `amsterdam.css` | [Amsterdam/design-system](https://github.com/Amsterdam/design-system) | `ams.*` |
| `utrecht.css` | [nl-design-system/utrecht](https://github.com/nl-design-system/utrecht) | `utrecht.*` |
| `denhaag.css` | [nl-design-system/denhaag](https://github.com/nl-design-system/denhaag) | `denhaag.*` |
| `rotterdam.css` | [nl-design-system/rotterdam](https://github.com/nl-design-system/rotterdam) | `rods.*` |
| Others | [nl-design-system/themes](https://github.com/nl-design-system/themes) | Various |

## Adding a New Token Set

1. **Create the CSS file** at `css/tokens/{id}.css` with all required `--nldesign-*` variables
2. **Add metadata** to `token-sets.json`:
   ```json
   {
     "id": "myorg",
     "name": "My Organization",
     "description": "Design tokens for My Organization"
   }
   ```
3. **Optional: Add a logo** at `img/logos/{id}.svg` and set `--nldesign-logo-url` in the CSS file
4. **Optional: Add theming metadata** to `token-sets.json` for Nextcloud theming sync:
   ```json
   {
     "id": "myorg",
     "name": "My Organization",
     "description": "...",
     "theming": {
       "primary_color": "#hexcolor",
       "background_color": "#FFFFFF",
       "logo": "img/logos/myorg.svg"
     }
   }
   ```
5. **Optional: Add lint tokens** if the organization uses a ribbon/banner behind their logo (following the Rijkshuisstijl pattern)

The admin dropdown picks up new token sets automatically from the filesystem. No PHP changes are needed.

### Mapping Guidelines

When translating tokens from an upstream NL Design System repo:

- **`--nldesign-color-primary`** = the **interactive** color (buttons, accents), NOT the brand/logo color
- **`--nldesign-color-header-background`** = the background of the Nextcloud top navigation bar
- **`--nldesign-color-link`** = may differ from primary (e.g., Rijkshuisstijl uses donkerblauw for links)
- **`--nldesign-color-error`** = map from `*.color.feedback.error` or equivalent
- Always check the municipality's actual website to verify how colors are used in practice
- Reference the upstream repo's component tokens to understand which palette color maps to which purpose
