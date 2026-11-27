# Design

The boards are werkplek/AppMenuConfiguratie and thematiq/TqLayout, with the switch the board agent adds
next to the create switches. They are canon; where this design and a board differ, the build follows
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

With the classic menu style `AppMenuService` loads no menu assets for a person, so the switch does
nothing there, like the Create button.

## Where the switch is set

1. **Menu settings, Configuration**, in the Sections block, after the create switches: "Contacts menu
   in the top bar". In Only for me it stays editable with the create switches and is saved as the
   person's key; the rest of the tab stays disabled under the existing note.
2. **thematiq's admin page, Layout and options, Menu card**, after the create switches, saved at once
   through `POST /apps/thematiq/app-menu/defaults` with only that key, under the same note that people
   can change these for themselves. Disabled with the classic menu, with "Only with the workplace menu."

## Labels

| key | English | Dutch |
|---|---|---|
| `showContactsMenu` | Contacts menu in the top bar | Contactenmenu in de bovenbalk |
| `showContactsMenuHelp` | The contacts button next to the notifications. Contacts stay in the Contacts app. | De contactenknop naast de meldingen. Contacten blijven in de app Contacten. |

The note "People can change both for themselves in Menu settings." from quick-actions-toggles becomes
"People can change these for themselves in Menu settings." / "Iedereen kan deze zelf aanpassen in
Menu-instellingen." The boards' copy wins where it differs.
