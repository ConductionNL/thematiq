# apps-menu-pins Specification

## Purpose
Apps a person pins to the top bar from the workplace apps menu, by dragging or with Alt+P, stored for that person on the server.

## Requirements

### Requirement: A person pins apps to the top bar
In the workplace header, the person's pinned apps MUST show right after the grid button, in a
navigation landmark named "Pinned apps", as 40 px links with the app's icon and the app's name as
their accessible name. A pin of the page's own app MUST carry `aria-current="page"`. A pin whose app
the person cannot see MUST be left out. Dragging an entry of the open apps menu onto the top bar MUST
show a dashed drop slot after the pins with the hint "Drop to pin" (a status) and a chip with the
app under the pointer; releasing it there MUST pin the app at the end. With focus on an entry of the
menu, Alt+P MUST pin it, or unpin it when it is pinned. At most 5 apps MAY be pinned: a sixth MUST
NOT be added and the live region MUST say "You can pin up to 5 apps." A pin or unpin MUST be said in
a polite live region. The footer MUST read "Drag an app to reorder it or pin it to the top bar." and
"Alt with an arrow key moves, Alt+P pins or unpins."

#### Scenario: Drag an app onto the top bar
@e2e exclude a pointer drag across the popover and the bar needs the live demo, which is being filmed; proven by tests/vitest/appMenuPins.spec.js (pins an app dragged onto the bar, with a drop slot and its hint)
- GIVEN the open apps menu and Files pinned
- WHEN Deck is dragged onto the top bar
- THEN a dashed slot with "Drop to pin" MUST show
- AND after release Deck MUST be pinned after Files

#### Scenario: Pin with the keyboard
@e2e exclude the same path as the drag through the key handler; proven by tests/vitest/appMenuPins.spec.js (pins and unpins the focused app with Alt+P, says so and saves)
- GIVEN focus on Mail in the open apps menu
- WHEN Alt+P is pressed
- THEN Mail MUST be pinned and the live region MUST say "Mail pinned to the top bar."

### Requirement: A person unpins an app
Dragging a pinned app off the bar MUST leave its slot as an empty dashed square and show a chip under
the pointer with the hint "Release to remove"; releasing it outside the bar MUST unpin it, releasing it
on the bar MUST keep it. The click that ends a drag MUST NOT open the app. With focus on a pin, Alt+P
MUST unpin it and move focus to the next pin, or to the grid button; Alt with an arrow key MUST move it
one place and the live region MUST say its new place.

#### Scenario: Drag a pin off the bar
@e2e exclude a pointer drag needs the live demo, which is being filmed; proven by tests/vitest/appMenuPins.spec.js (unpins a pin dragged off the bar, with its hint, and the drag does not open the app)
- GIVEN Files and Deck pinned
- WHEN Deck is dragged off the bar and released
- THEN only Files MUST be pinned

### Requirement: Pins are stored for the person on the server
The pins MUST be saved in the person's own user settings (user value `thematiq` / `menu.pinned`, the personal key of the menu settings)
through `POST /apps/thematiq/app-menu/pins` with `{pins: [keys]}`, and MUST be in the
`thematiq/app-menu` initial state as `pins`. The endpoint MUST take no user id and MUST refuse a request
without a session. Before storing, the server MUST keep only keys of entries the person can see: an
app in their Nextcloud navigation, by its id or the app id in its address, or an external app of the
menu. Malformed keys and duplicates MUST be dropped, at most 5 kept, and an empty list MUST remove the
value.

#### Scenario: What may not be pinned is dropped
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuPinsTest.php
- GIVEN the pins `["files", "Files", "files", "admin_audit", "external-code", "deck", "mail", "talk", "notes", "calendar"]` and a person who cannot see `admin_audit`
- WHEN they are saved
- THEN `["files", "external-code", "deck", "mail", "talk"]` MUST be stored

#### Scenario: One person's pins are not another's
@e2e exclude a second account adds nothing a unit test cannot show; proven by tests/Unit/Controller/AppMenuPinsControllerTest.php (testItWritesOnlyTheSignedInUsersKnownApps)
- GIVEN a person saves pins
- THEN only that person's user value MUST change
