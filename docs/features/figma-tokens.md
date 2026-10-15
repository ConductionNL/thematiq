---
sidebar_position: 32
---

# Token sets and Figma

Take a token set to Figma, change it there, and bring it back. Thematiq writes and reads the two kinds of file Figma tools use: W3C design tokens and Tokens Studio files.

The OpenProject plugin `openproject-thematiq` uses the same paths, so a file downloaded from one product uploads into the other.

## Download for Figma

Go to **Administration settings**, **Thematiq**. Choose the set in the **Design token set** list, choose a format in the list next to **Download as design tokens**, and click the button. The rows under **Custom token sets** use the same format.

| Format in the list | `format` | File | For |
|---|---|---|---|
| W3C design tokens | `dtcg` | `{set}.tokens.json` | Any tool that reads W3C Design Tokens 2025.10. Colours are objects with a colour space. |
| Figma variables | `figma` | `{set}.tokens.json` | Variable import plugins. The same file, with every colour as `#rrggbb` or `rgba()` text. |
| Tokens Studio for Figma | `tokens-studio` | `{set}.tokens-studio.json` | The Tokens Studio plugin, with `$value` and `$type`. |
| Tokens Studio for Figma, older spelling | `tokens-studio-legacy` | `{set}.tokens-studio.json` | The same file with `value` and `type`. |

The address is `GET /apps/thematiq/settings/tokensets/{id}/dtcg?format={format}`. It is for administrators. Without `format` you get the W3C file. An unknown format gives 400.

The file holds what the set itself declares. The defaults under it and your token editor overrides stay out.

## Upload from Figma

Under **Custom token sets**, give the set a name and choose the file. The upload creates a custom token set. The result lists what was imported and what was left out, with the reason for each token.

Thematiq reads:

- a W3C design tokens file (`.tokens.json`), with colours as objects or as text;
- a Tokens Studio single file, the one with `$themes` and `$metadata`, in either spelling;
- a Tokens Studio file with two or more themes: each theme is a brand you can tick, as described under [Custom token sets](custom-token-sets.md).

A Tokens Studio folder with one file per token set is not read. Export a single file from the plugin.

The limits of every upload hold here too: 512 KB, no value that could leave its declaration, no remote `url()`. The file that is stored is written from the parsed values, never from the bytes you uploaded.

### What is left out, and the reason you see

| Reason | Meaning |
|---|---|
| `token-set-disabled` | The file's only theme has this token set switched off. |
| `dark-colours-not-read` | The token set holds the dark colours of a light and dark file. Thematiq makes the dark variant of a set itself. |
| `alias-cycle` | The token refers to itself through other tokens. |
| `value-not-a-css-value` | The value is a sum over references (`{a} * 2`), a reference to a token the file does not hold, a boolean, or empty. |
| `unmapped` | The path is not one thematiq knows. See the paths below. |

## The path of a token

The path is the CSS name, cut at its first two dashes. The rest stays one segment.

```
--nldesign-color-primary        nldesign.color.primary
--nldesign-color-primary-hover  nldesign.color.primary-hover
--nldesign-border-radius-large  nldesign.border.radius-large
--zuiddrecht-color-blue-500     zuiddrecht.color.blue-500
```

Reading goes the other way: join the segments with dashes and put `--` in front. A file whose tokens sit under `nldesign` is read by this rule alone. Figma drops the extension that carries the CSS name, and the rule does not need it.

The path is the same in all four formats.

- **W3C and Figma variables.** The path starts at the root of the file. A reference is `{nldesign.color.primary}`.
- **Figma variable name.** Variable tools turn the dots into slashes: `nldesign/color/primary`.
- **Tokens Studio.** The token set sits under its own key, the set id. The path inside it is the same, and a reference leaves the set key out: `{nldesign.color.primary}`. The file has one theme, with the set id as its `id`, that has the set `enabled`. `$metadata.tokenSetOrder` lists the set.

## The value in each format

| Kind | W3C design tokens | Figma variables | Tokens Studio |
|---|---|---|---|
| Colour | `color`, an object with `colorSpace`, `components` and `hex` | `color`, `"#3669a5"` or `"rgba(0, 0, 0, 0.5)"` | `color`, the same text |
| Size in `px` or `rem` | `dimension`, `{"value": 8, "unit": "px"}` | the same | `dimension`, `"8px"` |
| Font stack | `fontFamily`, a list | the same | `fontFamilies`, the CSS text |
| Font weight | `fontWeight`, a number | the same | `fontWeights`, `"700"` |
| Number | `number` | the same | `number`, `"1.5"` |
| Duration, easing | `duration`, `cubicBezier` | the same | `other`, the CSS text |
| Anything else, such as a shadow, a gradient or `50%` | in `$extensions.nl.conduction.thematiq.cssOnly`, by CSS name | the same | `other`, the CSS text |
| Reference to another token | `{path}` | `{path}` | `{path}` |

A colour also keeps the text it was written in: when that is not its hex, the W3C and Figma variables files carry it in `$extensions["nl.conduction.thematiq"].css`. An upload uses that text, or the plugin's own copy in `$extensions["nl.conduction.openproject-thematiq"].css`, while it still names the same colour as the object. Only names, hex, `rgb()`, `hsl()` and `hwb()` count; a `lab()`, `lch()` or `color()` value comes back as its sRGB hex.

On upload, a Tokens Studio type is read as its W3C type: `fontFamilies` as `fontFamily`, `fontWeights` as `fontWeight`, and `borderRadius`, `borderWidth`, `spacing`, `sizing` and `fontSizes` as `dimension`.

## The roles of the shell

`GET /apps/thematiq/api/shell-tokens` gives another product nineteen values ([Read the house style from another product](shell-tokens-api.md)). Each role reads these tokens of a set, in this order. The path holds in every format.

| Role | CSS name | Path in a Figma file | Kind |
|---|---|---|---|
| `primary` | `--nldesign-color-primary` | `nldesign.color.primary` | colour |
| `primary-deep` | `--nldesign-color-primary-hover` | `nldesign.color.primary-hover` | colour |
| `primary-light` | `--nldesign-color-primary-light` | `nldesign.color.primary-light` | colour |
| `action` | `--nldesign-color-action`, else the primary | `nldesign.color.action` | colour, optional |
| `action-deep` | `--nldesign-color-action-hover` | `nldesign.color.action-hover` | colour, optional |
| `link` | `--nldesign-color-link` | `nldesign.color.link` | colour |
| `accent` | `--nldesign-color-accent`, else `--nldesign-color-error` | `nldesign.color.accent`, else `nldesign.color.error` | colour |
| `ink` | `--nldesign-color-text` | `nldesign.color.text` | colour |
| `text` | `--thematiq-shell-text`, else `ink` and `muted` mixed half and half | `thematiq.shell.text` | colour, optional |
| `muted` | `--nldesign-color-text-muted` | `nldesign.color.text-muted` | colour |
| `line` | `--nldesign-color-border` | `nldesign.color.border` | colour |
| `soft` | `--nldesign-color-background-dark` | `nldesign.color.background-dark` | colour |
| `ground` | `--nldesign-component-content-surface-background-color`, else `--nldesign-color-background` | `nldesign.component.content-surface-background-color`, else `nldesign.color.background` | colour |
| `chip` | `--nldesign-color-background-hover` | `nldesign.color.background-hover` | colour |
| `field` | `--nldesign-color-border-dark` | `nldesign.color.border-dark` | colour |
| `radius` | `--nldesign-border-radius` | `nldesign.border.radius` | size |
| `radius-lg` | `--nldesign-border-radius-large` | `nldesign.border.radius-large` | size |
| `font-family` | `--nldesign-font-family` | `nldesign.font.family` | font stack |
| `menu` | `--thematiq-shell-menu`, else `--nldesign-component-navigation-background-color`, else `--nldesign-color-nav-background` | `thematiq.shell.menu` | colour |

A role whose token a set leaves out is derived, in this way:

| Role | Without its token |
|---|---|
| `primary-deep` | The primary with 30% black mixed in. The hover colour is only taken when it is darker than the primary. |
| `primary-light` | The primary with 90% white mixed in. |
| `action` | The primary. |
| `action-deep` | With an action colour: that colour with 15% black. Without one: the primary's hover colour, by the rule for `primary-deep`. |
| `link` | The primary. |
| `text` | `ink` and `muted` mixed half and half. |
| `field` | `line`. |
| `radius-lg` | `radius`. |
| Any other | The default the [endpoint page](shell-tokens-api.md) lists. |

## Files from the OpenProject plugin

A file the plugin downloads uploads into thematiq, in all three of its formats. The test suite does this with real plugin files and compares the eighteen roles.

- The plugin's `text` value sits at `thematiq.shell.text` (`--thematiq-shell-text`). Thematiq keeps that name and reads the `text` role from it.
- The plugin's `menu` value sits at `thematiq.shell.menu` (`--thematiq-shell-menu`). Thematiq keeps that name, so it is in the next download, and paints it on the app navigation column: an upload copies it to `--nldesign-color-nav-background` when the file names no navigation colour. The endpoint has `menu` as a role since 14 October 2026.
- A Tokens Studio file from the plugin has a light token set and a dark one, with a theme for each. Thematiq reads it as one house style, takes the light set and names the dark one as left out.
- The dark colours in a W3C file from the plugin sit in the plugin's own extension. Thematiq does not read them and does not write them. It makes the dark variant of a set itself.
- The plugin keeps the original text of a colour in its own extension. Thematiq reads it while it names the same colour, so the notation comes back too.

## What a round trip loses

Every shipped set is downloaded in each format, uploaded again and compared, token by token, in the test suite.

- **W3C design tokens.** Nothing.
- **Figma variables, straight back.** Nothing.
- **Figma variables, through a variable tool.** Figma's own import and export sample keeps colours and numbers. Sizes, fonts and everything in `cssOnly` do not come back. The uploaded set then holds those, and the rest falls back to the defaults. For `zuiddrecht`, 87 of 124 tokens come back.
- **Tokens Studio.** A CSS name that is also the start of another name has no place: a path cannot be a token and a group at once. Over the 63 shipped sets that is 1 of 38,998 declarations.
- **All formats.** A colour that refers to another colour is stored as the colour it points at. A colour outside sRGB is stored as its nearest sRGB colour, and the upload result says which.

Comments, the order of the declarations and the set's logo are not part of any of these files.

## The plugin's set file

Besides the Figma formats, thematiq writes and reads the plugin's own set file (`GET /apps/thematiq/settings/tokensets/{id}/set-file`, `thematiq-{set}.json`). Each role is stored under the CSS name in the table of the eighteen roles above, `menu` as `--thematiq-shell-menu`, and each vocabulary key with `--nldesign-` in front. See [Import and export](import-export.md).
