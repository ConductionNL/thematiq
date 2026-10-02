---
sidebar_position: 4.2
---

# Set any Nextcloud theme variable

Your token set can set every variable in Nextcloud's own theme vocabulary: the search highlight, selected text, the loading spinner, shadows, the hover colours of the status messages, font sizes, input borders and more. Unset, each one keeps the value Nextcloud gives it for the light, dark or high-contrast theme your user chose.

## How to set one

Each variable has a token named `--nldesign-nc-` followed by the variable's own name. Declare it in your set like any other token:

```css
:root {
	--nldesign-nc-color-mark: #ffe08a;
	--nldesign-nc-default-font-size: 16px;
}
```

[The mappings page](../reference/mappings.md) lists every variable, its token and Nextcloud's own value in light and dark.

These tokens have no default. That is the point: a set that leaves one out changes nothing for it.

## Light and dark

Some variables have a different value in Nextcloud's dark theme, such as the highlight, the hover colours and the main background. For those:

- a value in your set's light file applies in light only, and dark keeps Nextcloud's own;
- a value in your set's dark file applies in dark.

Every other variable, such as a font size or a border width, takes your value in both themes.

## Layout variables

Thirteen variables size Nextcloud's layout: the header height, the navigation and sidebar widths, the mobile breakpoint, the grid baseline and the clickable areas. You can set them, and the token editor shows them under **Advanced**, with a warning. A wrong value here breaks the layout, not only the look.

## Contrast

The contrast audit checks two more pairs whenever your set moves one side of them:

- selected text against the selection wash, which Nextcloud draws as 20% of your primary colour;
- body text against the search highlight.

Both need at least 4.5:1.
