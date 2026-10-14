---
sidebar_position: 31
---

# Read the house style from another product

You run another product next to Nextcloud and want it to look the same. Read the active house style here: nineteen values and a logo cover a header, buttons, links and panels.

The OpenProject plugin `openproject-thematiq` reads this endpoint.

## The endpoint

```
GET /apps/thematiq/api/shell-tokens
```

You need no account and no session. The answer holds theme values only, the same ones every visitor already gets as CSS on the login page. A caller without a session may ask 120 times a minute.

```json
{
  "version": 1,
  "id": "zuiddrecht",
  "name": "Gemeente Zuiddrecht",
  "revision": "bc30fd0d66100acf",
  "tokens": {
    "primary": "#3669A5",
    "primary-deep": "#234A78",
    "primary-light": "#EAF0F7",
    "action": "#3669A5",
    "action-deep": "#234A78",
    "link": "#3669A5",
    "accent": "#CC0000",
    "ink": "#1B1C1D",
    "text": "#3d3f43",
    "muted": "#5E6168",
    "line": "#E4E6EA",
    "soft": "#EDEFF2",
    "ground": "#F5F6F8",
    "chip": "#F0F1F3",
    "field": "#8A929E",
    "radius": "8px",
    "radius-lg": "12px",
    "font-family": "'Fira Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Cantarell, Ubuntu, 'Helvetica Neue', Arial, sans-serif"
  },
  "logo": {
    "url": "https://cloud.example.org/custom_apps/thematiq/img/logos/zuiddrecht.svg",
    "inverse_url": "https://cloud.example.org/custom_apps/thematiq/img/logos/zuiddrecht-dark.svg"
  }
}
```

- `id` and `name` are the token set the instance wears. A set for one group, or a set in preview, is never answered.
- `revision` changes when any value below it changes, and only then.
- `tokens` always holds all nineteen roles. None is ever `null` or empty. `menu` was added on 14 October 2026; a reader that knows only the first eighteen can ignore it.
- `logo.url` and `logo.inverse_url` are absolute URLs, or `null` when there is no such logo.

## The nineteen roles

| Role | Use it for |
|---|---|
| `primary` | The header bar and other brand surfaces |
| `primary-deep` | The darker step of `primary`: hover, borders |
| `primary-light` | A light tint of `primary`: selected rows, soft panels |
| `action` | The main button, and only that. The set's action colour when it has one, else its primary |
| `action-deep` | The main button on hover, and its border |
| `link` | Links and link-coloured ids |
| `accent` | The second brand colour. Red in many sets, so keep it for emphasis |
| `ink` | Headings and the strongest text |
| `text` | Body text |
| `muted` | Secondary text |
| `line` | Dividers and row lines |
| `soft` | A grey a step darker than `chip`: secondary buttons, table headers |
| `ground` | The page behind cards |
| `chip` | Chips, tags and hovered rows |
| `field` | Borders of inputs |
| `radius` | Corners of buttons, chips and cards |
| `radius-lg` | Corners of inputs, menus and dialogs |
| `font-family` | The font stack |
| `menu` | The background of the main menu: the app navigation column in Nextcloud. White unless the set colours it |

The colours are CSS colours, the two radii are CSS lengths, and `font-family` is a CSS font stack. The font files stay on Nextcloud: load the family yourself, or let the stack fall through to a system font.

A value an administrator set in the token editor wins over the token set's own value. A role the set does not fill is derived from a role next to it, or takes the stock Nextcloud value.

## The logo

`logo.url` is the logo of the active set. When the set has none, it is the logo uploaded in Nextcloud's theming settings. `logo.inverse_url` is the logo for a dark surface, such as a header in `primary`. Expect `null` for either and keep your own logo as the fallback.

## Ask again without downloading again

The answer carries its revision as an `ETag`. Send it back and you get `304` with no body while nothing changed:

```
GET /apps/thematiq/api/shell-tokens
If-None-Match: "bc30fd0d66100acf"
```

Read the document when your product starts, keep it, and ask again every few minutes with `If-None-Match`. Do not read it on every page render.

## After an update

The path is new in this release. An instance that is updated the normal way answers on it at once. When the code was replaced without an update, Nextcloud keeps its route list for up to an hour and answers `404` until then. Restart the PHP workers to make it answer at once.

Next, request the document from your test instance and map the roles onto your product's own theme settings.
