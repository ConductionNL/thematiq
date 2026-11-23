---
kind: code
---

# Calendar entry states and Deck's card mid-move, as the boards draw them

## Why

Three things the ClWeek and TbBoard boards draw are states Calendar and Deck already have, so the
theme can draw them without changing the apps, but the app layer sheets do not draw them yet:

- ClWeek fades an entry that has passed.
- ClWeek draws Thursday's rollout review dashed, because the person has not answered it yet.
- TbBoard lifts a card mid-move and shows a dashed drop zone where it will land.

## What changes

- **Past entries.** In Calendar's week and month views, an entry that has ended is drawn faded,
  its text still readable.
- **Not answered yet.** An entry Calendar marks as tentative, or as waiting for the person's
  answer, is drawn with a dashed edge and frame instead of the solid tint.
- **A card mid-move.** While a Deck card is dragged, the card under the pointer is lifted (a
  shadow and a slight tilt off with reduced motion) and the place it will drop is a dashed slot of
  the card's height.

## Not in this change

- What the apps cannot show: TbBoard's drop zone text "Setting the status in OpenProject",
  work-in-progress limits, a second time zone. Those are upstream features.
- Any change to the apps' own code.

## Impact

- `css/workplace-apps/calendar.css`, `css/workplace-apps/deck.css`
- `tests/vitest/workplaceApps.spec.js`
