---
sidebar_position: 32
---

# OpenWOO reference set

`css/tokens/openwoo.css` is the OpenWOO theme authored by hand in Thematiq's vocabulary. It is the reference the converter's output for the OpenWOO theme is compared with. Authored on 22 November 2026.

## How it was made

The set is a component playground export. The playground started from `vng`, the role layer every brand file fills from. Every value with a brand meaning was then overridden with an OpenWOO value. The overrides are recorded in `scripts/playground/openwoo.overrides.json`.

`node scripts/export-playground-set.mjs scripts/playground/openwoo.overrides.json --out css/tokens/openwoo.css` runs the playground's own `exportCss()` over those overrides. The file is the one the "Export as token set" button downloads for the same set and overrides. `tests/vitest/openwooReferenceSet.spec.js` checks that the committed file equals a fresh export, and that the vocabulary audit rates it complete.

Five tokens keep the `vng` value, because OpenWOO publishes nothing for them: the two animation timings, the pill and rounded radii, and `--nldesign-color-text-light`.

## Where the values come from

The source is OpenWOO's own styling: `@conduction/theme` 2.1.0, `other/openwoo-design-tokens/src`, read on 22 November 2026. OpenWOO's licence there allows its house style only within OpenWOO, so the set ships restricted: an administrator confirms first, as for any other organisation's house style. The licence text is `css/tokens/openwoo.LICENSE.md`.

| Role | Value | OpenWOO token | Choice |
|---|---|---|---|
| Primary, link, buttons | #23845c | `openwoo.color.green.33` | OpenWOO's primary. White text on it reaches 4.64:1. |
| Hover | #1b6748 | `openwoo.color.green.25` | OpenWOO's primary hover. |
| Primary light, light hover | #e9f3ef, #d3e6de | none | The primary at 10% and 20% on white. OpenWOO publishes no green tint. |
| Header | #e8eff6, text #11304e | `utrecht.page-header` | OpenWOO's ice blue page header with navy text. |
| Header edge | 1px solid #cbdbeb | `openwoo.color.blue.86` | The deeper ice blue, so the light header stays apart from the white page. |
| Text, muted text | #000000, #4f4f4f | `utrecht.document.color`, `openwoo.color.grey.31` | Black body text as OpenWOO's document. The muted grey reaches 8.2:1 on white. |
| Borders | #d1d1d1, #7a7a7a | `utrecht.separator.color`, `utrecht.form-input.border-color` | Light lines as OpenWOO's separator, dark lines as its input edge. |
| Surfaces | #f2f2f2, #e6e6e6, hover #e8eff6 | `openwoo.color.grey.95`, `grey.90`, `blue.94` | OpenWOO's greys, and its ice blue for hover. |
| Error | #dc3545, hover #721c24 | `openwoo.color.error`, `alert-error` | OpenWOO's error red and its dark alert red. |
| Warning | #856404 | `openwoo.color.alert-warning` | OpenWOO's warning yellow #ffc107 reaches 1.6:1 on white, too low for text. Its own warning text colour is used instead. |
| Success | #155724 | `openwoo.color.alert-succes` | OpenWOO's green #28a745 reaches 3.1:1 on white. Its own success text colour is used instead. |
| Info | #004085 | `openwoo.color.alert-info` | OpenWOO points info at the primary green. Nextcloud would then show information as success, so OpenWOO's info text blue is used. |
| Favourite | #ffc107 | `openwoo.color.warning` | OpenWOO's yellow, for the star icon only. |
| Focus | rgba(17, 48, 78, 0.5) | `utrecht.focus.outline-color` | OpenWOO's navy focus ring. |
| Radii | 3px, small 2px, large 6px | `utrecht.button.border-radius` | OpenWOO's 3px controls. Cards get a little more. |
| Typeface | Arial, sans-serif | `openwoo.typography.sans-serif` | OpenWOO's own choice. |
| Logo | none | none | The package ships no OpenWOO logo file. |

## Compared with the converter

Made with `node scripts/diff-converter-reference.mjs openwoo.tokens.json --slug openwoo --reference css/tokens/openwoo.css`. The input is every `*.tokens.json` under `other/openwoo-design-tokens/src`, merged into one Style Dictionary file. Tokens only the converter writes are counted, not listed.

| Verdict | Tokens |
|---|---|
| same | 19 |
| different | 21 |
| reference only | 8 |
| converter only | 3 |

| Token | Reference | Converter | Verdict |
|---|---|---|---|
| `--nldesign-animation-quick` | 100ms |  | reference only |
| `--nldesign-animation-slow` | 300ms |  | reference only |
| `--nldesign-border-radius` | 3px | 8px | different |
| `--nldesign-border-radius-large` | 6px | 8px | different |
| `--nldesign-border-radius-pill` | 999px | 100px | different |
| `--nldesign-border-radius-rounded` | 999px | 28px | different |
| `--nldesign-border-radius-small` | 2px | 4px | different |
| `--nldesign-color-background` | #ffffff |  | reference only |
| `--nldesign-color-background-dark` | #f2f2f2 | #e8eff6 | different |
| `--nldesign-color-background-darker` | #e6e6e6 | #e6e6e6 | same |
| `--nldesign-color-background-hover` | #e8eff6 | #cbdbeb | different |
| `--nldesign-color-border` | #d1d1d1 | #7a7a7a | different |
| `--nldesign-color-border-dark` | #7a7a7a | #7a7a7a | same |
| `--nldesign-color-button-primary-background` | #23845c |  | reference only |
| `--nldesign-color-button-primary-border` | #23845c |  | reference only |
| `--nldesign-color-button-primary-hover` | #1b6748 |  | reference only |
| `--nldesign-color-button-primary-text` | #ffffff |  | reference only |
| `--nldesign-color-error` | #dc3545 | #d70d0d | different |
| `--nldesign-color-error-hover` | #721c24 | #c20c0c | different |
| `--nldesign-color-error-rgb` | 220, 53, 69 | 215, 13, 13 | different |
| `--nldesign-color-favorite` | #ffc107 | #f9cf00 | different |
| `--nldesign-color-focus` | rgba(17, 48, 78, 0.5) | rgba(17, 48, 78, 0.5) | same |
| `--nldesign-color-focus-rgb` | 17, 48, 78 | 17, 48, 78 | same |
| `--nldesign-color-header-background` | #e8eff6 | #e8eff6 | same |
| `--nldesign-color-header-text` | #11304e | #11304e | same |
| `--nldesign-color-info` | #004085 | #23845c | different |
| `--nldesign-color-info-rgb` | 0, 64, 133 | 35, 132, 92 | different |
| `--nldesign-color-link` | #23845c | #23845c | same |
| `--nldesign-color-link-hover` | #1b6748 | #1b6748 | same |
| `--nldesign-color-link-visited` | #23845c | #23845c | same |
| `--nldesign-color-nav-background` | #ffffff | #ffffff | same |
| `--nldesign-color-placeholder-dark` | #d1d1d1 | #e6e6e6 | different |
| `--nldesign-color-placeholder-light` | #f2f2f2 | #f2f2f2 | same |
| `--nldesign-color-primary` | #23845c | #23845c | same |
| `--nldesign-color-primary-hover` | #1b6748 | #1b6748 | same |
| `--nldesign-color-primary-light` | #e9f3ef | #e9f3ef | same |
| `--nldesign-color-primary-light-hover` | #d3e6de | #d3e6de | same |
| `--nldesign-color-primary-text` | #ffffff | #ffffff | same |
| `--nldesign-color-success` | #155724 | #39870c | different |
| `--nldesign-color-success-rgb` | 21, 87, 36 | 57, 135, 12 | different |
| `--nldesign-color-text` | #000000 | #000000 | same |
| `--nldesign-color-text-light` | #ffffff | #ffffff | same |
| `--nldesign-color-text-muted` | #4f4f4f | #767676 | different |
| `--nldesign-color-warning` | #856404 | #a65600 | different |
| `--nldesign-color-warning-rgb` | 133, 100, 4 | 166, 86, 0 | different |
| `--nldesign-font-family` | Arial, sans-serif | Arial, sans-serif | same |
| `--nldesign-header-border-bottom` | 1px solid #cbdbeb |  | reference only |
| `--nldesign-logo-url` | none | url('../../img/logos/openwoo.svg') | different |

The converter agrees on the primary, its hover and its tints, the header, the links and the typeface. It differs where OpenWOO publishes nothing for a Nextcloud role: the converter then fills in values of its own (error, success, warning, radii). It also differs where the reference chose a readable colour over OpenWOO's literal one (warning, success, info, muted text). The converter writes no button colours and no header edge for this theme.
