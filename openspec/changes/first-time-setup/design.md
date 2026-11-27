# Design

The boards are in ConductionNL/design-system#1213 (branch `design/thematiq-apps-menu-walkthrough`):
TqSetupWelkom, TqSetupHuisstijl, TqSetupHuisstijlLicentie, TqSetupTheming, TqSetupInlogpagina,
TqSetupAppmenu, TqSetupKlaar and TqSetupOpnieuw (the version card). They are canon; the copy below is
theirs. Ruben's answers of 27 November 2026 are marked where they apply.

## Where the wizard opens

Other apps open `CnSetupWizard` inside their own shell (`CnAppRoot`'s setup phase). thematiq has no
shell people visit: it changes every page. So the wizard opens from thematiq's global script, over
whatever page the administrator lands on (TqSetupWelkom draws it over the dashboard).

`SetupWizardListener` listens to `BeforeTemplateRenderedEvent`, beside `ThemeInjectionListener`. It acts
only when all of these hold, and otherwise adds nothing to the page:

1. the response renders as a user page (`TemplateResponse::RENDER_AS_USER`), not a guest, public,
   error or login page;
2. the signed-in user is in the `admin` group (`IGroupManager::isAdmin`). Delegated theming
   administrators and subadmins do not count (Ruben, 27 November 2026);
3. setup is due (below).

Then it provides the `setup_status` initial state (the document `GET /api/setup/status` answers, which
nextcloud-vue's `readPreloadedSetupStatus` reads) and adds `js/setup-wizard-loader.js`, a few lines that
add `js/vendor/setupWizard.js` once the page is idle. A non-admin's page, and an admin's page once setup
is done or skipped, carry neither: no state, no script, no request. The check costs a few app values
and a group lookup per admin page; the result is cached for the request.

The bundle mounts `CnSetupWizard` with thematiq's `setup` steps from `src/manifest.json`, in its own
container appended to `body`, so it never depends on the page's layout. It is built like the code editor
bundle (`webpack.setup-wizard.config.js`).

## Nothing changes until Apply

The boards collect every choice first and write once: step 5 ends with Apply ("Toepassen"), step 6 says
"Applied.", and the version card says "Nothing changes until you click Apply." So steps 2 to 4 and the
choices of step 5 write nothing. Apply runs one action, `apply`, with every choice. The server checks
the whole request before it writes anything, then writes in this order: the licence confirmation, the
token set, Nextcloud's theme, the login page, the apps menu, and `setup.completed`.

This replaces a per-step save. It also means a run closed with Later leaves nothing behind, so there is
no "run in progress" state to keep track of.

One thing is written before Apply: a token set the administrator uploads with "Upload your own set" is
stored among the own sets (as the settings page stores an upload), but it is not applied until Apply.

## When setup is due

Ruben's rule: completion is derived from the saved configuration, so an instance configured by an
earlier version counts as set up. The status therefore needs no migration:

- **set up** when the app value `setup.completed` or `setup.skipped` exists, or when the instance is
  **configured**;
- **configured** when any of these app values of `thematiq` is stored: `token_set`, `header_style`,
  `themed_contexts`, `hide_slogan`, `login_watermark`, `brand_stripe_placement`,
  `core_theming_follows_set`, or any `menu.<key>` default; or when a configuration source is set in
  `config.php` (`thematiq.config_source`, theme as code).

An instance upgraded from an earlier version with none of these stored is not configured, so the
wizard opens for its administrators (Ruben, 27 November 2026).

## The contract

ADR-042 names two routes, and thematiq serves both, for administrators only: no `#[NoAdminRequired]`,
so Nextcloud's middleware refuses anyone else with 403 before the controller runs, and the POST requires
CSRF. nextcloud-vue already treats a 403 on status as "set up" for the caller.

- `GET /apps/thematiq/api/setup/status` answers `{version, completed, skipped, configured, steps}`,
  each step `{done, detail}`, read from the stored values, and `current`: the stored choices the wizard
  fills in on Set up again.
- `POST /apps/thematiq/api/setup/action/{actionId}`:
  - `apply` with `{tokenSet, confirmRestricted, nextcloudThemeFollows, loginStyle, hideSlogan, menu}`.
    `tokenSet` goes through `ActiveTokenSetService::switchTo()`, so the licence gate, the audit entry and
    the saved version are those of the settings page; `confirmRestricted: true` first stores the
    confirmation through the licence service, as `POST /settings/tokensets/{id}/licence/confirmation`
    does. `nextcloudThemeFollows` applies the set to Nextcloud's theme through the `--sync-core` path
    and stores `core_theming_follows_set`. `loginStyle` (`house-style` or `nextcloud`) adds or removes
    `login` in `themed_contexts`; `hideSlogan` stores `hide_slogan`. `menu` goes through
    `AppMenuSettings::saveDefaults()`, the check of `POST /app-menu/defaults`. An unknown key or a
    wrong value refuses the whole request with 400 naming it, and nothing is stored. An unconfirmed
    restricted set without `confirmRestricted` answers 403 `restricted_set_not_confirmed`.
  - `skip`: stores `setup.skipped` (who, when) and changes nothing else.
  - any other id answers 404.

  Every write is in thematiq's audit trail with the actor, as the same write from the settings page is.

ADR-042's components also post to `/api/setup/config` for per-step saves. The boards apply at the end,
so thematiq's wizard has no use for it and the route is not added; nothing is left without a caller.

Under a configuration source the instance counts as configured, so the wizard never opens on its own,
and every write is refused with 423 by `ConfigSourceLockMiddleware`. The version card's Set up again is
disabled there, with the note the settings page already shows about who manages the house style.

## The steps

Every step is headed "Set up Thematiq, step <n> of 6" with the rail Welcome, House style, Nextcloud
theme, Login page, Apps menu, Done. Steps 1 to 5 have "Skip, keep Nextcloud as it is" and Later in the
footer; steps 2 to 5 add Back; steps 2 to 4 have Next and step 5 has Apply. The close button reads
"Later, continue on the next page" for assistive technology.

1. **Welcome** (TqSetupWelkom): what thematiq does, three points (Choose a house style, Nextcloud
   follows, An apps menu made to measure), the note that only administrators see the screen and that
   Later brings it back on the next page, and Start.
2. **House style** (TqSetupHuisstijl, TqSetupHuisstijlLicentie): the token sets as radio cards with
   colour swatches and a licence line ("Demo municipality, free to use", "Central government, open
   licence", "Restricted licence, confirmation needed"), Nextcloud default ("No house style, only the
   apps menu"), and "Upload your own set". A summary line of the chosen set (font, primary, accent,
   licence) and a link "All <n> sets in the gallery" that lists every set in the step. The cards show
   the stored set first, then the sets the instance suggests. A restricted set without a confirmation
   shows the warning "This house style belongs to <holder>", what the licence allows, the source package
   and version, the licence text, the statement "We are this organisation, or we have its permission to
   use this house style." with a checkbox, and "Your name and the time go into the audit log. Next works
   once you confirm this." Next stays disabled until it is ticked.
3. **Nextcloud theme** (TqSetupTheming): the switch "Keep the Nextcloud theme in line with the set",
   on by default, with "Also on the next change of set. Off: thematiq leaves the Nextcloud theme
   alone." Four cells show what changes: primary colour, background colour, logo (with a dark version)
   and favicon, each with what Nextcloud has now. A note says what Nextcloud has now is kept as a version
   in the log. Skipped when the house style is Nextcloud default.
4. **Login page** (TqSetupInlogpagina): "Login page style" with Follow the house style or Nextcloud
   default, the checkbox "Hide the Nextcloud slogan", and a preview of the login card.
5. **Apps menu** (TqSetupAppmenu): the switch "Use the thematiq apps menu" ("Off: Nextcloud keeps its own
   menu, the rest of the house style stays."), the style Workplace or Classic, columns 3 to 5, rows above
   More apps 1 to 3, and the switches Show payoff, Show Create and Show external apps, with "Change
   later: in the menu itself, under Menu settings, tab Configuration." The workplace apps menu shows
   only in the workplace top bar: when the menu is on and the chosen set does not draw that bar, Apply
   also stores `header_style` workplace (Ruben, 27 November 2026). Then Apply. Open point: the
   menu settings know one `style`, workplace or classic, and classic already means Nextcloud's own
   menu. So "on" with Classic reads the same as "off". Until the board or Ruben says otherwise, off
   stores classic and on stores the chosen style.
6. **Done** (TqSetupKlaar): "Done, <set> is set", "Applied.", "Everyone sees the house style on the next
   page they open. Version <n> is in the log.", a checked row per part (house style, Nextcloud theme,
   login page, apps menu), the invitation to the walkthrough, "You can always set up again from the
   version card in Administration settings, Theming.", and the buttons Close and "Start the apps menu
   walkthrough" (only when the apps menu is on). Start stores the walkthrough's progress with an
   immediate start, reloads the page in the new house style, opens the apps menu and starts the tour
   (change apps-menu-walkthrough, REQ-TQWT-002).

The walkthrough never starts while the wizard is open (REQ-TQWT-002).

## Later and Skip

Later closes the wizard and stores nothing; it comes back on the next page while setup is due. Skip
runs `skip`. Ruben's answer (27 November 2026) is that Skip keeps what earlier steps saved. With Apply at
the end, earlier steps have saved nothing except an uploaded set, which stays among the own sets. So
Skip leaves Nextcloud as it is, as its label says.

## Keeping Nextcloud's theme in line

TqSetupTheming's switch reaches past the wizard: "Also on the next change of set." The new app value
`core_theming_follows_set` holds it. When it is `1`, every later change of the instance's set (the
settings page, `occ thematiq:theme:set`, a planned switch) also applies the set to Nextcloud's theme, as
`--sync-core` and a planned switch already do, with the snapshot of the old theme as a version. When it
is `0` or absent, those paths behave as today. The settings page shows the value on the version card's
detail line ("Nextcloud theme kept in line"); a control for it on the settings page is not drawn yet and
is not part of this change.

## Set up again

The Theming settings page gets the version card of TqSetupOpnieuw, drawn as nextcloud-vue's
`CnVersionInfoCard`: "About this installation of Thematiq.", the installed version ("1.8.0, up to
date"), the row "First-time setup" with its state and detail, and three buttons: Set up again, Apps menu
walkthrough (change apps-menu-walkthrough) and "Help us: suggest an idea" (nextcloud-vue's
suggest-a-feature modal, ADR-042). The note under it: "Set up again opens the setup once more, with the
current choices filled in. Nothing changes until you click Apply."

The board draws the completed state: "Completed by admin on 27-11-2026, 13:52", detail "Token set
Gemeente Zuiddrecht applied, version 1 in the log", and "Applied on 27-11-2026, 13:52 by admin · Nextcloud
theme kept in line". The other states follow that pattern until the board draws them: "Skipped by
<user> on <date>", "Set up by an earlier version" for an instance configured before this version, and
"Not set up yet". Ruben left this wording to the boards.

A rerun closed with Later changes nothing, and `setup.completed` or `setup.skipped` stays. Applying a
rerun stores `setup.completed` again with the new actor and time.

## Edge cases

- **Non-admins**: never see the wizard, never load its script or state; the setup routes answer 403.
- **Delegated theming administrators** (`IDelegatedSettings`) and subadmins: treated as non-admins
  for the wizard; they keep their delegated settings pages (Ruben, 27 November 2026).
- **Upgrade from a configured earlier version**: configured, so set up; nothing shows, nothing is
  migrated. The version card reads "Set up by an earlier version".
- **Upgrade from an earlier version that was never configured**: not configured, so the wizard opens
  on the administrator's next page (Ruben, 27 November 2026).
- **Restricted sets**: Apply confirms and applies in one request through the same gate. If the server
  refuses (another administrator changed something in between), step 5 shows the refusal, and Back
  leads to step 2 to confirm again.
- **A write fails during Apply**: the whole request was checked first, so this is a fault, not a wrong
  value. Step 5 shows which parts were applied and which were not, and stays open so Apply can be
  pressed again. `setup.completed` is stored only when every part was applied.
- **Two administrators at once**: both may see the wizard. Each Apply is checked on its own; the last
  wins, as on the settings page. Once one applies or skips, the other's wizard does not return after
  their next page load.
- **Nextcloud's own first-run wizard** (`firstrunwizard`) is open: thematiq's wizard waits until it is
  closed, so two modals never stack.
- **Theming disabled for the current app** (per-app theming exclusion): the wizard still opens; it
  is about the whole instance.
- **Narrow screens**: the wizard is a full-screen dialog on a phone.
- **Accessibility**: `CnSetupWizard`'s dialog semantics, focus trap and step heading; the radio cards
  are a radio group; the switches are `role="switch"` with their label; the licence statement is a
  labelled checkbox; Later and Skip are buttons with their full text.

## Copy (from the boards)

| place | English | Dutch |
|---|---|---|
| counter | Set up Thematiq, step 1 of 6 | Thematiq instellen, stap 1 van 6 |
| welcome title | Welcome to Thematiq | Welkom bij Thematiq |
| welcome body | Thematiq gives Nextcloud your organisation's house style: colours, fonts, logo and the apps menu, on every screen. You set it up in five short steps. You can change every choice later in Administration settings, Theming. | Thematiq geeft Nextcloud de huisstijl van je organisatie: kleuren, lettertypen, logo en het appmenu, op elk scherm. In vijf korte stappen stel je het in. Elke keuze kun je later veranderen in Beheerinstellingen, Thema's en uiterlijk. |
| welcome note | Only administrators see this screen. Later closes it; it comes back on the next page you open, until you finish or skip. A Nextcloud that thematiq already set up does not see this. | Alleen beheerders zien dit scherm. Later sluit het; het komt terug bij de volgende pagina die je opent, tot je klaar bent of overslaat. Een Nextcloud die thematiq al eerder instelde, ziet dit niet. |
| buttons | Skip, keep Nextcloud as it is · Later · Start · Back · Next · Apply | Overslaan, Nextcloud blijft zoals het is · Later · Beginnen · Terug · Volgende · Toepassen |
| house style title | Choose the house style | Kies de huisstijl |
| house style intro | A house style is a token set: colours, fonts, corners and spacing in one file. Choose your organisation's set. You use another government's set only with its permission; thematiq asks for it in the next step. | Een huisstijl is een tokenset: kleuren, lettertypen, hoeken en afstanden in één bestand. Kies de set van je organisatie. Een set van een andere overheid gebruik je alleen met toestemming; thematiq vraagt daar in de volgende stap om. |
| licence warning | This house style belongs to <holder> | Deze huisstijl is van <holder> |
| licence statement | We are this organisation, or we have its permission to use this house style. | Wij zijn deze organisatie, of wij hebben haar toestemming om deze huisstijl te gebruiken. |
| theme title | Update the Nextcloud theme | Nextcloud-thema bijwerken |
| theme switch | Keep the Nextcloud theme in line with the set | Nextcloud-thema gelijk houden met de set |
| login title | The login page | De inlogpagina |
| login choices | Follow the house style · Nextcloud default · Hide the Nextcloud slogan | Volg de huisstijl · Nextcloud-standaard · Slogan van Nextcloud verbergen |
| apps menu title | The apps menu | Het appmenu |
| apps menu switch | Use the thematiq apps menu | Appmenu van thematiq gebruiken |
| done title | Done, <set> is set | Klaar, <set> staat |
| done buttons | Close · Start the apps menu walkthrough | Sluiten · Rondleiding door het appmenu starten |
| version card | Set up again · Apps menu walkthrough · Help us: suggest an idea | Opnieuw instellen · Rondleiding door het appmenu · Help ons: een idee doorgeven |
| card state | Completed by <user> on <date>, <time> | Afgerond door <user> op <date>, <time> |

The board's house style intro says thematiq asks for permission "in the next step", while
TqSetupHuisstijlLicentie draws the confirmation in the same step 2. The copy keeps the board's words
until the board settles which is meant.
