# workplace-app-sheets

## MODIFIED Requirements

### Requirement: The app layer script acts only with its sheet (REQ-TQAS-005)
The app parts of `js/workplace-apps.js` MUST act only on their own app's page while that app's sheet is
on it: the sheet sets `--thematiq-app-layer` to its app on the root (`mail`, `calendar` or `deck`),
which replaces `--thematiq-workplace-apps`. On a Mail page the script MUST set "Select a conversation"
and on a Calendar page "New event", translated, as quoted CSS variables the sheets show in place of the
app's own text; on a Calendar page it MUST name week day headings as REQ-TQAS-003 says and keep doing so
after the app renders again; on a Deck page it MUST draw the card faces of REQ-TQAS-004. The input-mode
mark `data-thematiq-pointer` is no longer an app part: workplace-layout REQ-TQSS-005 sets it on every
page with the light layout. The script MUST write text only.

#### Scenario: Without the sheet
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js (a Calendar page without `--thematiq-app-layer: calendar` keeps its headings)
- GIVEN a Calendar page whose root does not name `calendar` in `--thematiq-app-layer`
- WHEN the script runs
- THEN the week day headings MUST stay as the app wrote them

#### Scenario: Another app's sheet does not wake Mail's words
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js
- GIVEN a Deck page with the Deck sheet
- WHEN the script runs
- THEN `--thematiq-mail-empty-title` MUST NOT be set

#### Scenario: The keyboard brings the focus ring back
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js (marks pointer use and clears the mark on a key press, on any page with the light layout)
- GIVEN the root is marked `data-thematiq-pointer`
- WHEN a key is pressed
- THEN the mark MUST be cleared
