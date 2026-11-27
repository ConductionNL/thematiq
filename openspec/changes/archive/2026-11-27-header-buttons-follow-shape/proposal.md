---
kind: code
---

# Header buttons follow the shape

## Why

Ruben's review of 27 November 2026, item 5. Menu settings has a `shape` setting, round or square
(`AppMenuSettings`, key `shape`). Today it reaches only the app icons of the open apps menu:
`css/app-menu.css` gives `.app-item__circle` a rounded square under
`.app-menu__grid[data-thematiq-shape='square']`. Everything else with an icon background stays round:

- in the apps menu, the Create row (New document, New spreadsheet, New presentation, More) and the
  external apps;
- in the top bar, the hover, focus and active background of the Create button ('+'), the bell, the
  contacts menu, the person's menu and the other icon buttons apps add to the end of the bar;
- the Create button's own menu, whose item marks are circles, and the chip of a pin being dragged.

And the contacts menu shows no hover background at all: Nextcloud draws its header menu triggers
without one.

So a workplace set to square shows square app icons next to round buttons and round Create icons, in
the same menu and the same bar.

## What changes

- **One shape for every icon.** With `shape` square, every icon background in the apps menu (app
  entries, the Create row and its More, external apps) and every icon button of the top bar takes a
  rounded square with the radius the menu's icons already use, `--border-radius-element`. The bar's
  buttons take it for their hover, focus-visible and active background alike.
- **The contacts menu gets its hover.** The bell and the contacts menu draw the hover, focus and open
  background of the bar's other icon buttons, and a focus outline, in either shape.
- **Round stays as it is.** With `shape` round nothing changes, the apps menu button and the pins
  included, which are rounded squares today in both shapes.
- **The shape at page level.** `js/app-menu.js` marks the page root with the effective shape, so a
  rule for the top bar can follow it, not only a rule inside the open menu.

## Not in this change

- The search field. It is a field with a label, drawn as a pill on the DqKop board, not an icon
  button.
- Avatars. The person's initials and Nextcloud's avatars stay round: a face, not an icon.
- The classic menu style. With `style` classic thematiq leaves Nextcloud's menu and bar alone, and
  the shape has no meaning there.
- A new setting. The existing `shape` key covers it.

## Boards

The boards draw the round shape: werkplek/DqKop, werkplek/AppMenu, werkplek/AppMenuAlles,
werkplek/AppMenuSnelacties, werkplek/AppMenuVastzetten and werkplek/AppMenuInstellingen, whose
Theming tab shows the two shapes. See `screens.md`.

## Impact

- `js/app-menu.js`: the shape on the page root
- `css/app-menu.css`: one radius variable for the square shape; the Create row, the external apps
  and the dragged pin read it
- `css/header-workplace.css`: the bell, the contacts menu, the person's menu, the Create button and its
  marks read it, with today's radius behind it
- tests: vitest `appMenuSettings.spec.js`, `headerWorkplace.spec.js`; e2e new
  `tests/e2e/spec-coverage/header-buttons-follow-shape.spec.ts`
- Risk: an app that adds its own icon button to the bar gets the square too. That is the rule
  ("every icon button"); a button that is not icon-only keeps its shape.
