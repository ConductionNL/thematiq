# workplace-app-sheets

## ADDED Requirements

### Requirement: Mail's folders fold to a rail while a conversation is open (REQ-TQAS-001)
On a Mail page in the light layout, the folders MUST be 34 px rows in body text under a 34 px New
message button with a pencil. From 1024 px wide, while a conversation is open, the folder column MUST
fold to a 64 px rail of icons with a small label under each, as MlThread draws it. Keyboard focus in
the column, or an open folder menu, MUST unfold the full column over the list at once; the pointer MUST
unfold it after 200 ms, so crossing the rail does not flash it. A row without an icon ("Show all
folders") MUST NOT show in the rail. A browser without style queries MUST keep the column unfolded.

#### Scenario: Opening a conversation folds the folders
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js reads the sheet; looked at live on :8076 against MlThread
- GIVEN Mail in a 1440 px window
- WHEN a conversation is opened
- THEN the folder column MUST be 64 px wide with an icon and a label per folder

#### Scenario: The keyboard unfolds at once
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js
- GIVEN the folded rail
- WHEN a folder receives keyboard focus
- THEN the full column MUST show without delay

### Requirement: Mail's list and conversation follow MlInbox and MlThread (REQ-TQAS-002)
The conversation list MUST be one 410 px white column of rows with a hairline between them: a 32 px
initials circle in the primary's light tint, then sender, subject and a one-line preview. The selected
row MUST be in the primary's light tint with a 3 px primary edge. Unread MUST be an 8 px primary dot
before the avatar. A row MUST mark an attachment with the paperclip only. The list's date groups MUST
stay for screen readers only. The conversation MUST be white cards on the grey surface under a white
subject bar with the subject at 20 px. Attachments MUST be rows with a typed badge, the name as a link
and the size at the end; Reply MUST start its row. With no conversation open, the pane MUST show an
envelope in a white circle over the words "Select a conversation", centred, without Mail's paragraph
and second New message button.

#### Scenario: The empty reading pane
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js sets the translated words; looked at live on :8076 against MlInbox
- GIVEN Mail with no conversation open and a Dutch user
- WHEN the pane renders
- THEN it MUST show the envelope and the translated "Select a conversation"

#### Scenario: An unread row
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js reads the sheet
- GIVEN an unread conversation in the list
- WHEN the list renders
- THEN an 8 px dot in the primary MUST stand before the avatar

### Requirement: Calendar follows ClWeek, ClMonth and ClEventPopover (REQ-TQAS-003)
On a Calendar page in the light layout, the grid MUST be white with hairlines and the weekend a shade
darker; a day heading MUST be the day in muted text and today the primary. Pointing at the grid MUST
NOT grey a whole row. An entry MUST be its calendar's colour at 14 % with a 3 px edge in that colour,
the title semibold over the time in muted text. In the week view a day heading MUST read the weekday
and the day of the month ("Mon 12"), taken from the column's own date. In the month view day numbers
MUST be plain, today's in a filled primary badge, and a timed entry a chip in its calendar's tint with
a 3 px edge. The navigation MUST be 34 px rows in 14 px, and the new event button MUST read "New
event". The event popover MUST have a 1 px border, a 10 px radius and no focus ring round the whole
box.

#### Scenario: A week day heading
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js (names a week day "Mon 23" from the column's own date, also after a re-render)
- GIVEN the week view with a column dated 2026-11-23
- WHEN the heading renders
- THEN it MUST read the weekday the app wrote and "23"

#### Scenario: The month view keeps its weekday heads
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js (leaves the month view's weekday heads alone)
- GIVEN the month view
- WHEN the script runs
- THEN the weekday heads MUST stay as the app wrote them

### Requirement: Deck follows TbBoard and TbCard (REQ-TQAS-004)
On a Deck page in the light layout, the board MUST be a white canvas with a 60 px title row (the title
at 20 px) and a hairline under it, and one grey 224 px column per stack, 10 px apart on a 12 px margin,
each with a 32 px small uppercase heading. A column MUST scroll as a whole. A card MUST be white with a
hairline and the control radius, its labels above the title as coloured capitals without a chip, the
title at 14 px, then the person first and the due date in 12 px. The card menu MUST show when the card
is pointed at or has focus. The navigation MUST be 34 px rows in 14 px. An open card MUST sit in a
480 px panel, the title at 18 px, its tabs as a strip of folder tabs in text.

#### Scenario: The columns of TbBoard
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js; looked at live on :8076 against TbBoard
- GIVEN a Deck board with five stacks
- WHEN it renders in a 1440 px window
- THEN each stack MUST be a grey 224 px column of white cards

### Requirement: The app layer script acts only with its sheet (REQ-TQAS-005)
`js/workplace-apps.js` MUST act only while `css/workplace-apps/common.css` is on the page (it sets
`--thematiq-workplace-apps: 1`). It MUST set the boards' words, translated, as quoted CSS variables
the sheets show in place of the app's own text ("Select a conversation", "New event"), MUST name week
day headings as REQ-TQAS-003 says and keep doing so after the app renders again, and MUST mark the
root `data-thematiq-pointer` while the person uses a pointer and clear it on a key press, so the sheets
leave out the focus ring an app puts on a heading it focuses itself (Deck's card title) only for
pointer use. It MUST write text only.

#### Scenario: Without the sheet
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js (does nothing without the app layer sheet)
- GIVEN a page without `css/workplace-apps/common.css`
- WHEN the script runs
- THEN it MUST change nothing

#### Scenario: The keyboard brings the focus ring back
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js (marks pointer use and clears the mark on a key press)
- GIVEN the root is marked `data-thematiq-pointer`
- WHEN a key is pressed
- THEN the mark MUST be cleared
