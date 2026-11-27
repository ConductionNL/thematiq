# Design

The boards are in ConductionNL/design-system on branch `design/thematiq-quick-actions-place`:
AppMenuSnelacties and the changed settings board. They are canon; where this design and a board
differ, the build follows the board.

## One key with the other menu settings

The place is a menu setting, `quickActionsPlace`, with the choices `topBar` and `appsMenu` and the
shipped value `topBar`. It lives in `AppMenuSettings` next to `showCreateActions`, so it gets for free
what every menu setting already has: the app value `menu.quickActionsPlace`, the server check of
`CHOICES`, the effective value in the `thematiq/app-menu` initial state, Reset to default for everyone,
and the For everyone save of Menu settings.

Why not a separate app value next to `header_style`: the place is read by the menu script and the Quick
add script, which already read the menu settings. A second store would need its own check, reset and
export, and could disagree with Menu settings.

## Administrator only

`apps-menu-settings` splits the keys into personal keys (`order`, `pinned`, `hidden`,
`externalApps`) and administrator keys (everything else, theming and configuration). The Create switch
`showCreateActions` and the menu `style` are administrator keys. The place of the quick actions is the
same kind of choice: it decides how the workplace looks for everyone. So `quickActionsPlace` is not
added to `PERSONAL`, and `POST /apps/thematiq/app-menu/settings` refuses it with 400, as it refuses
`columns` today.

## Where the administrator sets it

1. **thematiq's admin page, Layout and options, Menu card** (the TqLayout panel). This is where the
   review asked for it. The page draws two radios in a `radiogroup` labelled "Quick actions":
   "In the top bar" (help: "The plus button before the notifications, on Mail, Calendar and Deck.")
   and "In the apps menu" (help: "A Create row at the bottom of the apps menu."). The radio saves at
   once through `POST /apps/thematiq/app-menu/defaults` with `{settings: {quickActionsPlace}}`, which
   stores only that key (`saveDefaults` already stores only the keys it is sent). The page shows the
   value from `AppMenuSettings::defaults()`.
2. **Menu settings, Configuration**, in the Sections block under "Show create actions", for an
   administrator choosing For everyone. In Only for me it is disabled under the existing "set by the
   administrator" note.

With `showCreateActions` off both controls are disabled with the help "Switch on the create actions
first." With the classic menu style the admin page control is disabled with "Only with the workplace
menu.", because the classic style loads no menu script and so no Quick add either.

## How the scripts follow it

`js/app-menu.js` already shares the Create actions as `window.thematiqCreateActions`, with an `on`
flag from `showCreateActions`. It gets a `place` field with the effective `quickActionsPlace`.

- `js/header-create.js` draws Quick add only when `on` is true and `place` is `topBar`. With
  `appsMenu` it draws nothing and asks nothing.
- The Create row in the apps menu is drawn in both places, as today. In the top bar place it stays,
  because Quick add is only on Mail, Calendar and Deck and the other apps need a way to create too.
- The walkthrough's create step spotlights the Create row, which is there in both places, so the tour
  needs no change.

## Labels

| key | English | Dutch |
|---|---|---|
| `quickActionsPlace` | Quick actions | Snelle acties |
| `quickActionsTopBar` | In the top bar | In de bovenbalk |
| `quickActionsTopBarHelp` | The plus button before the notifications, on Mail, Calendar and Deck. | De plusknop voor de meldingen, in Mail, Agenda en Deck. |
| `quickActionsAppsMenu` | In the apps menu | In het appmenu |
| `quickActionsAppsMenuHelp` | A Create row at the bottom of the apps menu. | Een rij Nieuw onderaan het appmenu. |
| `quickActionsNeedCreate` | Switch on the create actions first. | Zet eerst de acties voor nieuw aan. |
| `quickActionsNeedWorkplace` | Only with the workplace menu. | Alleen met het werkplekmenu. |

The boards' copy wins where it differs.
