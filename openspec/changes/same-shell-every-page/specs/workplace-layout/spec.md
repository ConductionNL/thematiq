# workplace-layout

## MODIFIED Requirements

### Requirement: The light layout draws Mail, Calendar and Deck as the boards
While the layout is `light`, a page of Mail, Calendar or Deck MUST load the app's own sheet
`css/workplace-apps/<app>.css` after the other conditional stylesheets, and no other page MUST load it.
`css/workplace-apps/brand.css` and `css/workplace-apps/common.css` MUST NOT exist: what they drew is
part of every page (REQ-TQSS-001 to REQ-TQSS-005, and workplace-header-brand REQ-TQWH-005). An app's
sheet MUST hold only what REQ-TQSS-006 lists for that app. The sheets MUST write no colour literal.
Every rule MUST start at the app's content element, at the header, at a panel another app mounts on
that page (OpenRegister's `#openregister-mail-sidebar`), or at Calendar's event popover
(`.event-popover`), which Calendar mounts at the end of the body. `:has()` MAY only be used inside a
rule that already starts at the app's content element, to read the app's own state (Mail: a
conversation is open); a browser without `:has()` MUST keep the app as it ships. The boards are
MlInbox, MlThreadList and MlThread for Mail, ClWeek, ClMonth and ClEventPopover for Calendar, TbBoard
and TbCard for Deck. The theme MUST NOT change the apps themselves; what a board draws that an app
cannot show is left out.

#### Scenario: Deck loads its sheets
@e2e exclude Unit check: tests/Unit/Service/CssInjectionServiceTest.php asserts the link order
- GIVEN the light layout and the workplace header
- WHEN a Deck page renders
- THEN `workplace-apps/deck` MUST be linked after `workplace-layout` and `header-workplace`
- AND neither `workplace-apps/brand` nor `workplace-apps/common` MUST be linked

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

## ADDED Requirements

### Requirement: Every app's navigation is drawn as the boards draw it (REQ-TQSS-001)
While the layout is `light`, `css/workplace-layout.css` MUST draw the standard app navigation of every
app (`#app-navigation-vue`) as MlNav, ClNav and TbNav draw it: entries 34 px high in 14 px regular
text, the active entry's label 600, a 16 px icon, a 10 px gap, the control radius and 1 px between
entries, with 34 px action buttons; captions on one line with the name in 12 px semibold capitals in
the muted text colour, cut with an ellipsis, and the caption's actions at the end; the new-item button
of `NcAppNavigationNew` as the filled primary, 34 px high in 14 px semibold. The selected entry MUST be
drawn the same in every app, by the navigation entry style the layout resolves
(`navigation-active-soft` when it is `soft`); no app MUST redeclare the accent tokens for its own pages.
The rules MUST start at `:where(body:not(#body-login)) :is(#content, #content-vue)` and MUST NOT name
an app's class. Nextcloud's legacy navigation (`#app-navigation` without the Vue component) MUST be
left alone.

#### Scenario: Files has the boards' navigation
- GIVEN the Zuiddrecht set and a 1440 px window
- WHEN a Files page renders
- THEN every entry of `#app-navigation-vue` MUST be 34 px high with a 14 px label
- AND no entry's text MUST be cut below its baseline

#### Scenario: The same selected entry in Files and Mail
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js finds no accent or navigation token redeclared under an app class in any sheet
- GIVEN the light layout with the soft entry style
- WHEN a Files page and a Mail page render
- THEN the selected entry MUST carry the same background and text colour on both

#### Scenario: The shared rules name no app
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the shared section
- GIVEN the section "Every app's standard structures" of `css/workplace-layout.css`
- WHEN its selectors are read
- THEN every selector MUST start at `:where(body:not(#body-login)) :is(#content, #content-vue)`
- AND no selector MUST contain `.app-mail`, `.app-calendar`, `.app-deck` or any other `.app-<id>` class

### Requirement: Every app's list rows are drawn as the boards draw them (REQ-TQSS-002)
While the layout is `light`, `NcListItem` rows in every app MUST be one column of rows with a hairline
in the border colour between them, not rounded tiles; the selected row MUST be the primary's light
tint with a 3 px primary edge at its start; an initials avatar in a row (`.list-item
.avatardiv__initials`) MUST be 32 px in the primary's light tint, and a picture avatar MUST keep its
picture and size. A link that is a row (a navigation entry, a list row, a table row, a card) MUST show
no underline and take the text colour. A link in running text MUST keep its underline. The rules MUST
set size, border and colour only, never `display`, `overflow` or `visibility` of an app's element.

#### Scenario: Contacts' list rows
- GIVEN the Zuiddrecht set and a contact selected in Contacts
- WHEN the list renders
- THEN the rows MUST be separated by a hairline
- AND the selected row MUST carry the primary's light tint and a 3 px primary edge

#### Scenario: A help link in Settings keeps its underline
- GIVEN the Zuiddrecht set
- WHEN the personal settings page renders
- THEN a link inside a settings section's text MUST be underlined

#### Scenario: Talk keeps its unread counter
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js finds no display, overflow or visibility declaration in the list-row rules; the e2e smoke opens Talk
- GIVEN a Talk conversation with unread messages
- WHEN the conversation list renders
- THEN its unread counter MUST stay visible

### Requirement: Every app's panels, buttons and empty states are drawn as the boards draw them (REQ-TQSS-003)
While the layout is `light`, in every app: the sidebar panel's title (`#app-sidebar-vue
.app-sidebar-header__mainname`) MUST be 20 px bold on a 26 px line, and its tabs a strip of folder
tabs in text, the selected one joined to the panel; an icon-only button that is not large MUST be a
square of the default clickable area; a heading an app focuses itself (the sidebar title) MUST show no
focus ring while the root carries `data-thematiq-pointer`, and MUST show it from the keyboard; an
empty state (`NcEmptyContent`) MUST show its icon centred in a 64 px circle of the main background with
a hairline, over its title in 20 px bold. Every colour MUST read a token.

#### Scenario: The Files sidebar
- GIVEN the Zuiddrecht set
- WHEN the sidebar of a file is opened
- THEN its title MUST be 20 px bold and its tabs a strip of folder tabs

#### Scenario: The keyboard keeps the ring
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the rule under `:root[data-thematiq-pointer]`
- GIVEN a sidebar whose title the app focuses
- WHEN the person reached it with the keyboard
- THEN the title MUST show the focus ring

#### Scenario: An empty folder
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js reads the empty state rules; the e2e smoke checks for no layout breakage
- GIVEN an empty folder in Files
- WHEN it renders
- THEN its icon MUST sit in a 64 px circle over a 20 px bold title

### Requirement: The navigation rail is one mechanism (REQ-TQSS-004)
`css/workplace-layout.css` MUST carry the navigation rail once: one registered property
`--thematiq-rail`, and the folded rules under `@container style(--thematiq-rail: 1)` on
`.app-navigation`: from 1024 px wide a 64 px column of icons with a small label under each; keyboard
focus in the column or an open menu MUST unfold it at once, the pointer after 200 ms; an entry without
an icon MUST NOT show in the rail; with reduced motion the pointer MUST unfold it at once; a browser
without style queries MUST keep the column unfolded. An app's sheet MUST only set `--thematiq-rail: 1`
on its navigation in the state its board folds it (Mail: a conversation is open; Deck: a card is open)
and MUST NOT declare a rail property or the folded rules of its own.

#### Scenario: Mail folds through the shared rail
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js finds no `@property` for a rail in the app sheets; compdiff MlThread
- GIVEN Mail in a 1440 px window
- WHEN a conversation is opened
- THEN the navigation MUST be 64 px wide with an icon and a label per folder

#### Scenario: Files never folds
@e2e exclude Static file check: tests/vitest/workplaceLayout.spec.js finds no rule setting `--thematiq-rail` in the shared section
- GIVEN a Files page with the sidebar open
- WHEN it renders
- THEN the navigation MUST keep its full width

### Requirement: The light layout's script acts on every page (REQ-TQSS-005)
While the layout is `light`, every page MUST load `js/workplace-apps.js`, whatever the header style.
`css/workplace-layout.css` MUST set `--thematiq-light-layout: 1` on the root, and the script MUST do
nothing while it is missing. On every page it MUST mark the root `data-thematiq-pointer` while the
person uses a pointer and clear it on a key press, and it MUST offer the words mechanism: a translated
word set as a quoted CSS variable on the root. It MUST write text only.

#### Scenario: Files gets the input mode
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js marks pointer use on a Files page and clears it on a key press
- GIVEN the light layout and a Files page
- WHEN the person clicks and then presses a key
- THEN the root MUST carry `data-thematiq-pointer` after the click and not after the key

#### Scenario: Without the light layout
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js and PHPUnit CssInjectionServiceTest
- GIVEN the default layout
- WHEN a page renders
- THEN `js/workplace-apps.js` MUST NOT be loaded, and run on its own it MUST change nothing

### Requirement: What stays app-specific is listed (REQ-TQSS-006)
An app's sheet under `css/workplace-apps/` MUST hold only content that exists in that app:
- Mail: its New message button mapped to the shared new-item button with a pencil and no refresh
  button; the 410 px list column and the list search field's inset; the date groups for screen readers
  only; the paperclip as the only attachment mark; the 8 px unread dot; the conversation as cards under
  a 20 px subject bar; attachment rows; Reply first; the empty reading pane with the envelope and
  "Select a conversation"; the rail's trigger; the account fold; `#openregister-mail-sidebar`.
- Calendar: the date picker and the navigation head's order; the grid; entries, past entries and
  unanswered or tentative entries; the month view's numbers, badge and chips; week day labels;
  "New event"; the event popover.
- Deck: the board's canvas and title row; the columns and their headings; card faces with labels,
  number, initials, short due date, project and mail lines and the priority mark; the card mid-move;
  the card panel's 480 px width and 18 px title; the rail's trigger.
Each sheet MUST set `--thematiq-app-layer` to its app on the root. The script MUST set an app's words
and run an app's redraw only on that app's page (`#content.app-<app>` or `#content-vue.app-<app>`) while
`--thematiq-app-layer` names it; the Deck API MUST be asked on a Deck page only.

#### Scenario: Files asks nothing of Deck
@e2e exclude Unit: vitest tests/vitest/workplaceApps.spec.js asserts no fetch outside a Deck page
- GIVEN the light layout and a Files page
- WHEN the script runs
- THEN the Deck API MUST NOT be asked and no Deck or Calendar redraw MUST start

#### Scenario: Mail's sheet holds no shared rule
@e2e exclude Static file check: tests/vitest/workplaceApps.spec.js finds no navigation-entry, caption, list-row or sidebar-tab rule in the app sheets other than the listed mappings
- GIVEN `css/workplace-apps/mail.css`
- WHEN its selectors are read
- THEN none MUST style `.app-navigation-entry`, `.app-navigation-caption` or `.app-sidebar-tabs__tab` on its own

### Requirement: No page pays more layout CSS than Mail paid (REQ-TQSS-007)
For every page, the stylesheets `LayoutStylesheets` gives it under the Zuiddrecht set MUST together
weigh no more than Mail's did on development at commit ae4a4e0d3: 94,592 bytes, and 24,669 bytes
gzipped at level 9. This holds for Files, Mail, Calendar, Deck and a page without an open app.

#### Scenario: Deck under the cap
@e2e exclude Static file check: tests/vitest/layoutBudget.spec.js sums the files per page
- GIVEN the Zuiddrecht set
- WHEN the stylesheets of a Deck page are summed
- THEN they MUST weigh at most 94,592 bytes and 24,669 bytes gzipped

#### Scenario: Files under the cap
@e2e exclude Static file check: tests/vitest/layoutBudget.spec.js sums the files per page
- GIVEN the Zuiddrecht set
- WHEN the stylesheets of a Files page are summed
- THEN they MUST weigh at most 94,592 bytes and 24,669 bytes gzipped

### Requirement: Apps without a layer keep their layout (REQ-TQSS-008)
Under the light layout and the workplace header, Files, the personal and the administration settings,
Talk and Contacts MUST open with no horizontal scroll of the page at 1440 px and at 390 px, with every
navigation entry's text inside its entry, with the content beside the navigation and not under it at
1440 px, and with the brand start in the bar.

#### Scenario: The four apps in the light layout
- GIVEN the Zuiddrecht set
- WHEN Files, the personal settings, the administration settings, Talk and Contacts are opened at 1440 px and at 390 px
- THEN no page MUST scroll sideways
- AND every navigation entry's text MUST sit inside its entry
- AND the bar MUST start with the organisation and the page's name
