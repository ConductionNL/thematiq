# Design

The boards are werkplek/AppMenuConfiguratie and thematiq/TqLayout from ConductionNL/design-system#1220,
with the switch next to the create switches. They are canon; where this design and a board differ, the build follows
the board.

## One personal key

`AppMenuSettings` gets `showContactsMenu`, `true` in `DEFAULTS`, in `SWITCHES` (true or false, null
follows the default again) and in `PERSONAL`. Being personal gives it the order of precedence that
`showCreateButton` has: a person's user value wins over the administrator's app value, which wins over
the shipped value. Reset to default for a person removes it with the other personal keys; Reset to
default for everyone removes the administrator's value. Nothing is migrated: without a stored value
the bar keeps its contacts menu.

Why personal: the same decision as the create switches (Ruben, 27 November 2026). It changes only that
person's own top bar.

## How the button leaves the bar

The contacts menu is Nextcloud's own (`#contactsmenu`, mounted by Nextcloud's core script). thematiq
does not own it, so it does not remove the node: core mounts into it, and a missing mount point risks a
script error on every page. Instead, with the effective value off, `AppMenuService` adds one conditional
stylesheet, `css/top-bar-no-contacts.css`, with the person's settings it already reads:

```css
body:not(#body-login) #header #contactsmenu { display: none !important; }
```

`display: none` takes the element out of the layout, the focus order and the accessibility tree at
once, so there is no focusable element and nothing for a screen reader. Because the stylesheet comes
with the page, the button never shows and then disappears. The rules in `css/header-workplace.css` that
place the bell and the contacts menu keep the bar's spacing with one button fewer, as the board draws.

## The classic header too

Ruben decided the switch also removes the button from Nextcloud's own header (27 November 2026).
Nextcloud's header has the same `#contactsmenu`, so the same rule works there. With the classic style
`AppMenuService` returns early for a person and loads no menu script or menu stylesheet; the
contacts stylesheet is added before that early return, so it loads in either style. A person cannot
open Menu settings with the classic style (only an administrator gets the gear there), so in practice
the administrator's default applies, and a value a person saved earlier still wins.

## Where the switch is set

1. **Menu settings, Configuration**, in the Sections block, after the create switches: "Contacts menu
   in the top bar". In Only for me it stays editable with the create switches and is saved as the
   person's key; the rest of the tab stays disabled under the existing note.
2. **thematiq's admin page, Layout and options, Menu card**, after the create switches, saved at once
   through `POST /apps/thematiq/app-menu/defaults` with only that key, under the same note that people
   can change these for themselves. Unlike the create switches it stays enabled with the classic menu,
   because it works there too.

## Labels

| key | English | Dutch |
|---|---|---|
| `showContactsMenu` | Contacts button in the top bar | Knop Contacten in de bovenbalk |
| `showContactsMenuHelp` (Menu settings) | The address book next to the notifications. Off: the button disappears, the Contacts app stays in the menu. | Het adresboek naast de meldingen. Uit: de knop verdwijnt, de app Contacten blijft in het menu. |
| `showContactsMenuDefaultHelp` (admin page) | The address book next to the notifications. This is the default; off leaves the button out, the Contacts app stays in the apps menu. | Het adresboek naast de meldingen. Dit is de standaard; uit laat de knop weg, de app Contacten blijft in het appmenu. |

The note "People can change both for themselves in Menu settings." from quick-actions-toggles becomes
"People can change these for themselves in Menu settings." / "Iedereen kan deze zelf aanpassen in
Menu-instellingen." The boards' copy wins where it differs.
