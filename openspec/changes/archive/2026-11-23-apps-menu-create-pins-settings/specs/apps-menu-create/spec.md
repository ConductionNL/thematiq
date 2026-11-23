# apps-menu-create

## ADDED Requirements

### Requirement: The apps menu has a title and a chevron on its toggle
The open workplace apps menu MUST start with a level-2 heading "Apps". The All apps / Hide apps
toggle MUST show a chevron before its text: pointing down while the rest is folded, pointing up while
it is unfolded. The chevron MUST be hidden from assistive technology; the button keeps its text and
`aria-expanded`.

#### Scenario: The chevron turns with the toggle
@e2e exclude a drawing detail of one button; proven by tests/vitest/appMenuCreate.spec.js (titles the panel and draws a chevron that turns with the toggle)
- GIVEN the open apps menu with more than nine entries
- WHEN All apps is pressed
- THEN the button MUST read "Hide apps" and its chevron MUST point up

### Requirement: The Create row offers what Nextcloud offers
Above the footer, the menu MUST show a "Create" heading and a group of round chips labelled by it:
New document, New spreadsheet, New presentation and New whiteboard, then More. The chips MUST come
from the template creators of the Files templates API (`GET /ocs/v2.php/apps/files/api/v1/templates`),
asked once per page and only when the menu opens. A kind MUST be shown only when a creator offers it:
document for a word processing, OpenDocument text or Markdown creator, spreadsheet and presentation
for their office mimetypes, whiteboard for the Whiteboard app. More MUST unfold every creator and
every Nextcloud navigation action (`core/navigationActions`) and fold them again; it carries
`aria-expanded` and `aria-controls`. Without any creator or action the heading and the row MUST NOT
be drawn. When the person switched create actions off, the menu MUST NOT ask the templates API.

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

### Requirement: A create action makes the file and opens it
A chip MUST create an empty file named after the creator's label with its extension in the person's
home folder, through `POST /ocs/v2.php/apps/files/api/v1/templates/create`. When the name is taken it
MUST try again with a number, "New document (2).docx", up to 20 tries. On success it MUST open the file
at `/f/<fileid>`. On failure the menu's polite live region MUST say "The file could not be created."

#### Scenario: A taken name gets a number
@e2e exclude creating files in a shared demo account leaves files behind; proven by tests/vitest/appMenuCreate.spec.js (creates the file in the home folder, numbers a taken name, and opens it)
- GIVEN "New document.docx" exists in the home folder
- WHEN New document is pressed
- THEN "New document (2).docx" MUST be created and opened
