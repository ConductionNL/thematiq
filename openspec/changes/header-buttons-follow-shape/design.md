# Design

The boards draw the round shape and stay canon for it. The square shape has no board of its own: it is
the menu's square icon, `--border-radius-element`, applied to the other icons. Where this design and a
board differ, the build follows the board.

## How the shape reaches the page

`AppMenuService::inject()` puts the effective settings in the `thematiq/app-menu` initial state, and
`js/app-menu.js` already writes `data-thematiq-shape` on each open grid. The grid exists only while
the menu is open, so a top bar rule cannot read it.

The script also writes `data-thematiq-shape` on the page root (`document.documentElement`) when it
starts, with the same checked value (`settingsOf()`, round when the value is not allowed). The root
already carries the script's `data-thematiq-app-menu-peek`.

Why not server-side: Nextcloud gives an app no hook on `<html>`, `<body>` or `#header` attributes, and
an inline stylesheet per request would add a second source for one value. A flash cannot happen:
round is the shape without the attribute, and in the square shape the only icons visible before the
script runs are the apps menu button and the pins, which are rounded squares in both shapes. Every
other icon background shows only on hover or focus, or in the open menu, after the script ran. A
change in Menu settings reloads the page, so the attribute never goes stale.

## The rules

`css/app-menu.css`, loaded only with the workplace menu style:

```css
:root[data-thematiq-shape='square'] {
	--thematiq-icon-radius: var(--border-radius-element, 8px);
}
```

Every round icon background reads `var(--thematiq-icon-radius, <today's radius>)`:

| Element | Sheet | Round (today) |
|---|---|---|
| `.app-item__circle` of an app entry | app-menu.css | Nextcloud's |
| `.app-item__circle` of an external app | app-menu.css | 50% |
| `.thematiq-app-menu__create-icon` (the Create row and More) | app-menu.css | 50% |
| `.thematiq-app-pins__ghost > .thematiq-app-pins__icon` | app-menu.css | 50% |
| `#notifications`, `#contactsmenu` trigger | header-workplace.css | 22px |
| `#user-menu` trigger over the name chip | header-workplace.css | 24px |
| `.thematiq-quick-add__button` (the '+') | header-workplace.css | 22px |
| `.thematiq-quick-add__mark` | header-workplace.css | 50% |
| any other `.header-menu__trigger` or icon-only button at the end of the bar | app-menu.css, square only | Nextcloud's |

The app entry's circle and the other triggers keep Nextcloud's radius in the round shape, so their
rule applies under the square attribute only. The radius sits on the element, so its hover, focus,
active and open backgrounds all follow, and the focus outline follows the radius too.

Exclusions: the search field (`.unified-search-menu`), the person's avatar and the unread dot.

## Tests

- vitest `appMenuSettings.spec.js`: the root carries the shape, round for a value that is not allowed,
  nothing with the classic style.
- vitest `headerWorkplace.spec.js`: the listed rules read `--thematiq-icon-radius` with today's
  radius behind it; `app-menu.css` sets the variable under the square root only; no colour literal.
- e2e `header-buttons-follow-shape.spec.ts` on the Zuiddrecht set: shape square, hover the bell and
  the '+', open the menu, every icon background has the computed radius of `--border-radius-element`;
  shape round, the bell and the '+' are round again. Puts the administrator's value back.
