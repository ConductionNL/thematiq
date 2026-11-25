# apps-menu-order

## ADDED Requirements

### Requirement: A person orders the apps menu
In the workplace apps menu, a signed-in person MUST be able to move an entry to another place by
dragging it, or with the keyboard: with focus on an entry, Alt with Arrow up or Arrow left MUST move
it one place earlier and Alt with Arrow down or Arrow right one place later. A drag MUST start only
after the pointer moved 6 px, and the click that ends a drag MUST NOT open the app. After a keyboard
move, focus MUST stay on the moved entry and a polite live region MUST say its new place, for
example "Files moved to place 3 of 14." An entry moved into the first nine MUST show without
unfolding; an entry moved out of them MUST fold away with the rest.

#### Scenario: Drag an app into the first nine
- GIVEN the open apps menu with "All apps" unfolded, and an app at place 12
- WHEN that app is dragged onto the first app
- THEN it MUST be at place 1
- AND after the menu is folded again it MUST still show

#### Scenario: Move an app with the keyboard
- GIVEN the open apps menu, focus on the app at place 2
- WHEN Alt and Arrow up is pressed
- THEN that app MUST be at place 1 and keep focus
- AND the live region MUST say "<app> moved to place 1 of <total>."

#### Scenario: A drag is not a click
@e2e exclude the swallowed click is a timing detail of one gesture; proven by tests/vitest/appMenu.spec.js (a drag does not open the app)
- GIVEN an app in the open apps menu
- WHEN it is pressed, moved 20 px and released
- THEN the app's page MUST NOT open

### Requirement: The order is stored for the person on the server
The order MUST be saved in the person's own user settings (user value `thematiq` /
`app_menu_order`) through `POST /apps/thematiq/app-menu/order` with `{order: [keys]}`, and MUST be
in the `thematiq/app-menu` initial state as `order` on every page with the workplace header. Only
the signed-in user's own value MUST be written: the endpoint MUST take no user id and MUST refuse a
request without a session. A key MUST match `^[a-z0-9_-]{1,80}$`; other keys and duplicates MUST be
dropped and at most 200 kept. An empty list MUST remove the value. Entries the stored order does not
name MUST follow the named ones in the default order; stored keys not on the page MUST be ignored.

#### Scenario: The order follows the person to another browser
- GIVEN a person moved an app to place 1
- WHEN they sign in again in a new browser context and open the apps menu
- THEN that app MUST be at place 1

#### Scenario: What may not be stored is dropped
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/AppMenuOrderTest.php
- GIVEN the order `["files", "Files", "files", "<b>", "external-mail"]`
- WHEN it is saved
- THEN `["files", "external-mail"]` MUST be stored

#### Scenario: One person's order is not another's
@e2e exclude a second account's menu adds nothing a unit test cannot show; proven by tests/Unit/Controller/AppMenuOrderControllerTest.php (testItWritesOnlyTheSignedInUser)
- GIVEN two people, one of whom saves an order
- WHEN the other opens the apps menu
- THEN the other MUST see the default order

#### Scenario: A new app joins after the stored ones
@e2e exclude installing an app during a test changes the shared instance; proven by tests/vitest/appMenu.spec.js (an app the order does not name follows the named ones)
- GIVEN a stored order that does not name `deck`
- WHEN the apps menu opens with `deck` installed
- THEN `deck` MUST come after every app the order names
