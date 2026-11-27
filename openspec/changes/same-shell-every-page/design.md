# Design

The boards are canon: ConductionNL/design-system#1216 for the top bar of every page (`werkplek/DqKop`
with the brand start and the Create button, the `werkplek/AppMenu` family), and MlInbox, MlThreadList,
MlThread, ClWeek, ClMonth, ClEventPopover, TbBoard and TbCard for the app layout. Where this design and
a board differ, the build follows the board.

## What loads where, before and after

| Page, light layout | Today | After |
|---|---|---|
| Every page | `workplace-layout`, with the workplace header `header-workplace` | the same two sheets, now with the shared rules and the brand fallback |
| Mail, Calendar, Deck | plus `workplace-apps/brand` (workplace header), `workplace-apps/common`, `workplace-apps/<app>` | plus `workplace-apps/<app>` only, shrunk to its app content |
| `js/workplace-apps.js` | every page with the workplace header; acts only with `common.css` | every page with the light layout; the general part acts with `workplace-layout.css`, an app part only with its own sheet |

`LayoutStylesheets::APP_LAYERS` stays (`mail`, `calendar`, `deck`), but now means "an app with content
of its own that a board draws". `forApp()` returns `['workplace-apps/' . $appId]` or nothing.

## (a) The brand start on every page

Most of this exists already. `js/header-brand.js` draws the board's brand block (emblem, organisation
over the app's name, divider) on every page with the workplace header, and `css/header-workplace.css`
hides Nextcloud's logo and current-app name so that a Conduction app's own brand has the start of the
bar. What differs per page today:

1. **The first paint.** Only Mail, Calendar and Deck load `brand.css`, which shows Nextcloud's logo
   and the app's name until the script has drawn the block (`#header:not([data-thematiq-brand])`).
   Files and every other app show an empty start until then. The rules of `brand.css` move into
   `css/header-workplace.css`, with one more condition: they step back while the bar holds an app's own
   brand (`#header:has(> .cn-brand-bar-host)`) or the page holds one in its navigation
   (`body:has(.cn-app-nav__brand)`). Without `:has()` the bar keeps the hidden start it has today, so an
   old browser loses the fallback only, never shows two brands.
2. **A page without an open app.** `resolveBrand()` reads the active entry of `core/apps`, which holds
   only apps. A settings page has none, so no block is drawn. The script then reads the active entry of
   Nextcloud's settings navigation (`core/settingsNavEntries`, which the user menu already uses) and
   names it ("Personal settings", "Administration settings"), translated as Nextcloud translates it.
   With neither, the block shows the emblem and the organisation alone, never an empty name. The
   builder confirms the state key on the supported Nextcloud versions before relying on it.

## (b) What moves where

The three sheets were read rule by rule. A rule moves into the general light layout when its selector,
after dropping the app's root (`.app-mail`, `.app-calendar`, `.app-deck`), names only standard
Nextcloud structures, and when at least two of the three apps carry it, or `common.css` carries it. It
stays with its app when it names the app's own markup or draws content only that app has.

### Into `css/workplace-layout.css` (section "Every app's standard structures")

Every rule starts at `:where(body:not(#body-login)) :is(#content, #content-vue)` and names only
Nextcloud's structural ids and `@nextcloud/vue` component classes. No app class appears in it.

| Shared rule | Comes from | Applies to |
|---|---|---|
| Navigation rows: 34 px, 14 px regular, the active one 600, a 16 px icon, a 10 px gap, the control radius, 1 px between rows, 34 px action buttons | mail.css "The folders", calendar.css and deck.css "The navigation" | `#app-navigation-vue .app-navigation-entry` and its link, icon, name, utils |
| Navigation captions: one line, the name 12 px semibold capitals in muted text with an ellipsis, the actions at the end | common.css, mail.css "An account's caption", calendar.css | `#app-navigation-vue .app-navigation-caption` |
| The new-item button: the filled primary, 34 px, 14 px semibold | mail.css "New message", calendar.css "new-event" | `NcAppNavigationNew` (`.app-navigation-new .button-vue`); Mail's and Calendar's own buttons map to it in their residue (below) |
| The selected navigation entry | common.css (the primary's light tint) | every app alike; see open question 1 |
| Rows without link underlines, in the text colour | common.css | links that are rows: `.app-navigation-entry`, `.list-item`, table rows (`tr a`), cards. Prose links keep their underline (WCAG 1.4.1); see risks |
| List rows: one column of rows with a hairline between them, not rounded tiles; the selected row in the primary's light tint with a 3 px primary edge; an initials avatar 32 px in the primary's tint | mail.css "The list", "Senders are initials" | `NcListItem` (`.list-item`, `.list-item--active`, `.list-item .avatardiv__initials`) |
| Icon-only buttons are squares | common.css | `.button-vue--icon-only:not(.button-vue--size-large)` |
| The sidebar panel: the title 20 px bold, its tabs as a strip of folder tabs in text | common.css, deck.css "The card panel" | `#app-sidebar-vue .app-sidebar-header__mainname`, `.app-sidebar-tabs__*` |
| A heading an app focuses itself shows no ring under the pointer | deck.css (card title), the `data-thematiq-pointer` rule | `#app-sidebar-vue .app-sidebar-header__mainname` under `:root[data-thematiq-pointer]` |
| Empty states: the icon in a 64 px circle of the main background with a hairline, the title 20 px bold, centred | mail.css "With no conversation open" | `NcEmptyContent` (`.empty-content`) |
| The navigation rail: 64 px of icons with a small label under each, unfolding at once on keyboard focus or an open menu and after 200 ms under the pointer; without style queries the column stays unfolded | mail.css and deck.css, each with its own `@property` | one `@property --thematiq-rail` and one set of `@container style(--thematiq-rail: 1)` rules on `.app-navigation`; an app's residue only says when (below) |
| The input-mode mark `data-thematiq-pointer` on the root and the words mechanism (translated words as quoted CSS variables, `cssString()`) | js/workplace-apps.js | every page with the light layout; the gate becomes `--thematiq-light-layout: 1`, set by `workplace-layout.css` |

The boards' words are app words: "Select a conversation" (Mail) and "New event" (Calendar). No word is
shared by the three boards, so the mechanism moves and the two words stay with their app.

### Stays app-specific (listed, loaded on that app's pages only)

**Mail** (`css/workplace-apps/mail.css`):
- New message: Mail's own button class mapped to the shared new-item button, its pencil, no refresh button
- the 410 px list column (`.splitpanes__pane-list`) and the list search field's inset
- the date groups for screen readers only, the paperclip as the only attachment mark, the 8 px unread dot
- the conversation as white cards under a subject bar at 20 px; attachment rows with a typed badge; Reply first
- the empty reading pane: the envelope over "Select a conversation" (`.no-message-selected__*`), and its word
- the rail's trigger (a conversation is open, `#mail-thread-header`) and the account fold of the navigation
- OpenRegister's `#openregister-mail-sidebar`

**Calendar** (`css/workplace-apps/calendar.css`):
- the date picker beside its arrows and the navigation head in the boards' order
- the grid: white with hairlines, the weekend darker, no greyed row under the pointer
- entries: the calendar's tint with a 3 px edge, past entries faded, unanswered or tentative entries dashed
- the month view: plain day numbers, today's badge, timed entries as chips, other months' days muted
- week day labels "Mon 12" (with `shortenDays()` in the script) and the "New event" word
- the event popover (`.event-popover`)

**Deck** (`css/workplace-apps/deck.css`):
- the board: the white canvas, the 60 px title row with the number of open cards
- the 224 px grey columns, their heading with a status dot, count pill and plus, the list menu
- card faces: labels as coloured capitals, the number, the person's initials, the short due date, the project and mail lines, the priority mark, the card menu on hover (with `drawDeckCards()` and the Deck API read in the script)
- the card mid-move (lifted, tilted only without reduced motion)
- the card panel's 480 px width and its 18 px title (TbCard)
- the rail's trigger (a card is open)

The residue rules keep today's root (`:is(#content, #content-vue).app-<app>`, the header, the popover,
the OpenRegister panel). The vitest root check stays for `css/workplace-apps/`, and a new check holds
the shared section of `workplace-layout.css` to the general root without any `.app-` class.

## How the script is split

`js/workplace-apps.js` stays one file (it is already loaded on every page with the workplace header, so
splitting it would add a request and save nothing). Its main function:

1. returns unless `--thematiq-light-layout` is `1` on the root (set by `workplace-layout.css`);
2. sets the pointer mark listeners;
3. sets an app's words and starts an app's redraw only while the page is that app's
   (`#content.app-<app>` or `#content-vue.app-<app>`) and its sheet is on the page: each residue sheet
   sets `--thematiq-app-layer: <app>` on the root, which replaces the single `--thematiq-workplace-apps`.

The script is loaded where the light layout's sheets are emitted, not from `HeaderUserService`, so a
light layout with Nextcloud's own header gets the input mode too. The Deck API read stays behind the Deck
check; no other page ever asks it.

## Risk to apps that never had a layer

| Where | What may break | How it is held |
|---|---|---|
| Files | Navigation rows from 42 to 34 px; the quota and settings entries at the foot of the navigation; the shell canon (`tests/css/check-shell-canon.mjs`) measures 42 | e2e smoke; the canon is updated or a KNOWN line written (open question 2) |
| Files | Links in table rows lose their underline: intended. The README block and the sidebar's prose keep theirs | the row rule names row structures only, never `#app-content-vue a` |
| Settings | Help and documentation links in `.section` prose must keep their underline; the admin navigation is the legacy `#app-navigation` | the shared rules name `#app-navigation-vue` only; e2e checks an underline on a settings help link |
| Talk | Its conversation list is `NcListItem` inside the navigation: rows lose their rounded tiles, the selected one gets the 3 px edge; unread counters and status dots must stay visible; its call view must not be touched | the list-row rules set geometry and colour only, never `display` or `overflow`; e2e smoke on Talk's list and an open conversation |
| Contacts | Its list is `NcListItem` in `#app-content-vue`, its groups are navigation entries; the 32 px initials avatar must not shrink a picture avatar | the avatar rule names `.avatardiv__initials` only |
| Any app | An icon-only button becomes a square: a button with a hidden label but wide content could clip | the rule leaves `size-large` alone; e2e checks no horizontal overflow on the four apps |
| Conduction apps | They use `@nextcloud/vue` too, so their navigation rows, list rows and sidebar take the shared rules; DqZijbalk draws the selected entry in the accent | top-bar and sidebar compdiff on `dossiq/DqZaken`; open question 4 |
| Old browsers | No `:has()` or no style queries | the brand fallback and the rail step back, as today |

Nothing here writes a colour literal, `display: none` on an app's control, or a rule on an element
outside `#content`, `#content-vue` and `#header`.

## How it is verified

1. **Compdiff, the three apps stay matched.** On the demo stack with the Zuiddrecht set: MlInbox,
   MlThreadList, MlThread, ClWeek, ClMonth, ClEventPopover, TbBoard and TbCard pair as they did on
   development before this change, flagging nothing new except the region open question 1 names. A
   rule that moved and changes a pixel is a bug in the move.
2. **Compdiff, the bar.** `werkplek/DqKop` and the #1216 top bars on Files and Settings: the brand
   start pairs.
3. **E2E smoke on apps without a layer.** `tests/e2e/spec-coverage/same-shell-every-page.spec.ts`
   under Zuiddrecht at 1440 and 390 px: Files, personal and admin Settings, Talk and Contacts open
   with no horizontal overflow, no navigation entry clipped below its text, the brand start in the bar,
   the shared row height in the navigation, a settings help link still underlined. An app the instance
   lacks is reported as skipped with its name, not passed.
4. **Speed.** A vitest budget test sums the bytes of every stylesheet `LayoutStylesheets` gives a
   page under Zuiddrecht, for Files, Mail, Calendar, Deck and a page without an app. No page may pass
   what Mail paid on development at this change's base (commit ae4a4e0d3): 94,592 bytes, 24,669
   gzipped (`gzip -9`), the sum of `workplace-layout`, `header-workplace`, `brand-stripe`,
   `brand-stripe-login-only`, `navigation-width`, `navigation-active-soft`, `workplace-apps/brand`,
   `workplace-apps/common` and `workplace-apps/mail`. Deck pays 97,012 today, so its sheet must lose at
   least 2,420 bytes in the move; the shared navigation, rail and panel rules it drops are larger than
   that. The page budget of `tests/e2e/performance.spec.ts` stays green.

## Open questions for Ruben

1. **The selected navigation entry.** Mail, Calendar and Deck draw it in the primary's light tint
   (MlNav, ClNav, TbNav); every other app follows the navigation entry style, which on Zuiddrecht is the
   accent on its tint (DqZijbalk, #A30000 on #FCEDEC). One must win. The spec follows the
   administrator's option on every page, so the three apps turn to the accent and their boards are
   redrawn; compdiff names that region until they are. The other way round makes the option
   meaningless under the light layout.
2. **The navigation row height.** The three boards draw 34 px rows; the shell canon (and the
   OpenProject plugin built to it) says 42 px, measured on Files. The spec takes 34 px for every app and
   moves the canon. If 42 px wins, the three boards are redrawn instead.
3. **The panel title.** TbCard draws Deck's card title at 18 px, every other sidebar title is 20 px.
   Kept as Deck's own, listed above; say if it should be 20 px too.
4. **Conduction apps.** Do dossiq, pipelinq and the rest take the shared navigation and list rows, or
   do their own boards (DqZijbalk) win where they differ? The spec applies the shared rules to every
   app that uses the standard structures, Conduction apps included.
