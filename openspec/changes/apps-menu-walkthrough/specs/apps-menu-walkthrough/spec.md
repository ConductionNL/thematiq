# apps-menu-walkthrough

## ADDED Requirements

### Requirement: The apps menu has a walkthrough with one step per feature (REQ-TQWT-001)

The workplace apps menu MUST have a walkthrough drawn by nextcloud-vue's `CnWalkthrough` and driven by
`useWalkthrough`, declared as an ADR-043 `walkthrough` block with one tour `apps-menu`. The tour MUST
have these steps, in this order, each spotlighting the part it explains through a
`data-walkthrough-id` the menu script sets: `open` (the grid button: click, pointing, Enter and Escape),
`apps` (the list of entries), `more` (the More / Fewer toggle and moving an app between the list and
More), `reorder` (dragging, Alt with an arrow key), `pin` (dragging onto and off the top bar, Alt+P, at
most 5), `create` (the Create row) and `settings` (the Menu settings gear). The steps `more` and
`create` MUST be optional and skipped when their part is not drawn. Every step MUST have a title and a
body of at most two sentences in English and Dutch, without em-dashes and in sentence case. The card
MUST show the step's place among the steps shown, with Back, Next, Skip and, on the last step, Finish.

#### Scenario: The tour walks every feature of the menu
- GIVEN a person who never saw the tour, on a page with the workplace apps menu and more apps than fit above the fold
- WHEN they open the apps menu
- THEN the tour MUST show the steps open, apps, more, reorder, pin, create and settings, in that order
- AND each step MUST spotlight the element with its `data-walkthrough-id`
- AND the card MUST read "1 of 7" on the first step

#### Scenario: A step whose part is absent is skipped
- GIVEN the administrator switched create actions off for everyone
- WHEN a person who never saw the tour opens the apps menu
- THEN the tour MUST NOT show the create step
- AND the card MUST read "1 of 6" on the first step

#### Scenario: The copy is in Dutch for a Dutch person
@e2e exclude a second account in Dutch adds nothing the l10n check cannot show; proven by tests/l10n/check-l10n-completeness.js (every step key has a Dutch string) and tests/vitest/menuWalkthrough.spec.js (the step texts go through t('thematiq'))
- GIVEN a person whose language is Dutch
- WHEN the tour shows the pin step
- THEN its title MUST read "Vastzetten in de bovenbalk"

### Requirement: The tour starts on the first open of the apps menu (REQ-TQWT-002)

The tour MUST NOT start on page load. On a page where a tour is due, it MUST start when the person
opens the apps menu, by any means, with the menu open. A tour is due when the person has no seen
version, when a step's `sinceVersion` is newer than their seen version, or when their tour progress
names a step left part-way. The server MUST put the person's seen version, their progress and the
version core of the installed thematiq (`major.minor.patch`, without a pre-release or build suffix) in
the `thematiq/app-menu` initial state as `walkthrough`, so the menu script knows whether a tour is due
without a request. The tour's script bundle MUST be loaded only when a tour is due or asked for. The
tour MUST NOT start while the first-time setup wizard is open on the page.

#### Scenario: Opening the menu for the first time starts the tour
- GIVEN a person who never saw the tour
- WHEN they open a page
- THEN no tour MUST show and the tour's script MUST NOT be loaded
- WHEN they open the apps menu
- THEN the tour MUST start at the open step with the menu open

#### Scenario: A person who finished the tour does not get it again
- GIVEN a person who finished the tour at the installed version
- WHEN they open the apps menu
- THEN no tour MUST start and the tour's script MUST NOT be loaded

#### Scenario: Leaving part-way resumes at the same step
- GIVEN a person on the reorder step of the tour
- WHEN they reload the page and open the apps menu
- THEN the tour MUST resume at the reorder step

### Requirement: The menu keeps working while the tour runs (REQ-TQWT-003)

While the tour runs, the apps menu MUST stay open: a click on the dimmer, the pointer leaving the menu
or a hover timeout MUST NOT close it. The spotlit part MUST stay clickable and focusable in place.
Alt with an arrow key MUST move the focused entry, Alt+P MUST pin or unpin it, and the More toggle MUST
fold and unfold, exactly as without the tour, and each MUST be stored as without the tour. Escape MUST
close the tour first and leave the menu open; a second Escape closes the menu.

#### Scenario: Reordering works on the reorder step
- GIVEN the tour on the reorder step and focus on the app at place 2
- WHEN Alt and Arrow up is pressed
- THEN that app MUST be at place 1 and the tour MUST still show the reorder step
- AND after a reload the app MUST still be at place 1

#### Scenario: Pinning and unpinning work on the pin step
- GIVEN the tour on the pin step and focus on an app that is not pinned
- WHEN Alt+P is pressed
- THEN the app MUST be pinned in the top bar
- WHEN Alt+P is pressed again
- THEN the app MUST no longer be pinned

#### Scenario: More unfolds on the more step and an app moves into view
- GIVEN the tour on the more step
- WHEN More is pressed
- THEN the folded apps MUST show and the toggle MUST read "Fewer"
- WHEN focus is on the first folded app and Alt and Arrow up is pressed until it passes the fold
- THEN that app MUST show after the menu is folded again

#### Scenario: Escape closes the tour, not the menu
- GIVEN the tour on the apps step
- WHEN Escape is pressed
- THEN the tour MUST close and the apps menu MUST stay open

#### Scenario: Dragging still works under the tour
@e2e exclude a pointer drag under the dimmer is the engine's cutout, which nextcloud-vue proves; the drag itself is proven by tests/vitest/appMenu.spec.js and the keyboard paths above prove the same handlers under the tour
- GIVEN the tour on the reorder step
- WHEN an app is dragged onto the first app
- THEN it MUST be at place 1

### Requirement: Finishing or skipping records the seen version (REQ-TQWT-004)

Finish, Skip, Escape and the close button MUST store the installed version core as the person's seen
version, in their user value `thematiq` / `apps_menu_walkthrough_seen_version`, and clear their
progress. Moving to a step MUST store the progress (`apps_menu_walkthrough_seen_version-progress`).
Both MUST go through `GET` / `PUT /apps/thematiq/api/preferences/{key}`, which MUST serve the signed-in
person only, take no user id, refuse a request without a session, and accept exactly these two keys;
any other key MUST answer 404 and store nothing.

#### Scenario: Skip is remembered on the server
- GIVEN a person who skips the tour on its first step
- WHEN they sign in again in a new browser context and open the apps menu
- THEN no tour MUST start

#### Scenario: Only the two tour keys are served
@e2e exclude pure controller logic: PHPUnit tests/Unit/Controller/PreferencesControllerTest.php (testOnlyTheTourKeysAreServed)
- GIVEN a signed-in person
- WHEN they `PUT /apps/thematiq/api/preferences/token_set` with a value
- THEN the answer MUST be 404 and nothing MUST be stored

#### Scenario: One person's tour state is not another's
@e2e exclude a second account adds nothing a unit test cannot show; proven by tests/Unit/Controller/PreferencesControllerTest.php (testItWritesOnlyTheSignedInUser)
- GIVEN two people, one of whom finished the tour
- WHEN the other opens the apps menu for the first time
- THEN the tour MUST start for the other

### Requirement: A new menu feature gets its own step after an upgrade (REQ-TQWT-005)

Every step MUST carry the `sinceVersion` of the thematiq release that ships it, as a version core. After
an upgrade, a person whose seen version is older than a step's `sinceVersion` MUST get, on their next
open of the apps menu, a tour of only the steps newer than their seen version. A person without a seen
version MUST get the whole tour. A step newer than the installed version MUST NOT show. The comparison
MUST use version cores on both sides, so a nightly build (`1.3.0-unstable.20261201`) and its release
(`1.3.0`) compare equal.

#### Scenario: Only the new step after an upgrade
@e2e exclude an upgrade cannot be staged on the test instance; proven by tests/vitest/menuWalkthrough.spec.js (a person who saw 1.3.0 gets only a step since 1.4.0)
- GIVEN a person who finished the tour at 1.3.0
- AND the installed thematiq is 1.4.0 with one step since 1.4.0
- WHEN they open the apps menu
- THEN the tour MUST show that one step only

#### Scenario: A nightly build does not hide the next release's steps
@e2e exclude pure version logic; proven by tests/Unit/Service/WalkthroughPreferencesTest.php (testTheVersionCoreDropsTheBuildSuffix) and tests/vitest/menuWalkthrough.spec.js
- GIVEN a person who finished the tour on `1.3.0-unstable.20261201`
- AND the installed thematiq is 1.3.1 with one step since 1.3.1
- WHEN they open the apps menu
- THEN the tour MUST show that step

### Requirement: The tour can be replayed (REQ-TQWT-006)

A person MUST be able to replay the whole tour from step 1 from two places: a "Replay the tour" button
in the footer of the Menu settings dialog, which closes the dialog, opens the apps menu and starts the
tour, and an "Apps menu tour" entry in the user menu, directly after Nextcloud's Help entry or at the end
of the user menu when Help is not there, which opens the apps menu and starts the tour. A replay MUST
show every step whose part is drawn, whatever the seen version. With the classic menu style, the user
menu entry MUST NOT be drawn.

#### Scenario: Replay from the user menu
- GIVEN a person who finished the tour
- WHEN they choose "Apps menu tour" in the user menu
- THEN the apps menu MUST open and the tour MUST start at the open step

#### Scenario: The classic style has no tour entry
@e2e exclude switching everyone to the classic style changes the shared instance; proven by tests/Unit/Service/AppMenuServiceTest.php (testTheClassicStyleLeavesTheMenuAlone) and tests/vitest/headerUser.spec.js (no tour entry without the menu state)
- GIVEN the administrator chose the classic menu for everyone
- WHEN a person opens the user menu
- THEN it MUST NOT have an "Apps menu tour" entry

### Requirement: The tour is accessible (REQ-TQWT-007)

The tour card MUST be a labelled dialog that names its step, with focus moved into it when a step opens
and back to the spotlit part when the person tabs into it. Every step change MUST be announced in a
polite live region with the step's title and place. Back, Next, Skip, Finish and the close button MUST
work with the keyboard. With `prefers-reduced-motion: reduce` the tour MUST draw without transitions.
The card's text MUST meet WCAG AA contrast in every token set.

#### Scenario: A step change is announced
- GIVEN the tour on the open step
- WHEN Next is pressed with the keyboard
- THEN the live region MUST say "Step 2 of 7: All your apps in one place"

#### Scenario: Reduced motion
@e2e exclude a transition's absence is a style rule of the engine; proven by tests/vitest/menuWalkthrough.spec.js (the reduced-motion look is passed to CnWalkthrough)
- GIVEN `prefers-reduced-motion: reduce`
- WHEN the tour moves to the next step
- THEN it MUST draw without a transition
