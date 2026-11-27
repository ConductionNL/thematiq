# apps-menu-create

## MODIFIED Requirements

### Requirement: The Create row offers what Nextcloud offers
Above the footer, the menu MUST show a "Create" heading and a group of round chips labelled by it:
New document, New spreadsheet, New presentation and New whiteboard, then More. The chips MUST come
from the template creators of the Files templates API (`GET /ocs/v2.php/apps/files/api/v1/templates`),
asked once per page and only when the menu opens. A kind MUST be shown only when a creator offers it:
document for a word processing, OpenDocument text or Markdown creator, spreadsheet and presentation
for their office mimetypes, whiteboard for the Whiteboard app. More MUST unfold every creator and
every Nextcloud navigation action (`core/navigationActions`) and fold them again; it carries
`aria-expanded` and `aria-controls`. Without any creator or action the heading and the row MUST NOT
be drawn. With the effective `showCreateRow` off the heading and the row MUST NOT be drawn. The
menu MUST share the Create actions (`window.thematiqCreateActions`) whenever `showCreateButton` or
`showCreateRow` is on, so Quick add works without the row; with both off it MUST NOT share them and
MUST NOT ask the templates API.

#### Scenario: A kind no installed app offers is left out
@e2e exclude installing or removing an office app changes the shared instance; proven by tests/vitest/appMenuCreate.spec.js (shows a chip per kind an installed app offers, and no whiteboard without one)
- GIVEN ONLYOFFICE and Text are installed and Whiteboard is not
- WHEN the apps menu opens
- THEN the row MUST show New document, New spreadsheet, New presentation and More, and no New whiteboard

#### Scenario: More unfolds the full list
@e2e exclude depends on the installed creators; proven by tests/vitest/appMenuCreate.spec.js (unfolds every creator and Nextcloud action under More)
- GIVEN the open apps menu
- WHEN More is pressed
- THEN every creator and every Nextcloud navigation action MUST show, and `aria-expanded` MUST be true

#### Scenario: The Create row switched off leaves Quick add working
@e2e exclude covered by the e2e scenario "Only the Create button" of apps-menu-settings; the sharing is proven by tests/vitest/appMenuCreate.spec.js (shares the Create actions with the row switched off)
- GIVEN `showCreateRow` is off and `showCreateButton` is on
- WHEN the apps menu script runs on a Mail page
- THEN the Create row MUST NOT be drawn
- AND `window.thematiqCreateActions` MUST list the Create actions
