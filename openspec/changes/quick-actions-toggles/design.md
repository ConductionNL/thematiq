# Design

The boards are ConductionNL/design-system#1216, revised to two switches: werkplek/AppMenuSnelacties
(the apps menu with the Create row), thematiq/TqLayout (the Menu card of the admin page) and
werkplek/AppMenuConfiguratie (the Configuration tab of Menu settings). They are canon; where this design
and a board differ, the build follows the board.

## Two personal keys

`AppMenuSettings` gets `showCreateButton` and `showCreateRow`, both `true` in `DEFAULTS`, both in
`SWITCHES` (checked as true or false) and both in `PERSONAL`. Being personal gives them the existing
order of precedence for free: a person's user value wins over the administrator's app value, which wins
over the shipped value. Reset to default for a person removes them with the other personal keys, and
Reset to default for everyone removes the administrator's values.

Why personal: Ruben decided the person chooses (27 November 2026). The switches change only that
person's own screen, like the pins and hidden apps, and not the look of the workplace for others.

## The move from showCreateActions

`showCreateActions` was an administrator key only, so only app value `menu.showCreateActions` can
exist. The move copies its value into `menu.showCreateButton` and `menu.showCreateRow` where those are
not stored yet, then deletes it. It runs where the external apps move already runs: on the first page
that draws the menu and when Menu settings opens. No marker is needed, because the old key is gone after
the first run.

A write of `showCreateActions` to the defaults endpoint is read as both new keys and never stored. That
keeps a setup wizard built before this change working. `SetupRequest::MENU_KEYS` names the two new keys.
On the person's endpoint the old key stays refused with 400, as it is today.

## How the scripts follow the switches

- `js/app-menu.js` draws the Create row only with `showCreateRow` on. It shares the Create actions as
  `window.thematiqCreateActions` while either switch is on, because Quick add reads them from there.
  With both off it shares nothing and never asks the templates API.
- `js/header-create.js` draws Quick add with `showCreateButton` on, on every page with the workplace
  top bar. Its list of apps (`APPS`, Mail, Calendar and Deck) goes, with the check of the open app.
  Mail, Calendar and Deck get exactly the button every other page gets.
- Drawing the button costs no request. The Create actions are fetched on the first open of Quick add
  or the apps menu, once per page, as `window.thematiqCreateActions.list()` already does.

## What happens to app-specific create entries

There are none to move. Quick add on Mail, Calendar and Deck never had entries of its own: it lists the
shared Create actions (`window.thematiqCreateActions`), the same as the apps menu's Create row. Removing
the app list therefore loses nothing. Should an app's own create action (a new message, event or card)
be wanted later, it belongs in the shared Create actions, so it shows in the button and the row on every
page alike, and never as an entry of one app's bar.
- The walkthrough's create step is optional and is skipped when the Create row is not drawn, so it needs
  no change.

## Where the switches are set

1. **Menu settings, Configuration**, in the Sections block: "Create button in the top bar" and "Create
   row in the apps menu", then "Show external apps". In Only for me the two create switches stay
   editable and are saved as the person's keys; the rest of the tab stays disabled under the existing
   note. In For everyone an administrator sets the defaults.
2. **thematiq's admin page, Layout and options, Menu card**: the same two switches, saved at once
   through `POST /apps/thematiq/app-menu/defaults` with only the toggled key, with the note that people
   can change both for themselves. Disabled with the classic menu, which loads no menu script.

## Labels

| key | English | Dutch |
|---|---|---|
| `showCreateButton` | Create button in the top bar | Knop Nieuw in de bovenbalk |
| `showCreateButtonHelp` | The plus button before the notifications, on every page. | De plusknop voor de meldingen, op elke pagina. |
| `showCreateRow` | Create row in the apps menu | Rij Nieuw in het appmenu |
| `showCreateRowHelp` | The Create row at the bottom of the menu. | De rij Nieuw onderaan het menu. |
| `createSwitchesPersonal` | People can change both for themselves in Menu settings. | Iedereen kan beide zelf aanpassen in Menu-instellingen. |
| `createSwitchesNeedWorkplace` | Only with the workplace menu. | Alleen met het werkplekmenu. |

The boards' copy wins where it differs.
