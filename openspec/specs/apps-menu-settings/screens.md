# Screens: apps-menu-settings

- AppMenuInstellingen https://identity.conduction.nl/screens/board?id=werkplek/AppMenuInstellingen&ds=zuiddrecht
- AppMenuConfiguratie https://identity.conduction.nl/screens/board?id=werkplek/AppMenuConfiguratie&ds=zuiddrecht
- AppMenuVormgeving https://identity.conduction.nl/screens/board?id=werkplek/AppMenuVormgeving&ds=zuiddrecht
- AppMenuExterneApp https://identity.conduction.nl/screens/board?id=werkplek/AppMenuExterneApp&ds=zuiddrecht

## header-buttons-follow-shape (REQ-TQMS-001)

The boards that show the top bar and the apps menu. They draw the round shape; the square shape is the
menu's square icon applied to every icon of the bar and the menu (`design.md`). Boards are canon.

### The top bar

- DqKop (the bar with the bell, the contacts menu, the Create button and the person) https://identity.conduction.nl/screens/board?id=werkplek/DqKop&ds=zuiddrecht: REQ-TQMS-001
- AppMenuVastzetten (the bar with the pins) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuVastzetten&ds=zuiddrecht: REQ-TQMS-001

### The apps menu

- AppMenu https://identity.conduction.nl/screens/board?id=werkplek/AppMenu&ds=zuiddrecht: REQ-TQMS-001
- AppMenuAlles https://identity.conduction.nl/screens/board?id=werkplek/AppMenuAlles&ds=zuiddrecht: REQ-TQMS-001
- AppMenuSnelacties (the Create row) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuSnelacties&ds=zuiddrecht: REQ-TQMS-001

### The setting

- AppMenuInstellingen (Menu settings, the round and square samples) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuInstellingen&ds=zuiddrecht: REQ-TQMS-001

## top-bar-contacts-switch (REQ-TQCM-001, REQ-TQCM-002)

### Boards

- werkplek/AppMenuConfiguratie (Menu settings, Configuration, with the contacts menu switch after the create switches) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuConfiguratie&ds=zuiddrecht: REQ-TQCM-001, REQ-TQCM-002
- thematiq/TqLayout (the switch in the Menu card of Layout and options) https://identity.conduction.nl/screens/board?id=thematiq/TqLayout: REQ-TQCM-002

### Existing boards it keeps

- werkplek/AppMenu https://identity.conduction.nl/screens/board?id=werkplek/AppMenu&ds=zuiddrecht: the top bar with the contacts menu, the switch on
- werkplek/DqKop https://identity.conduction.nl/screens/board?id=werkplek/DqKop&ds=zuiddrecht: the end of the top bar, where the button leaves a gap that closes

## apps-menu-show-titles (REQ-TQMT-001 to REQ-TQMT-006)

### Boards

- werkplek/AppMenuVormgeving (Menu settings, Theming: "Show titles", directly under it "Show titles in the top bar" (off), then "Show payoff", disabled while titles is off) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuVormgeving&ds=zuiddrecht: REQ-TQMT-001, REQ-TQMT-004, REQ-TQMT-005
- werkplek/AppMenuZonderTitels (the open apps menu with titles off: icons only in every section, a tooltip on the focused entry) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuZonderTitels&ds=zuiddrecht: REQ-TQMT-002, REQ-TQMT-003

- werkplek/AppMenuBalkTitels (the top bar with titles in the top bar on: the pins show their name next to the icon) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuBalkTitels&ds=zuiddrecht: REQ-TQMT-006
- werkplek/AppMenuVastzetten (the pinned apps icon-only, with the name tooltip) https://identity.conduction.nl/screens/board?id=werkplek/AppMenuVastzetten&ds=zuiddrecht: REQ-TQMT-003, REQ-TQMT-006
- thematiq/TqLayout (the Menu card of Layout and options, with "Show titles in the top bar" in place of the older option) https://identity.conduction.nl/screens/board?id=thematiq/TqLayout: REQ-TQMT-005, admin-settings

### Board follow-ups

- thematiq/TqSetupAppmenu: "Show titles" before "Show payoff".

### Existing boards it keeps

- werkplek/AppMenuAlles https://identity.conduction.nl/screens/board?id=werkplek/AppMenuAlles&ds=zuiddrecht: the menu with titles on, unchanged
- werkplek/AppMenuSnelacties https://identity.conduction.nl/screens/board?id=werkplek/AppMenuSnelacties&ds=zuiddrecht: the Create row with titles on, unchanged
- thematiq/TqSetupAppmenu https://identity.conduction.nl/screens/board?id=thematiq/TqSetupAppmenu: the setup wizard's apps menu step
