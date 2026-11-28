---
sidebar_position: 6
---

# App Compatibility

This guide explains how Nextcloud app developers can ensure their apps work correctly when Thematiq is active.

## Key Principles

### Use Nextcloud's CSS Variables

Thematiq works by overriding Nextcloud's CSS custom properties. If your app uses these variables, it will automatically pick up the correct colors:

```css
/* Good — uses Nextcloud variables, works with Thematiq */
.my-button {
  background-color: var(--color-primary);
  color: var(--color-primary-text);
  border-radius: var(--border-radius-element);
}

/* Bad — hardcoded colors, ignores Thematiq theming */
.my-button {
  background-color: #0082c9;
  color: white;
  border-radius: 8px;
}
```

### Avoid Hardcoded Colors

Never hardcode color values in CSS or inline styles. Always use Nextcloud's CSS variables or the `--nldesign-*` token namespace.

Common variables to use:

| Purpose | Variable |
|---------|----------|
| Primary accent | `var(--color-primary)` |
| Text on primary | `var(--color-primary-text)` |
| Body text | `var(--color-main-text)` |
| Muted text | `var(--color-text-maxcontrast)` |
| Borders | `var(--color-border)` |
| Error state | `var(--color-error)` |
| Success state | `var(--color-success)` |
| Warning state | `var(--color-warning)` |

### Use Standard Nextcloud Components

If you're building a Vue.js Nextcloud app, use components from `@nextcloud/vue`. These components already use the correct CSS variables and will inherit Thematiq theming automatically.

### Test with Multiple Token Sets

Different token sets have different characteristics:

- **Rijkshuisstijl** uses 0px border radius (sharp corners)
- **Amsterdam** uses a light header with dark icons (unusual contrast)
- **Some municipalities** have very dark or very light primary colors

Test your app with at least 3-4 different token sets to catch contrast and layout issues.

## Testing Your App

1. Install Thematiq in your development environment
2. Enable it and select a token set
3. Navigate through your app and check:
   - All text is readable (contrast)
   - Buttons and interactive elements are visible
   - No hardcoded colors clash with the theme
   - Icons are visible against the header background
4. Switch to a different token set and repeat

## What Thematiq Overrides

Thematiq maps 49 of Nextcloud's 102 CSS variables. The full mapping is documented in the [CSS Variable Mappings reference](../reference/mappings.md).

Variables that Thematiq intentionally does **not** override:
- Layout dimensions (header height, sidebar width, breakpoints)
- Background images and gradients (user-configurable)
- Spacing and clickable area sizes (accessibility standards)
- Dark mode inversion filters (Nextcloud handles these)
