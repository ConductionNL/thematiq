# Design

The boards are in ConductionNL/design-system#1220 and #1221: werkplek/AppMenuVormgeving (the Theming
tab with "Show titles" and "Show titles in the top bar"), werkplek/AppMenuZonderTitels (the open menu
with titles off), werkplek/AppMenuBalkTitels (pins with their names), werkplek/AppMenuVastzetten (the
pin tooltip) and thematiq/TqLayout (the Menu card). The column count of
the icon-only grid follows AppMenuZonderTitels. They
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
| Pins in the top bar | follow `topBarTitles`, see below |
| Headings, More, Fewer, the Create row's More | unchanged text: they are not app entries |

The entry keeps everything else it has: the drag handle behaviour, Alt with the arrow keys, Alt+P, and
the live region messages. `nameOf()` reads the name from the entry's `.app-item__label` text today, so
it moves to a source that survives icon-only drawing: the entry's `aria-label`, or the label text when
titles are on.

The visible name is removed, not hidden with CSS: an icon-only entry has no text node a screen reader
could read twice next to its `aria-label`.

## The tooltip

One tooltip element per menu, `role="tooltip"`, reused by every entry and, with `topBarTitles` off,
by every pin in the top bar, and moved next to the entry it
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

## Titles in the top bar

Ruben wants two separate choices (27 November 2026): titles in the cards of the apps menu (`titles`,
on) and titles in the top bar (`topBarTitles`, off). A menu can be icon-only while the bar shows names,
and the other way round.

- **Workplace top bar.** With `topBarTitles` on, each pin draws its icon and the app's name next to it,
  in the bar's text style, up to a maximum width with an ellipsis. The visible name is the accessible
  name, so the pin needs no `aria-label` and no tooltip; only a name cut off by the ellipsis gets the
  tooltip. With it off, the pins stay as today (icon-only, the name as accessible name) and get the
  tooltip of the apps menu, placed under the pin.
- **Nextcloud's own apps menu.** On a page where thematiq does not draw the workplace apps menu (the
  classic style, or a house style without the workplace top bar), `topBarTitles` on loads
  `css/show-menu-labels.css`, exactly what the older option did, on Nextcloud 32 and 33.
- **Never both.** On a page with the workplace apps menu `show-menu-labels.css` is not loaded: its
  `.app-item__label` rules would also reach the workplace menu's entries and put names back into an
  icon-only menu.

### A Theming key, the administrator's

`topBarTitles` follows `titles`: `DEFAULTS` false, `SWITCHES`, not `PERSONAL`. The workplace pins and
Nextcloud's own menu both read the administrator's value. It is set in two places, as the boards draw:
Menu settings, tab Theming (For everyone only), directly under "Show titles"; and the Menu card of the
admin page, where the older option was, which is also reachable when the classic style is on. It does
not depend on `titles` and does not disable Show payoff.

### The older option moves into it

- A repair step, `MigrateMenuLabelsToTopBarTitles`, moves `show_menu_labels` once on upgrade (`'1'`
  true, `'0'` false, only where `menu.topBarTitles` is not stored) and removes it. A repair step,
  not a first-page move, because with the classic style no menu script runs for a person.
- Until it has run, `AppMenuSettings` reads `show_menu_labels` while `menu.topBarTitles` is absent, as
  it reads `app_menu_order` for the order.
- `POST /settings/menulabels` and `SettingsController::setMenuLabelsSetting()` go. The switch saves
  through `POST /apps/thematiq/app-menu/defaults`.
- The OCS capability field `showMenuLabels`, the bundle key `showMenuLabels` and the theme version
  field `showMenuLabels` keep their names, so consumers and older bundles keep working, and carry
  `menu.topBarTitles`. No `bundleVersion` bump: the bundle's shape does not change.
- `MigrateAppConfigKeys` still names `show_menu_labels` for its old nldesign move; that step runs first
  and leaves the value where the new step finds it.

## Focus

An icon-only entry keeps the menu's visible focus ring (WCAG 2.4.7, and 2.4.11 not obscured: the
tooltip is placed away from it). The target stays at least the icon chip's size, 32 or 40 px plus
padding, above the 24 px of WCAG 2.5.8.

## The Menu settings dialog

Theming, in the look section: "Show items as tiles", then "Show titles" with its help, then "Show
titles in the top bar" with its help, then "Show payoff". The preview draws its four items without names when titles is off and adds nothing for
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
| `titlesHelp` | The name of the app under or next to the icon. Off: icons only, the name appears as a tooltip. | De naam van de app onder of naast het pictogram. Uit: alleen pictogrammen, de naam verschijnt als tooltip. |
| `topBarTitles` | Show titles in the top bar | Titels in de bovenbalk tonen |
| `topBarTitlesHelp` | The name next to the icon of every pinned app in the top bar. Off: icons only, the name appears as a tooltip. | De naam naast het pictogram van elke vastgezette app in de bovenbalk. Uit: alleen pictogrammen, de naam verschijnt als tooltip. |
| `payoffNeedsTitles` | Only with Show titles. | Alleen met Titels tonen. |
| `withoutTitles` | without titles | zonder titels |
| existing string | (opens in a new tab) | as translated today |

The boards' copy wins where it differs.
