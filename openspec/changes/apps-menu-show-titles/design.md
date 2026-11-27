# Design

The boards are on design-system branch `design/thematiq-show-titles`: werkplek/AppMenuVormgeving (the
Theming tab with "Show titles") and werkplek/AppMenuZonderTitels (the open menu with titles off). They
are canon; where this design and a board differ, the build follows the board, including the size of an
icon-only entry.

## One Theming key

`AppMenuSettings` gets `titles`, `true` in `DEFAULTS` and listed in `SWITCHES`, so the existing check
(true or false, null follows the default again) covers it. It is not in `PERSONAL`.

Why not personal: the apps-menu-settings spec makes every Theming choice the administrator's. The
Theming tab shows only for an administrator choosing For everyone, and a person's save of a theming
key is refused with 400 naming it. `titles` follows that rule, so its precedence is the administrator's
value over the shipped one. Reset to default for everyone removes `menu.titles` with the other keys.

`titles` reaches the menu through `effective()` and the `thematiq/app-menu` initial state like every
other key. Nothing is migrated: an instance without `menu.titles` gets `true`, today's menu.

## Payoff depends on titles

A payoff without a title reads as a stray line, so with `titles` off the menu draws no payoff, whatever
`payoff` holds. The stored `payoff` is left alone: the server does not rewrite it, and the dialog saves
the value the switch had. Switching titles back on brings the payoff back as it was. In the dialog the
Show payoff switch is disabled (`disabled` on the `role="switch"` input, so it stays in the reading
order) with the note "Only with Show titles." under it.

## The icon-only entry

`js/app-menu.js` sets `data-titles="false"` on the menu while `titles` is off and draws each entry as
the icon alone. Per section:

| Section | Off |
|---|---|
| Nextcloud apps | icon only; the name is the link's `aria-label` and the tooltip |
| Create row | each create chip icon only; its label is the `aria-label` and the tooltip |
| External apps | icon only; `aria-label` is the title plus "(opens in a new tab)", the tooltip is the title |
| Pins in the top bar | unchanged: already icon-only with the app's name as accessible name |
| Headings, More, Fewer, the Create row's More | unchanged text: they are not app entries |

The entry keeps everything else it has: the drag handle behaviour, Alt with the arrow keys, Alt+P, and
the live region messages. `nameOf()` reads the name from the entry's `.app-item__label` text today, so
it moves to a source that survives icon-only drawing: the entry's `aria-label`, or the label text when
titles are on.

The visible name is removed, not hidden with CSS: an icon-only entry has no text node a screen reader
could read twice next to its `aria-label`.

## The tooltip

One tooltip element per menu, `role="tooltip"`, reused by every entry and moved next to the entry it
names. It holds the same text as the accessible name (without "(opens in a new tab)"), so it is not
referenced with `aria-describedby`: that would make a screen reader read the name twice.

- **Shows** on pointer hover after a short delay (300 ms, as Nextcloud's own tooltips) and at once on
  keyboard focus (`:focus-visible`).
- **Stays** while the pointer is on the entry or on the tooltip itself, and while the entry has focus
  (WCAG 1.4.13 hoverable and persistent). It never covers the focused entry or its focus ring: it
  sits below the entry, or above it on the last visible row.
- **Dismissed** with Escape without moving focus (WCAG 1.4.13 dismissible). Escape with no tooltip
  showing keeps doing what it does today. The order is: a running walkthrough first (as today), then
  the tooltip, then the menu.
- **Hidden** when the entry loses hover and focus, when a drag starts, and when the menu closes.
- Colours come from the Nextcloud tokens (`--color-main-text` on `--color-main-background` with a
  border), so dark mode and the house styles follow; text contrast at least 4.5:1.

## Focus

An icon-only entry keeps the menu's visible focus ring (WCAG 2.4.7, and 2.4.11 not obscured: the
tooltip is placed away from it). The target stays at least the icon chip's size, 32 or 40 px plus
padding, above the 24 px of WCAG 2.5.8.

## The Menu settings dialog

Theming, in the look section: "Show items as tiles", then "Show titles" with its help, then "Show
payoff". The preview draws its four items without names when titles is off and adds nothing for
payoff. The preview sentence swaps its payoff part: "Round, large icons in the brand colour, without
titles and without tiles." with titles off; with titles on it reads as today.

## The setup wizard

The apps menu step of first-time-setup (REQ-TQFS-010) offers Show payoff, a Theming choice, so it gets
"Show titles" before it, with the same rule: Show payoff disabled while titles is off. Apply stores
`menu.titles` as the administrator's default. `SetupRequest::MENU_KEYS` names `titles`. The step is not
built yet; it is built with this switch in it.

## Changes that touch the same requirements

quick-actions-toggles modifies the requirements "Menu settings are admin defaults with per-user
overrides", "The menu is drawn from the settings" and "The Menu settings dialog". To stay out of its
way this change only adds requirements. The new requirement states the shipped value, the scope and
the dialog place of `titles`; whichever change archives last can fold `titles` into the list of shipped
values.

## Labels

| key | English | Dutch |
|---|---|---|
| `titles` | Show titles | Titels tonen |
| `titlesHelp` | The app's name next to its icon. Off: icons only, the name shows on hover and focus. | De naam van de app naast het icoon. Uit: alleen iconen, de naam verschijnt bij aanwijzen en focus. |
| `payoffNeedsTitles` | Only with Show titles. | Alleen met Titels tonen. |
| `withoutTitles` | without titles | zonder titels |
| existing string | (opens in a new tab) | as translated today |

The boards' copy wins where it differs.
