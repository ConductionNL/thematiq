# workplace-app-sheets

## ADDED Requirements

### Requirement: Calendar fades entries that have passed (REQ-TQAS-101)
On a Calendar page in the light layout, an entry that has ended (FullCalendar's `fc-event-past`) MUST be
drawn faded in the week and the month view, as ClWeek draws it, while its title and time keep at least
4.5:1 against what they sit on. The rule MUST read tokens only and MUST start at the app's content
element.

#### Scenario: Yesterday's meeting
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js reads the rule; looked at live against ClWeek
- GIVEN the week view on a Wednesday with a meeting on Monday
- WHEN the week renders
- THEN Monday's meeting MUST be faded and today's MUST NOT

#### Scenario: The text stays readable
@e2e exclude Computed in the test: the faded text colour against the entry's tint on white
- GIVEN a faded entry under the Zuiddrecht set
- WHEN its colours are computed
- THEN the title MUST reach 4.5:1

### Requirement: Calendar draws an entry not answered yet with a dashed frame (REQ-TQAS-102)
An entry Calendar marks as tentative, or as waiting for the person's answer, MUST be drawn with a
dashed 1 px frame and a dashed 3 px edge in its calendar's colour on a white ground, as ClWeek draws
Thursday's rollout review. When Calendar marks neither state on the entry's element, the requirement
applies to the tentative state alone and the builder MUST note that in the task.

#### Scenario: A tentative meeting
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js reads the rule; looked at live against ClWeek
- GIVEN a meeting the person accepted tentatively
- WHEN the week renders
- THEN the entry MUST have a dashed frame and edge

### Requirement: Deck lifts a card mid-move (REQ-TQAS-103)
While a Deck card is dragged in the light layout, the card under the pointer MUST be drawn lifted, with
a shadow and a slight tilt, as TbBoard draws it. With reduced motion asked for, the tilt MUST be left
out. The rules MUST read tokens only and start at Deck's content element. When the dragged copy is
drawn outside that element, the lift MUST be left out rather than styled from the body. TbBoard's
dashed slot where the card will land is not drawn: Deck passes no `dropPlaceholder` to smooth-dnd, so
no element marks the place, and drawing one would take a change to Deck's own code (see the proposal's
"Not in this change").

#### Scenario: A card between two columns
@e2e exclude A pointer drag across columns; static check in tests/vitest/workplaceApps.spec.js, looked at live against TbBoard
- GIVEN a Deck board
- WHEN a card is dragged towards another column
- THEN the card MUST be lifted

#### Scenario: Reduced motion
@e2e exclude Static file check: the tilt sits outside `prefers-reduced-motion: reduce`
- GIVEN the person asks for reduced motion
- WHEN a card is dragged
- THEN the card MUST NOT tilt
