# Tasks: workplace-app-sheets-board-details

## 1. Mail

- [x] 1.1 `css/workplace-apps/mail.css`: folders, list, selected row, conversation head as the mail boards (#1790).
- [x] 1.2 The 64 px folder rail while a conversation is open, unfolding on focus and after 200 ms on the pointer (#1798).
- [x] 1.3 The unread dot, the empty pane, attachment rows and the rail delay (#1801).
- [x] 1.4 Rows without an icon stay out of the rail (#1802).
- [x] 1.5 The film audit's Mail differences (#1809).

## 2. Calendar

- [x] 2.1 `css/workplace-apps/calendar.css`: grid, today, entries, navigation (#1783, #1795).
- [x] 2.2 Month chips, today's badge, the event popover, "New event" (#1795, #1809).

## 3. Deck

- [x] 3.1 `css/workplace-apps/deck.css`: title row, columns, cards, labels, person and due date (#1783, #1795).
- [x] 3.2 The 480 px card panel with folder tabs; no focus ring on a heading focused by a click (#1795, #1809).

## 4. Script and checks

- [x] 4.1 `js/workplace-apps.js`: the boards' words, week day headings, the pointer mark; loaded by `HeaderUserService` (#1809).
- [x] 4.2 vitest `tests/vitest/workplaceApps.spec.js` (#1783, #1795, #1809).
- [x] 4.3 vitest `tests/vitest/workplaceApps.spec.js`: assert the selector rule of the modified requirement (every selector starts at an allowed root, `:has(` only under the content element).
