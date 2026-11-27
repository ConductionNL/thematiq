# Design

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
which `app-menu.js`, the Menu settings dialog and the user menu call.

Why not hand-write a tour in `app-menu.js`: the review asks for the same mechanism and look as the other
apps, and ADR-043 rejects per-app tour code. The spotlight cutout, focus handling, live region,
progress and version gating are the engine's.

## The tour is data

`src/walkthrough/apps-menu.json` holds the tour as an ADR-043 `walkthrough` block: one tour
`apps-menu`, `completionConfigKey` `apps_menu_walkthrough_seen_version`, and its steps. Each step has an
`id`, a `title` and `body` (l10n keys, translated with `t('thematiq', ...)`), a `target` of kind
`element` (a `data-walkthrough-id` the menu script sets), a `placement`, `sinceVersion`, `optional`
where the part may be absent, and `advanceOn: manual`.

| # | id | target (`data-walkthrough-id`) | optional | since |
|---|---|---|---|---|
| 1 | `open` | `apps-menu-button` (the grid button) | no | release of this change |
| 2 | `apps` | `apps-menu-list` (the grid of entries) | no | same |
| 3 | `more` | `apps-menu-more` (the More / Fewer toggle) | yes, no fold toggle when every app fits above the fold | same |
| 4 | `reorder` | `apps-menu-first-entry` (the first entry) | no | same |
| 5 | `pin` | `apps-menu-pins` (the pinned apps, or the grid button when none is pinned) | no | same |
| 6 | `create` | `apps-menu-create` (the Create row) | yes, absent when create actions are off or nothing creates | same |
| 7 | `settings` | `apps-menu-settings` (the Menu settings gear) | no | same |

Steps advance with Next. They are not enforced action steps: the tour explains, the person may try.
What they try is real (a pin is a real pin), as it would be without the tour.

## When it starts

ADR-043's `first-visit` trigger starts a tour when the shell mounts. A page load is the wrong moment
here: the menu is closed and the person came for something else. So thematiq mounts the engine on the
**first open of the menu** on a page where a tour is due, with the menu open and held open. The engine
then sees a first visit (nothing seen) or a version bump (new steps since the seen version) and composes
the tour as it does in every app.

Due means one of:

- the person has no seen version (never toured);
- a step's `sinceVersion` is newer than the person's seen version;
- the person's progress names a tour that was left part-way (or the setup wizard asked for it, see
  first-time-setup).

`app-menu.js` reads this from the initial state the server already writes for the menu: the menu state
`thematiq/app-menu` gains `walkthrough: {seen, progress, appVersion}`. Without it, `app-menu.js` would
have to load the bundle to find out.

## Versions

thematiq ships nightly builds such as `1.2.48-unstable.20261127094823`. nextcloud-vue's
`compareSemver` splits on dots, so the build stamp would read as a fourth number and make every nightly
newer than the release that follows it. The server therefore hands the tour the version core
(`1.2.48`), and every `sinceVersion` is a version core: the release that ships the step. A step merged
during the 1.3 nightlies carries `1.3.0`.

A person's seen version is written when they finish or skip the tour, as that version core.

## While the tour runs

- The menu stays open. A click on the dimmer, the pointer leaving the menu or a hover timeout does not
  close it. The engine's cutout leaves the spotlit part clickable and focusable.
- Escape closes the tour first, not the menu. It counts as Skip: the seen version is written.
- Keyboard actions inside the spotlit part work as without the tour: Alt with an arrow key moves the
  focused entry, Alt+P pins or unpins it, Enter opens More.
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

- **Menu settings**: a "Replay the tour" button in the dialog footer, left of Reset to default. It closes
  the dialog, opens the menu and starts the whole tour from step 1. It is there for everyone, for the
  administrator in either scope.
- **User menu**: an entry "Apps menu tour" directly after Nextcloud's Help entry, or at the end of the
  user menu when Help is switched off. It opens the menu and starts the whole tour.

A replay writes the seen version again when it ends.

## Copy (draft until the boards land)

| step | English title | English body | Dutch title | Dutch body |
|---|---|---|---|---|
| open | Your apps | Click here to open your apps, or point at it. With the keyboard, press Enter on it; Escape closes the menu. | Je apps | Klik hier om je apps te openen, of wijs het aan. Met het toetsenbord druk je hier op Enter; Escape sluit het menu. |
| apps | All your apps in one place | Click an app to open it. The apps you use most stand at the top. | Al je apps op één plek | Klik op een app om hem te openen. De apps die je het meest gebruikt staan bovenaan. |
| more | More apps | More shows the rest of your apps, Fewer folds them away. Drag an app above or below this line to move it in or out of view. | Meer apps | Meer toont de rest van je apps, Minder klapt ze weer in. Sleep een app boven of onder deze lijn om hem in of uit beeld te zetten. |
| reorder | Your own order | Drag an app to another place. With the keyboard, Alt and an arrow key move the app you are on. | Je eigen volgorde | Sleep een app naar een andere plek. Met het toetsenbord verplaatsen Alt en een pijltjestoets de app waar je op staat. |
| pin | Pin to the top bar | Drag an app onto the top bar to pin it there, or press Alt+P. Drag it off, or press Alt+P again, to unpin it. You can pin up to 5 apps. | Vastzetten in de bovenbalk | Sleep een app naar de bovenbalk om hem daar vast te zetten, of druk op Alt+P. Sleep hem eraf, of druk nog eens op Alt+P, om hem los te maken. Je kunt tot 5 apps vastzetten. |
| create | Start something new | Create a new document, spreadsheet or presentation straight from here. It is saved in your files and opens at once. | Iets nieuws maken | Maak hier meteen een nieuw document, rekenblad of presentatie. Het komt in je bestanden en gaat direct open. |
| settings | Make it yours | Menu settings sets the order, pins and apps you see. You can replay this tour there too. | Maak het van jou | In Menu-instellingen kies je de volgorde, vastgezette apps en welke apps je ziet. Daar kun je deze rondleiding ook opnieuw bekijken. |

## Edge cases

- **Not an administrator**: everyone with the workplace menu gets the tour. Nothing in it is
  admin-only; the Menu settings step describes what a person can set for themselves.
- **Classic menu style**: no menu script loads, so no tour and no replay entry. The user menu entry
  is left out too.
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
