---
sidebar_position: 2
---

# Nextcloud CSS Variable to NL Design Token Mappings

This document provides a complete mapping between every Nextcloud CSS custom property and its `--nldesign-*` equivalent. Variables are organized by category.

**Legend:**
- **Mapped** = Nextcloud variable is overridden with an `--nldesign-*` token
- **Unmapped** = No appropriate NL Design equivalent; variable is commented out in `overrides.css`
- **Intentionally not overridden** = Variable exists but is left to Nextcloud's control

---

## Primary Colors

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-primary` | `--nldesign-color-primary` | Primary | Main brand color |
| `--color-primary-text` | `--nldesign-color-primary-text` | Primary | Text on primary background |
| `--color-primary-hover` | `--nldesign-color-primary-hover` | Primary | Primary hover state |
| `--color-primary-element` | `--nldesign-color-primary` | Primary | Interactive element primary color |
| `--color-primary-element-hover` | `--nldesign-color-primary-hover` | Primary | Interactive element hover |
| `--color-primary-element-text` | `--nldesign-color-primary-text` | Primary | Text on primary element |
| `--color-primary-element-text-dark` | unmapped | Primary | Dark variant of primary element text; no NL Design equivalent |
| `--color-primary-light` | `--nldesign-color-primary-light` | Primary | Light variant of primary |
| `--color-primary-light-hover` | `--nldesign-color-primary-light-hover` | Primary | Light primary hover |
| `--color-primary-light-text` | `--nldesign-color-primary` | Primary | Text on light primary; uses brand color |
| `--color-primary-element-light` | `--nldesign-color-primary-light` | Primary | Deprecated alias for primary-light |
| `--color-primary-element-light-text` | `--nldesign-color-primary` | Primary | Deprecated alias |
| `--color-primary-element-light-hover` | `--nldesign-color-primary-light-hover` | Primary | Deprecated alias |

## Main Background

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-main-background` | intentionally not overridden | Background | Core page background; overriding breaks dark mode and accessibility themes |
| `--color-main-background-rgb` | intentionally not overridden | Background | RGB variant for rgba() usage; depends on --color-main-background |
| `--color-main-background-translucent` | intentionally not overridden | Background | Translucent variant; depends on --color-main-background-rgb |
| `--color-main-background-blur` | intentionally not overridden | Background | Blur variant; depends on --color-main-background-rgb |
| `--color-background-plain` | intentionally not overridden | Background | Admin/user configured background; should respect user settings |
| `--color-background-plain-text` | intentionally not overridden | Background | Text on plain background; auto-calculated by Nextcloud |

## Background States

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-background-hover` | `--nldesign-color-background-hover` | Background State | Hover state background |
| `--color-background-dark` | `--nldesign-color-background-dark` | Background State | Darker background variant |
| `--color-background-darker` | `--nldesign-color-background-darker` | Background State | Darkest background variant |

## Placeholder Colors

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-placeholder-light` | `--nldesign-color-placeholder-light` | Placeholder | Light placeholder (e.g., empty states) |
| `--color-placeholder-dark` | `--nldesign-color-placeholder-dark` | Placeholder | Dark placeholder |

## Text Colors

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-main-text` | `--nldesign-color-text` | Text | Main body text color |
| `--color-text-maxcontrast` | `--nldesign-color-text-muted` | Text | Muted/secondary text |
| `--color-text-maxcontrast-default` | unmapped | Text | Default maxcontrast; auto-calculated by Nextcloud per theme |
| `--color-text-maxcontrast-background-blur` | unmapped | Text | Blur-adjusted maxcontrast; Nextcloud calculates per background |
| `--color-text-light` | `--nldesign-color-text-light` | Text | Light-colored text (deprecated in NC) |
| `--color-text-lighter` | `--nldesign-color-text-muted` | Text | Lighter text (deprecated alias) |
| `--color-text-error` | `--nldesign-color-error` | Text | Error message text |
| `--color-text-success` | `--nldesign-color-success` | Text | Success message text |
| `--color-text-warning` | `--nldesign-color-warning` | Text | Warning message text |

## Status: Error

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-error` | `--nldesign-color-error` | Status | Error background/accent |
| `--color-error-hover` | `--nldesign-color-error-hover` | Status | Error hover state |
| `--color-error-text` | unmapped | Status | Error text color; NC auto-calculates for contrast |
| `--color-error-rgb` | `--nldesign-color-error-rgb` | Status | Deprecated; RGB for rgba() |
| `--color-element-error` | `--nldesign-color-error` | Status | Error element accent |
| `--color-border-error` | `--nldesign-color-error` | Status | Error border; uses error color |

## Status: Warning

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-warning` | `--nldesign-color-warning` | Status | Warning background/accent |
| `--color-warning-hover` | unmapped | Status | Warning hover; no NL Design equivalent |
| `--color-warning-text` | unmapped | Status | Warning text; NC auto-calculates for contrast |
| `--color-warning-rgb` | `--nldesign-color-warning-rgb` | Status | Deprecated; RGB for rgba() |
| `--color-element-warning` | `--nldesign-color-warning` | Status | Warning element accent |

## Status: Success

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-success` | `--nldesign-color-success` | Status | Success background/accent |
| `--color-success-hover` | unmapped | Status | Success hover; no NL Design equivalent |
| `--color-success-text` | unmapped | Status | Success text; NC auto-calculates for contrast |
| `--color-success-rgb` | `--nldesign-color-success-rgb` | Status | Deprecated; RGB for rgba() |
| `--color-element-success` | `--nldesign-color-success` | Status | Success element accent |
| `--color-border-success` | `--nldesign-color-success` | Status | Success border; uses success color |

## Status: Info

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-info` | `--nldesign-color-info` | Status | Info background/accent |
| `--color-info-hover` | unmapped | Status | Info hover; no NL Design equivalent |
| `--color-info-text` | unmapped | Status | Info text; NC auto-calculates for contrast |
| `--color-info-rgb` | unmapped | Status | Deprecated; RGB for rgba() |
| `--color-element-info` | `--nldesign-color-info` | Status | Info element accent |

## Borders

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-border` | `--nldesign-color-border` | Border | Standard border color |
| `--color-border-dark` | `--nldesign-color-border-dark` | Border | Dark border variant |
| `--color-border-maxcontrast` | `--nldesign-color-border-dark` | Border | Max contrast border; maps to dark border |
| `--border-width-input` | unmapped | Border | Input border width (1px); standard across all themes |
| `--border-width-input-focused` | unmapped | Border | Focused input border width (2px); standard |

## Border Radius

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--border-radius` | `--nldesign-border-radius` | Border Radius | Deprecated; default border radius |
| `--border-radius-small` | `--nldesign-border-radius-small` | Border Radius | Small elements (4px default) |
| `--border-radius-element` | `--nldesign-border-radius` | Border Radius | Interactive elements (8px default) |
| `--border-radius-container` | unmapped | Border Radius | Containers (12px); intentionally not overridden for layout consistency |
| `--border-radius-container-large` | unmapped | Border Radius | Large containers (16px); intentionally not overridden |
| `--border-radius-large` | `--nldesign-border-radius-large` | Border Radius | Deprecated alias for element radius |
| `--border-radius-rounded` | `--nldesign-border-radius-rounded` | Border Radius | Rounded elements (28px default) |
| `--border-radius-pill` | `--nldesign-border-radius-pill` | Border Radius | Pill shape (100px) |

## Typography

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--font-face` | `--nldesign-font-family` | Typography | Font stack |
| `--default-font-size` | unmapped | Typography | Base font size (15px); standard across themes |
| `--font-size-small` | unmapped | Typography | Small font size (13px); standard |
| `--default-line-height` | unmapped | Typography | Line height (1.5); standard |
| `--font-weight-default` | `--nldesign-font-weight-default` | Typography | Body weight; new in NC 33/34 |
| `--font-weight-element` | `--nldesign-font-weight-element` | Typography | Weight of controls and button labels; new in NC 33/34. NcButton in @nextcloud/vue 9 reads it on `.button-vue__text`, where NC 32 hard-coded `bold` |
| `--font-weight-heading` | `--nldesign-font-weight-heading` | Typography | Heading weight; new in NC 33/34. The per-level `--nldesign-component-heading-N-font-weight` tokens resolve through it |

## Spacing & Clickable Areas

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--default-clickable-area` | unmapped | Spacing | Touch target (34px); accessibility standard |
| `--clickable-area-large` | unmapped | Spacing | Large touch target (48px); accessibility standard |
| `--clickable-area-small` | unmapped | Spacing | Small touch target (24px); accessibility standard |
| `--default-grid-baseline` | unmapped | Spacing | Base grid unit (4px); core layout building block |

## Layout & Dimensions

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--header-height` | intentionally not overridden | Layout | Header height (50px); overriding breaks NC layout |
| `--header-menu-item-height` | intentionally not overridden | Layout | Header menu item height; NC layout dependent |
| `--header-menu-icon-mask` | intentionally not overridden | Layout | Icon mask gradient; NC internal |
| `--navigation-width` | intentionally not overridden | Layout | Navigation panel width (300px); NC layout dependent |
| `--sidebar-min-width` | intentionally not overridden | Layout | Sidebar min width; NC layout dependent |
| `--sidebar-max-width` | intentionally not overridden | Layout | Sidebar max width; NC layout dependent |
| `--body-container-radius` | intentionally not overridden | Layout | Body container radius; depends on border-radius-container-large |
| `--body-container-margin` | intentionally not overridden | Layout | Body container margin; NC layout dependent |
| `--body-height` | intentionally not overridden | Layout | Body height calculation; NC layout dependent |
| `--breakpoint-mobile` | intentionally not overridden | Layout | Mobile breakpoint (1024px); NC responsive design |
| `--footer-height` | intentionally not overridden | Layout | Footer height; NC layout dependent |

## Animations

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--animation-quick` | `--nldesign-animation-quick` | Animation | Quick animation duration (100ms) |
| `--animation-slow` | `--nldesign-animation-slow` | Animation | Slow animation duration (300ms) |
| `--filter-background-blur` | intentionally not overridden | Animation | Background blur filter; Nextcloud handles browser compat |

## Shadows

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-box-shadow-rgb` | unmapped | Shadow | Box shadow RGB; auto-calculated from background |
| `--color-box-shadow` | unmapped | Shadow | Box shadow color; depends on --color-box-shadow-rgb |

## Gradients

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--gradient-main-background` | intentionally not overridden | Gradient | Main background gradient; depends on main-background vars |
| `--gradient-primary-background` | intentionally not overridden | Gradient | Primary gradient; depends on primary color |

## Special Colors

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-favorite` | `--nldesign-color-favorite` | Special | Favorite/star color |
| `--color-loading-light` | unmapped | Special | Loading animation light; NC internal |
| `--color-loading-dark` | unmapped | Special | Loading animation dark; NC internal |
| `--color-scrollbar` | intentionally not overridden | Special | Scrollbar color; depends on border-maxcontrast |
| `--color-background-selection` | intentionally not overridden | Special | Selection wash; new in NC 33/34. Derived from `--color-primary-element` at 20% alpha, and that variable is mapped |
| `--color-text-selection` | intentionally not overridden | Special | Selected text; new in NC 33/34. Derived from `--color-main-text`, which is mapped |
| `--color-mark` | unmapped | Special | `<mark>` highlight tint (#fff0c7); new in NC 33/34. No NL Design highlight token, and the tint has to stay legible under the text colour |

## Focus

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| (no direct NC variable) | `--nldesign-color-focus` | Focus | NL Design focus ring color; applied via theme.css |
| (no direct NC variable) | `--nldesign-color-focus-rgb` | Focus | RGB variant for focus opacity |

## Filters & Inversion

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--background-invert-if-dark` | intentionally not overridden | Filter | Dark mode image inversion; NC theme dependent |
| `--background-invert-if-bright` | intentionally not overridden | Filter | Bright mode inversion; NC theme dependent |
| `--background-image-invert-if-bright` | intentionally not overridden | Filter | Background image inversion; NC theme dependent |
| `--primary-invert-if-bright` | intentionally not overridden | Filter | Primary color inversion; NC auto-calculates |
| `--primary-invert-if-dark` | intentionally not overridden | Filter | Primary dark inversion; NC auto-calculates |

## Images & Resources

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--image-background` | intentionally not overridden | Image | Background image; admin/user configured |
| `--image-logoheader-custom` | intentionally not overridden | Image | Logo header flag; admin setting |
| `--image-logo` | intentionally not overridden | Image | Logo URL; admin setting |

## Assistant (AI)

| Nextcloud Variable | NL Design Mapping | Category | Notes |
|---|---|---|---|
| `--color-background-assistant` | intentionally not overridden | Assistant | AI assistant background; NC-specific feature |
| `--color-border-assistant` | intentionally not overridden | Assistant | AI assistant border gradient; NC-specific |
| `--color-element-assistant` | intentionally not overridden | Assistant | AI assistant element gradient; NC-specific |
| `--color-element-assistant-icon` | intentionally not overridden | Assistant | AI assistant icon gradient; NC-specific |

## Header & Navigation (NL Design specific)

These are `--nldesign-*` tokens that don't map to standard Nextcloud variables but are used in `overrides.css` for direct element styling:

| NL Design Token | Purpose | Notes |
|---|---|---|
| `--nldesign-color-header-background` | Header background color | Applied via element selectors in overrides.css |
| `--nldesign-color-header-text` | Header text color | Applied via element selectors |
| `--nldesign-header-border-bottom` | Header bottom border | Applied via element selectors |
| `--nldesign-color-nav-background` | Navigation background | Applied via element selectors |
| `--nldesign-color-button-primary-background` | Primary button background | Applied to .button-vue--vue-primary |
| `--nldesign-color-button-primary-text` | Primary button text | Applied to .button-vue--vue-primary |
| `--nldesign-color-button-primary-border` | Primary button border | Applied to .button-vue--vue-primary |
| `--nldesign-color-button-primary-hover` | Primary button hover | Applied to .button-vue--vue-primary:hover |
| `--nldesign-color-link` | Link color | Applied to a elements |
| `--nldesign-color-link-hover` | Link hover color | Applied to a:hover |
| `--nldesign-color-link-visited` | Visited link color | Applied to a:visited |

---

## Summary

Counts NEXTCLOUD variables. The Focus and Header & Navigation sections above
list `--nldesign-*` tokens that answer to no Nextcloud variable at all, so they
have nothing to count here — which is why the rows below do not add up to the
number of table rows in this file.

| Category | Total Variables | Mapped | Unmapped | Intentionally Not Overridden |
|---|---|---|---|---|
| Primary | 13 | 12 | 1 | 0 |
| Background | 9 | 3 | 0 | 6 |
| Placeholder | 2 | 2 | 0 | 0 |
| Text | 9 | 6 | 3 | 0 |
| Status (Error) | 6 | 5 | 1 | 0 |
| Status (Warning) | 5 | 2 | 3 | 0 |
| Status (Success) | 6 | 4 | 2 | 0 |
| Status (Info) | 5 | 2 | 3 | 0 |
| Border | 5 | 3 | 2 | 0 |
| Border Radius | 8 | 6 | 2 | 0 |
| Typography | 7 | 4 | 3 | 0 |
| Spacing | 4 | 0 | 4 | 0 |
| Layout | 11 | 0 | 0 | 11 |
| Animation | 3 | 2 | 0 | 1 |
| Shadow | 2 | 0 | 2 | 0 |
| Gradient | 2 | 0 | 0 | 2 |
| Special | 6 | 1 | 3 | 2 |
| Focus | 0 | 0 | 0 | 0 |
| Filter | 5 | 0 | 0 | 5 |
| Image | 3 | 0 | 0 | 3 |
| Assistant | 4 | 0 | 0 | 4 |
| **Total** | **115** | **52** | **29** | **34** |
