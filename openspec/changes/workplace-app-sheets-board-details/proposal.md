---
kind: code
---

# Mail, Calendar and Deck drawn as their boards, in detail

## Why

The workplace-layout spec says that, in the light layout, Mail, Calendar and Deck are drawn as the
MlInbox, MlThreadList, MlThread, ClWeek, ClMonth, TbBoard and TbCard boards, but only in broad
strokes. The pixel and film passes of 23 November 2026 (#1790, #1795, #1798, #1801, #1802 and #1809)
drew each app much closer to its boards, and added a small script for what a stylesheet cannot do.
None of that detail has a spec, and two choices go against the current text: Mail's folder rail
needs `:has()` to know a conversation is open, and Calendar's event popover is mounted outside the
content element. This change writes the detail down and corrects the requirement.

## What changes

- **Mail.** Folders as 34 px rows under a 34 px New message button with a pencil; with a
  conversation open they fold to a 64 px rail that unfolds on keyboard focus at once and on the
  pointer after 200 ms; rows without an icon stay out of the rail. The list as one 410 px white
  column, the selected row in the primary's light tint with a 3 px edge, unread as an 8 px dot,
  date groups for screen readers only. With no conversation open, an envelope over "Select a
  conversation". Attachments as rows with a typed badge; Reply starts its row.
- **Calendar.** A white grid with hairlines and a darker weekend, today in the primary, entries in
  their calendar's tint with a 3 px edge, week day heads as "Mon 12", "New event" on the button,
  month entries as chips, and the event popover as ClEventPopover draws it.
- **Deck.** A 60 px title row, grey 224 px columns of white cards, labels as coloured capitals,
  the person first in a card's footer, the card panel 480 px wide with its tabs as folder tabs,
  and no focus ring on a heading the app focuses after a click.
- **The script.** `js/workplace-apps.js` sets the boards' words as translated variables, names
  week day heads from the column's own date, and marks pointer use. It acts only while
  `css/workplace-apps/common.css` is on the page.

## Not in this change

- What the boards draw that the apps cannot show: Mail's filter chips and work package chips,
  Calendar's second time zone, work package bars, milestones and context panel, Deck's
  work-in-progress limits, grouping and team filter. Those are upstream features, not styling.
- New behaviour. Every task below is built and cites its pull request.

## Impact

- `css/workplace-apps/mail.css`, `calendar.css`, `deck.css`, `common.css`
- `js/workplace-apps.js`, `lib/Service/HeaderUserService.php` (loads it), l10n
