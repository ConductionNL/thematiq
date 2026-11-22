# apps-menu-opening

## ADDED Requirements

### Requirement: The apps menu opens on hover and pins on click
In the workplace header, pointing a fine pointer at the grid button or the current-app button MUST
open the apps menu after a pause of about 300 ms, in a focused window only. A menu opened this way
MUST close about 180 ms after the pointer has left both the buttons and the menu. A click on a
button while the menu is open by hover MUST pin it: it MUST stay open when the pointer leaves, until
a click outside it, Escape or another click on the button. A menu opened by a click or by the
keyboard MUST behave as pinned. On a Nextcloud that opens the menu on hover by itself, the script
MUST NOT open it a second time. With `prefers-reduced-motion: reduce`, the menu MUST open and close
without a transition.

#### Scenario: Pointing opens, leaving closes
- GIVEN a page with the workplace header
- WHEN the pointer rests on the grid button
- THEN the apps menu MUST open
- WHEN the pointer moves away from the button and the menu
- THEN the apps menu MUST close

#### Scenario: A click pins the menu
- GIVEN the apps menu opened by pointing
- WHEN the grid button is clicked and the pointer moves away
- THEN the apps menu MUST stay open
- WHEN Escape is pressed
- THEN the apps menu MUST close

#### Scenario: Passing over the button does nothing
@e2e exclude a pointer passing within 300 ms is a timing detail; proven by tests/vitest/appMenuOpening.spec.js (a short pass does not open)
- GIVEN a page with the workplace header
- WHEN the pointer crosses the grid button in less than 300 ms
- THEN the apps menu MUST NOT open

#### Scenario: The keyboard opens and closes the menu
- GIVEN focus on the grid button
- WHEN Enter is pressed
- THEN the apps menu MUST open with focus inside it
- WHEN Escape is pressed
- THEN the apps menu MUST close and focus MUST return to the grid button

#### Scenario: Reduced motion
@e2e exclude a transition's absence is a style rule; proven by tests/vitest/appMenuOpening.spec.js (the stylesheet turns transitions off under reduced motion)
- GIVEN `prefers-reduced-motion: reduce`
- WHEN the apps menu opens
- THEN it MUST appear without a transition
