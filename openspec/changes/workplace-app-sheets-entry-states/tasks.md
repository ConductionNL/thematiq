# Tasks: workplace-app-sheets-entry-states

## 1. Calendar

- [x] 1.1 Read which classes Calendar puts on a past, a tentative and an unanswered entry (FullCalendar's `fc-event-past`, Calendar's own tentative class); record them in a comment in `css/workplace-apps/calendar.css`. Calendar marks both states: `fc-event-nc-tentative` (STATUS or the person's answer) and `fc-event-nc-needs-action` (not answered yet).
- [x] 1.2 `css/workplace-apps/calendar.css`: past entries faded, text kept at 4.5:1. Tokens only.
- [x] 1.3 `css/workplace-apps/calendar.css`: the dashed frame and edge for an entry not answered yet.
- [x] 1.4 vitest `tests/vitest/workplaceApps.spec.js`: the rules exist, start at the content element and write no colour literal.

## 2. Deck

- [x] 2.1 Read which classes Deck and smooth-dnd put on the dragged card and the drop placeholder.
- [x] 2.2 `css/workplace-apps/deck.css`: the lifted card, no tilt with reduced motion. Tokens only.
- [ ] 2.4 The dashed slot. Deck passes no `dropPlaceholder` to smooth-dnd, so no element marks where the card will land, only a gap; a stylesheet has nothing to draw dashed. Needs a decision: an upstream Deck change, a script that draws an element (REQ-TQAS-005 allows text only), or dropping the slot from the requirement.
- [x] 2.3 vitest `tests/vitest/workplaceApps.spec.js`: the rules exist and the tilt sits outside reduced motion.

## 3. Look

- [ ] 3.1 Look at both live against ClWeek and TbBoard (not on the demo stack at :8076).
