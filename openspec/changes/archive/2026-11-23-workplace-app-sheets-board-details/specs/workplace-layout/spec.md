# workplace-layout

## MODIFIED Requirements

### Requirement: The light layout draws Mail, Calendar and Deck as the boards
While the layout is `light`, a page of Mail, Calendar or Deck MUST load `css/workplace-apps/common.css`
and the app's own sheet `css/workplace-apps/<app>.css`, after the other conditional stylesheets; with
the workplace header it MUST load `css/workplace-apps/brand.css` first. No other page MUST load them.
The sheets MUST write no colour literal. Every rule MUST start at the app's content element, at the
header, at a panel another app mounts on that page (OpenRegister's `#openregister-mail-sidebar`), or at
Calendar's event popover (`.event-popover`), which Calendar mounts at the end of the body. `:has()` MAY
only be used inside a rule that already starts at the app's content element, to read the app's own
state (Mail: a conversation is open); a browser without `:has()` MUST keep the app as it ships. The
boards are MlInbox, MlThreadList and MlThread for Mail, ClWeek and ClMonth for Calendar, TbBoard and
TbCard for Deck: the selected navigation entry in the primary's light tint, the new-item button as the
filled primary, rows and entries without link underlines, Deck's stacks as grey columns of white cards,
and the bar showing the logo and the app's name. The theme MUST NOT change the apps themselves; what a
board draws that an app cannot show is left out.

#### Scenario: Deck loads its sheets
@e2e exclude Unit check: tests/Unit/Service/CssInjectionServiceTest.php asserts the link order
- GIVEN the light layout and the workplace header
- WHEN a Deck page renders
- THEN `workplace-apps/brand`, `workplace-apps/common` and `workplace-apps/deck` MUST be linked after `workplace-layout`

#### Scenario: Another app loads none
@e2e exclude Unit check: tests/Unit/Service/LayoutOptionsServiceTest.php
- GIVEN the light layout
- WHEN a Files page renders
- THEN no `workplace-apps/` sheet MUST be linked

#### Scenario: The sheets use tokens only
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js reads the sheets
- GIVEN the files under `css/workplace-apps/`
- WHEN their declarations are read
- THEN no value MUST hold a hex, rgb() or hsl() colour
- AND every selector MUST start at `:is(#content, #content-vue)`, at `body:not(#body-login) #header`, at `#openregister-mail-sidebar` or at `.event-popover`
- AND a selector with `:has(` MUST start at `:is(#content, #content-vue)`
