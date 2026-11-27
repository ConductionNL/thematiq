# Screens

Source: ConductionNL/design-system#1216, branch `design/thematiq-quick-actions-place`, which adds the
brand start and the Create button to the top bars that are not Mail's. Boards are canon: where this
change and a board differ, the change follows the board.

## The top bar of every page

- DqKop (the bar with the brand start and, from #1216, the Create button) https://identity.conduction.nl/screens/board?id=werkplek/DqKop&ds=zuiddrecht: REQ-TQWH-001, REQ-TQWH-005
- AppMenu (the bar drawn with the apps menu, from #1216) https://identity.conduction.nl/screens/board?id=werkplek/AppMenu&ds=zuiddrecht: REQ-TQWH-001
- DqZaken (a Conduction app with its own brand; must not change) https://identity.conduction.nl/screens/board?id=dossiq/DqZaken&ds=zuiddrecht: REQ-TQWH-005, REQ-TQSS-001

## The app layout, now for every app

These boards must stay matched after the move; their shared parts become every app's.

- MlInbox https://identity.conduction.nl/screens/board?id=mail/MlInbox&ds=zuiddrecht: REQ-TQSS-001, 002, 003
- MlThreadList https://identity.conduction.nl/screens/board?id=mail/MlThreadList&ds=zuiddrecht: REQ-TQSS-002
- MlThread https://identity.conduction.nl/screens/board?id=mail/MlThread&ds=zuiddrecht: REQ-TQSS-004, 006
- ClWeek https://identity.conduction.nl/screens/board?id=calendar/ClWeek&ds=zuiddrecht: REQ-TQSS-001, 006
- ClMonth https://identity.conduction.nl/screens/board?id=calendar/ClMonth&ds=zuiddrecht: REQ-TQSS-001, 006
- ClEventPopover https://identity.conduction.nl/screens/board?id=calendar/ClEventPopover&ds=zuiddrecht: REQ-TQSS-006
- TbBoard https://identity.conduction.nl/screens/board?id=tasks/TbBoard&ds=zuiddrecht: REQ-TQSS-001, 006
- TbCard https://identity.conduction.nl/screens/board?id=tasks/TbCard&ds=zuiddrecht: REQ-TQSS-003, 004, 006

## Boards to redraw (Ruben's decisions, 27 November 2026)

- MlNav, ClNav and TbNav, and MlInbox, ClWeek and TbBoard where they show the navigation: the selected
  entry as the navigation entry style draws it (on Zuiddrecht the accent on its tint, as DqZijbalk):
  REQ-TQSS-001.
- TbCard: the card panel's title at 20 px, like every other sidebar title: REQ-TQSS-003.
- Files, Talk, Contacts and Settings have no board of their own; they take the shared rules and are
  checked by the e2e smoke, not by compdiff.
