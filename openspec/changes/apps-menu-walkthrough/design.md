# Design

The boards are in ConductionNL/design-system#1213 (branch `design/thematiq-apps-menu-walkthrough`):
TqMenuRondleiding (step 1 and the overview of the seven steps), TqMenuRondleidingApps,
TqMenuRondleidingMeer, TqMenuRondleidingOrdenen, TqMenuRondleidingVastzetten, TqMenuRondleidingNieuw,
TqMenuRondleidingInstellingen and TqMenuRondleidingOpnieuw (the replay button, added to
AppMenuInstellingen). They are canon; the copy below is theirs.

## The engine: CnWalkthrough in a bundle of its own

thematiq's pages are hand-written JavaScript served as it is. The one bundled file today is the
playground's code editor (`webpack.code-editor.config.js` builds `src/codeEditor.js` into
`js/vendor/codeEditor.js`). The tour follows that pattern: `src/menuWalkthrough.js` imports
`CnWalkthrough` and `useWalkthrough` from `@conduction/nextcloud-vue` (already a dependency) and mounts
them; `webpack.menu-walkthrough.config.js` builds it into `js/vendor/menuWalkthrough.js`, and
`npm run build` runs it.

The bundle is not on every page. `js/app-menu.js` decides from the menu's initial state whether a tour
is due, and only then adds the script. A replay adds it on demand. The speed budget (ADR-124) is spent
only when a tour runs. The bundle exposes one global, `window.thematiqMenuWalkthrough.start({mode})`,
which `app-menu.js`, the Menu settings dialog, the account menu entry and the version card call.

Why not hand-write a tour in `app-menu.js`: the review asks for the same mechanism and look as the other
apps, and ADR-043 rejects per-app tour code. The spotlight cutout, focus handling, live region,
progress and version gating are the engine's.

## The tour is data

`src/walkthrough/apps-menu.json` holds the tour as an ADR-043 `walkthrough` block: one tour
`apps-menu`, `completionConfigKey` `apps_menu_walkthrough_seen_version`, and its steps. Each step has an
`id`, a `title` and `body` (l10n keys, translated with `t('thematiq', ...)`), the keyboard hints its
board draws, a `target` of kind `element` (a `data-walkthrough-id` the menu script sets), a
`placement`, `sinceVersion`, `optional` where the part may be absent, and `advanceOn: manual`.

| # | id | board | target (`data-walkthrough-id`) | optional |
|---|---|---|---|---|
| 1 | `open` | TqMenuRondleiding | `apps-menu-button` (the grid button) | no |
| 2 | `apps` | TqMenuRondleidingApps | `apps-menu-list` (the first apps) | no |
| 3 | `more` | TqMenuRondleidingMeer | `apps-menu-more` (the More apps / Fewer apps toggle and the line) | yes, no toggle when every app fits above the fold |
| 4 | `reorder` | TqMenuRondleidingOrdenen | `apps-menu-first-entry` | no |
| 5 | `pin` | TqMenuRondleidingVastzetten | `apps-menu-pins` (the pinned apps, or the grid button when none is pinned) | no |
| 6 | `create` | TqMenuRondleidingNieuw | `apps-menu-create` (the Create row) | yes, absent when create actions are off or nothing creates |
| 7 | `settings` | TqMenuRondleidingInstellingen | `apps-menu-settings` (the Menu settings gear) | no |

Every step's `sinceVersion` is the release that ships this change (version core).

Steps advance with Next. They are not enforced action steps: the tour explains, the person may try.
What they try is real (a pin is a real pin), as it would be without the tour.

## The card

As the boards draw it: a dialog card beside the spotlit part, headed "Step 1 of 7" ("Stap 1 van 7"),
the step title as its heading, the body, the keyboard hints as key caps with their text, and a close
button "Close walkthrough" ("Rondleiding sluiten"). Step 1 has Next ("Volgende"); steps 2 to 6 have Back
("Terug") and Next; step 7 has Back and Done ("Klaar"). The boards draw no separate Skip button: the
close button is the way out, and counts as finishing for the seen version.

## When it starts

ADR-043's `first-visit` trigger starts a tour when the shell mounts. A page load is the wrong moment
here: the menu is closed and the person came for something else. So thematiq mounts the engine on the
**first open of the menu** on a page where a tour is due, with the menu open and held open. The engine
then sees a first visit (nothing seen) or a version bump (new steps since the seen version) and composes
the tour as it does in every app.

Due means one of:

- the person has no seen version (never toured);
- a step's `sinceVersion` is newer than the person's seen version;
- the person's progress names a tour that was left part-way.

One exception to "on the first open": the first-time setup's "Start the apps menu walkthrough"
(TqSetupKlaar) stores progress that asks for an immediate start. The page reloads in the new house
style, the menu opens by itself and the tour starts.

`app-menu.js` reads all this from the initial state the server already writes for the menu: the menu
state `thematiq/app-menu` gains `walkthrough: {seen, progress, appVersion}`. Without it, `app-menu.js`
would have to load the bundle to find out.

## Versions

thematiq ships nightly builds such as `1.2.48-unstable.20261127094823`. nextcloud-vue's
`compareSemver` splits on dots, so the build stamp would read as a fourth number and make every nightly
newer than the release that follows it. The server therefore hands the tour the version core
(`1.2.48`), and every `sinceVersion` is a version core: the release that ships the step. A step merged
during the 1.3 nightlies carries `1.3.0`.

A person's seen version is written when they press Done or close the tour, as that version core. As
TqMenuRondleidingOpnieuw says: after a new menu feature the tour shows only the new step by itself; a
replay always walks every step.

## While the tour runs

- The menu stays open. A click on the dimmer, the pointer leaving the menu or a hover timeout does not
  close it. The engine's cutout leaves the spotlit part clickable and focusable.
- Escape closes the tour first, not the menu, and counts as closing it.
- Keyboard actions inside the spotlit part work as without the tour: Alt with an arrow key moves the
  focused entry, Alt+P pins or unpins it, Enter opens More apps.
- Leaving the page mid-tour keeps the progress; the next open of the menu resumes at that step.
- If the first-time setup wizard is open on the page, the tour does not start; it waits for the next
  open of the menu.
- `prefers-reduced-motion`: the engine draws no transitions.

## Persistence

The engine reads and writes the seen version and progress through the nextcloud-vue preference
contract, `GET` / `PUT /apps/thematiq/api/preferences/{key}`. thematiq does not depend on OpenRegister,
so it implements the two routes itself in `PreferencesController`, for the signed-in person only, and
for exactly two keys: `apps_menu_walkthrough_seen_version` and `apps_menu_walkthrough_seen_version-progress`.
Any other key answers 404. Values are user values of `thematiq` with the same names. The engine's
localStorage mirror stays as its offline fallback; the server value wins.

## Replay

The boards name the places (TqMenuRondleidingInstellingen: "Menu settings, Replay walkthrough. Or from
Help in your account menu."):

- **Menu settings**: "Replay walkthrough" ("Rondleiding opnieuw starten") in the dialog footer, between
  Reset to default and Cancel (TqMenuRondleidingOpnieuw, AppMenuInstellingen). It closes the dialog,
  opens the menu and starts the whole tour from step 1. It is there for everyone, for the administrator
  in either scope.
- **Account menu**: the same "Replay walkthrough" entry directly under Nextcloud's Help entry, or at
  the end of the account menu when Help is switched off. This settles the earlier open question on its
  place: the boards put it under Help.
- **Version card**: "Apps menu walkthrough" ("Rondleiding door het appmenu") on thematiq's version card
  for administrators (TqSetupOpnieuw, change first-time-setup).

A replay writes the seen version again when it ends.

## Copy (from the boards)

| step | English title | English body | Dutch title | Dutch body |
|---|---|---|---|---|
| open | Open the apps menu | This walkthrough starts the first time you open the menu. Click the button with the nine dots, or rest the pointer on it for a moment. A click keeps the menu open. Keys: Enter opens the menu with focus inside it, Escape closes it and puts focus back on the button. | Open het appmenu | Deze rondleiding start de eerste keer dat je het menu opent. Klik op de knop met de negen stipjes, of houd de muis er even op. Een klik houdt het menu open. Toetsen: Enter opent het menu met de focus erin, Escape sluit het en zet de focus terug op de knop. |
| apps | Your apps in a row | The apps you use most are at the top: icon, name and one line on what the app does. The app you are working in is marked. Tab and the arrow keys move you through the list. | Je apps op een rij | De apps die je het meest gebruikt staan bovenaan: icoon, naam en in één regel wat de app doet. De app waarin je nu werkt is gemarkeerd. Met Tab en de pijltjestoetsen loop je door de lijst. |
| more | More and fewer | More apps unfolds the rest of your apps, Fewer apps folds them away. The line is the border: drag an app from below the line to above it and it stays with your first apps. Drag one down and it goes to More. Keys: Alt with Arrow up moves the focused app up across the line, Arrow down puts it below. | Meer en minder | Meer apps klapt de rest van je apps uit, Minder apps klapt ze weer in. De lijn is de grens: sleep een app van onder de lijn naar boven en hij staat voortaan bij je eerste apps. Sleep er een omlaag en hij gaat naar Meer. Toetsen: Alt met Pijl omhoog haalt de app met focus over de lijn naar boven, Pijl omlaag zet hem eronder. |
| reorder | Put them in your order | Pick an app up and drop it where you want it. Your order is saved and follows you to every browser you sign in to. After a move you hear the new place, for example Portaliq moved to place 4 of 14. Keys: Alt with Arrow left or up, one place earlier; Arrow right or down, one place later. | Zet ze in jouw volgorde | Pak een app op en laat hem los waar je hem wilt hebben. Je volgorde wordt bewaard en reist mee naar elke browser waarin je inlogt. Na een verplaatsing hoor je de nieuwe plek, bijvoorbeeld Portaliq verplaatst naar plek 4 van 14. Toetsen: Alt met Pijl links of omhoog, een plek eerder; Pijl rechts of omlaag, een plek later. |
| pin | Pin an app to the top bar | Drag an app from the menu to the top bar and drop it on the dashed slot. It is then always one click away. Drag a pinned app off the bar to unpin it. You can pin five. Keys: Alt+P pins the focused app, or unpins it. | Zet een app vast in de bovenbalk | Sleep een app uit het menu naar de bovenbalk en laat los op het gestippelde vak. Zo staat hij altijd één klik weg. Sleep een vastgezette app van de balk af om hem weer los te maken. Je kunt er vijf vastzetten. Toetsen: Alt+P zet de app met focus vast, of maakt hem los. |
| create | Create | Start a new document, spreadsheet, presentation or whiteboard here. The file lands in your own folder and opens at once. More shows everything else Nextcloud can create. | Nieuw maken | Begin hier een nieuw document, spreadsheet, presentatie of whiteboard. Het bestand komt in je eigen map en opent meteen. Meer toont alles wat Nextcloud verder kan aanmaken. |
| settings | Menu settings | Here you choose which apps you see, in which order and which are pinned, and switch the buttons under Create on or off. Administrators set the default for everyone here. Hint: Want to see the walkthrough again? Menu settings, Replay walkthrough. Or from Help in your account menu. | Menu-instellingen | Hier kies je welke apps je ziet, in welke volgorde en welke vastgezet zijn, en zet je de knoppen onder Nieuw maken aan of uit. Beheerders stellen hier de standaard voor iedereen in. Hint: Rondleiding nog eens zien? Menu-instellingen, Rondleiding opnieuw starten. Of via Hulp in je accountmenu. |

Card and replay labels: Step 1 of 7 / Stap 1 van 7, Next / Volgende, Back / Terug, Done / Klaar, Close
walkthrough / Rondleiding sluiten, Replay walkthrough / Rondleiding opnieuw starten.

## Edge cases

- **Not an administrator**: everyone with the workplace menu gets the tour. Nothing in it is
  admin-only; the Menu settings step says administrators set the default for everyone.
- **Classic menu style**: no menu script loads, so no tour and no replay entry in the account menu.
- **Upgrade from a version without the tour**: a person has no seen version, so the whole tour starts on
  their first open of the menu after the upgrade. That is the intent: it is new to everyone.
- **Upgrade that adds a step**: only the steps newer than the person's seen version run, on their next
  open of the menu.
- **A part is absent**: the `more` and `create` steps are optional and skipped when their part is not
  drawn. The counter counts only the steps shown.
- **No pins yet**: the pin step spotlights the grid button and the bar beside it, where a pin lands.
- **Narrow screens**: the engine places the card where it fits; on a phone the card sits under the
  menu.
- **Two tabs**: the second tab learns about the seen version on its next page load; at worst it starts a
  tour once more.
