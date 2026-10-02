---
sidebar_position: 4.1
---

# Token Editor

The **Custom Token Overrides** section in the admin settings lets you fine-tune individual Nextcloud CSS tokens independently of your selected token set. Overrides apply as a live preview instantly, and are saved persistently when you click **Save overrides**.

## Overview

The token editor is located in the **NL Design System Theme** section of the Appearance admin settings. It consists of:

- **4 category tabs** grouping the 53 editable tokens by area
- **Per-row editing** with a color picker, hex input field, and reset button
- **Custom value badge** indicating which tokens have been manually overridden
- **Save overrides** button to persist all current values

![Custom Token Overrides section showing the Download/Upload buttons, 4 category tabs, and the first token row with a custom value badge](../img/import-export-buttons.png)

## Category Tabs

Tokens are grouped into 4 tabs. Click a tab to switch between categories. The active tab is highlighted.

The tokens listed below are Nextcloud's own base tokens. They are locked until you tick **Also edit Nextcloud's base tokens** above the tabs and confirm the warning: each one is read by far more of Nextcloud than any single component, so changing it changes much more than the part you are looking at. To change one component, use that component's own rows in the preview instead. The base tokens lock again when you untick the box or leave the page.

### Login Page & Branding (12 tokens)

Controls the primary brand colors used throughout the interface — buttons, links, highlights, and the login page header.

![Login page & Branding tab showing 12 primary color tokens with color pickers and hex inputs](../img/token-editor-login-tab.png)

| Token | CSS Variable | Description |
|-------|-------------|-------------|
| Primary color | `--color-primary` | Main brand color for buttons and accents |
| Primary text color | `--color-primary-text` | Text on primary-colored backgrounds |
| Primary hover color | `--color-primary-hover` | Hover state for primary elements |
| Primary element color | `--color-primary-element` | Color for active/selected elements |
| Primary element hover | `--color-primary-element-hover` | Hover state for primary elements |
| Primary element text | `--color-primary-element-text` | Text on primary elements |
| Primary light | `--color-primary-light` | Light tint of primary color |
| Primary light hover | `--color-primary-light-hover` | Hover state for light primary |
| Primary light text | `--color-primary-light-text` | Text on light primary backgrounds |
| Primary element light | `--color-primary-element-light` | Light element color |
| Primary element light text | `--color-primary-element-light-text` | Text on light element backgrounds |
| Primary element light hover | `--color-primary-element-light-hover` | Hover state for light elements |

### Content Area (18 tokens)

Controls background colors, borders, and border radii — the structural appearance of the interface.

![Content area tab showing background and border color tokens](../img/token-editor-content-tab.png)

Covers: main background, dark/darker backgrounds, placeholder colors, border colors, border radius values (small, element, body container), scrollbar color.

### Buttons & Status (15 tokens)

Controls error, warning, success, and info state colors — used in alerts, status badges, validation messages, and notification banners.

![Buttons & Status tab showing error, warning, success, and info color tokens](../img/token-editor-buttons-tab.png)

Covers: error color/hover/RGB/element/border, warning color/RGB/element, success color/RGB/element/border, info color/element, and favorite star color.

### Typography (8 tokens)

Controls text colors and the font family stack.

![Typography tab showing text color tokens and font family input](../img/token-editor-typography-tab.png)

| Token | CSS Variable | Description |
|-------|-------------|-------------|
| Main text color | `--color-text` | Primary body text color |
| Text max contrast | `--color-text-maxcontrast` | Maximum contrast text (accessible variant) |
| Text light | `--color-text-light` | Light/inverted text |
| Text lighter | `--color-text-lighter` | Lighter secondary text |
| Text error | `--color-text-error` | Error state text color |
| Text success | `--color-text-success` | Success state text color |
| Text warning | `--color-text-warning` | Warning state text color |
| Font family | `--font-face` | Full font family stack |

## Token Row Layout

Each token row contains:

- **Token name** — human-readable label (e.g., "Primary color")
- **CSS variable** — the actual CSS custom property name (e.g., `--color-primary`)
- **Color picker** — click to open a native color picker (color tokens only)
- **Hex input** — type a hex value directly (e.g., `#c00000`)
- **Reset button** (↺) — undoes unsaved edits: puts back the value you last saved, or the token set's value if you never saved one

The color picker and hex input are always in sync — changing one updates the other instantly.

## Custom Value Badge

When a token has been manually overridden (saved to `custom-overrides.css`), a small colored dot badge appears next to the token name.

![Primary color row showing the "Custom value" badge dot next to the token name](../img/token-editor-custom-badge.png)

The badge color matches the overridden value, making it easy to identify which tokens have been customized at a glance.

To stop overriding a token, empty its field and click **Save overrides** — the token returns to the token set's value. The reset button (↺) only undoes edits you have not saved yet.

## Live Preview

Every change you make is shown immediately in the **Preview** section above the editor. It is applied to the preview only: the rest of Nextcloud keeps the saved theme until you save.

Saving applies it — with **Save overrides** on the active theme, or by saving the changes as a new theme and selecting it. Reloading the page without saving reverts all changes.

### Nextcloud version

Components that Nextcloud 32 to 35 draw differently have a **Nextcloud version** selector in the preview. It starts on the version your instance runs, and each component keeps its own choice. Components that every version draws the same have no selector. Under the preview it lists what the chosen version does differently for that component. The header and the note cards are also drawn as the chosen version, and the note cards show the rows that only that version reads. Other components keep the look of the version you run, and the preview says so while another version is chosen.

### Header app icons on Nextcloud 32 and 33

On Nextcloud 32 and 33 the app menu in the header (Dashboard, Files, Photos, …) is drawn with white icon images. **Header glyphs** colours the icons that are drawn as glyphs — search, notifications, contacts and the account menu — but it cannot recolour images, so the app icons stay white whatever the header background is. Keep that in mind when you give the header a light background on these versions.

From Nextcloud 34 the app menu is a glyph as well, and follows **Header glyphs**.

### Toast and note card text

Toasts and note cards have a background and a text colour per type (success, error, warning, info). When a theme sets a background but no text colour, the text is chosen automatically: white on a dark background, black on a light one. When the theme sets neither, toasts and note cards look exactly as Nextcloud draws them.

From Nextcloud 33 the note card colour is the card's fill. On Nextcloud 32 it is the stripe on the left, and the fill is a light tint mixed from a separate **fill (Nextcloud 32)** colour. Select **32** above the note card preview to see and set those; they are hidden for 33 and later, where Nextcloud does not read them. The automatic text colour compares against the card colour, so on Nextcloud 32 pick the text colour yourself if the automatic one does not suit the light fill.

## Preview your own component

Building a component for your own portal or app? See it in the house style before you publish anything. Every tab of the preview ends with the chip **Your component**.

1. Paste the component's HTML in **HTML** and its CSS in **CSS**. Read the house style through its tokens, for example `background: var(--nldesign-color-primary)`.
2. The frame under the fields draws it with the active token set, including edits you have not saved yet. Change a colour in the token list and the frame follows at once.
3. The token list beside the preview shows the tokens your code reads. A token the editor can set has its editor row. Any other token shows its value, read-only.
4. When your code reads a text token and its base, such as `--nldesign-color-primary-text` on `--nldesign-color-primary`, the stage shows their contrast against WCAG AA.
5. **Dark theme** shows the frame with the set's dark values. Nextcloud's own variables keep the theme you are using.
6. Give it a name and click **Save component**. It gets a chip of its own, and a link: `#preview=content/own-{name}`. You can keep 20 components of at most 64 KB each. Only administrators see them, and saving one changes nothing for other users.

Three locks keep pasted code harmless. The frame runs no scripts and cannot submit forms, open windows or move this page. It loads nothing from outside this server. And the code is cleaned before it is drawn: scripts, event handlers, external addresses and elements outside a fixed list are removed, and the stage says what it removed, for example "Removed: 1 script, 2 event handler".

## Saving Overrides

Click **Save overrides** at the bottom of the token editor to persist all current values.

![Token editor bottom showing multiple token rows and the Save overrides button](../img/token-editor-save-button.png)

On save:
- Only tokens that differ from the token set's defaults are written to `custom-overrides.css`
- Tokens at their default value are excluded from the saved file
- A success toast notification confirms the save

The saved CSS file is served at every page load, so overrides persist for all users and survive browser refreshes.
