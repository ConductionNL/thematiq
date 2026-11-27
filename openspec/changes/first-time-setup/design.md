# Design

## Where the wizard opens

Other apps open `CnSetupWizard` inside their own shell (`CnAppRoot`'s setup phase). thematiq has no
shell people visit: it changes every page. So the wizard opens from thematiq's global script, over
whatever page the administrator lands on.

`SetupWizardListener` listens to `BeforeTemplateRenderedEvent`, beside `ThemeInjectionListener`. It acts
only when all of these hold, and otherwise adds nothing to the page:

1. the response renders as a user page (`TemplateResponse::RENDER_AS_USER`), not a guest, public,
   error or login page;
2. the signed-in user is in the `admin` group (`IGroupManager::isAdmin`);
3. setup is due (below).

Then it provides the `setup_status` initial state (the document `GET /api/setup/status` answers, which
nextcloud-vue's `readPreloadedSetupStatus` reads) and adds `js/setup-wizard-loader.js`, a few lines that
add `js/vendor/setupWizard.js` once the page is idle. A non-admin's page, and an admin's page once setup
is done or skipped, carry neither: no state, no script, no request. The check costs two app values and a
group lookup per admin page; the result is cached for the request.

The bundle mounts `CnSetupWizard` with thematiq's `setup` steps from `src/manifest.json`, in its own
container appended to `body`, so it never depends on the page's layout. It is built like the code editor
bundle (`webpack.setup-wizard.config.js`).

## When setup is due

Ruben's rule: completion is derived from the saved configuration, so an instance configured by an
earlier version counts as set up. The status therefore needs no migration:

- **set up** when the app value `setup.completed` or `setup.skipped` exists, or when the instance is
  **configured** and no wizard run is in progress;
- **configured** when any of these app values of `thematiq` is stored: `token_set`, `header_style`,
  `hide_slogan`, `login_watermark`, `brand_stripe_placement`, or any `menu.<key>` default; or when a
  configuration source is set in `config.php` (`thematiq.config_source`, theme as code).

`CnSetupWizard` saves each step when the administrator presses Next (ADR-042). Without care, the first
saved step would make the instance "configured" and the wizard would not come back after Later. So the
first write through the setup contract stores `setup.inProgress` (who and when), and while it is there
and neither `setup.completed` nor `setup.skipped` is, setup stays due. Finish stores `setup.completed`,
Skip stores `setup.skipped`; both remove `setup.inProgress`.

The status endpoint reports each step as done from the stored values, so a wizard that comes back after
Later opens at the first step not done:

| step | done when |
|---|---|
| `welcome` | a wizard run is in progress, or setup is done |
| `house-style` | `token_set` is stored |
| `nextcloud-theming` | Nextcloud's theming was applied from the stored set (`CoreThemingState`), or the administrator chose to keep Nextcloud's own in this run |
| `login-page` | any of `hide_slogan`, `login_watermark`, `brand_stripe_placement` is stored |
| `apps-menu` | any `menu.<key>` default is stored |
| `finish` | `setup.completed` is stored |

## The contract

All three routes are administrator only: no `#[NoAdminRequired]`, so Nextcloud's middleware refuses
anyone else with 403 before the controller runs, and CSRF is required on the two POSTs. nextcloud-vue
already treats a 403 on status as "set up" for the caller.

- `GET /apps/thematiq/api/setup/status` answers `{version, completed, skipped, configured, inProgress,
  steps: {<id>: {done, detail}}}`. `detail` of `house-style` names the stored set.
- `POST /apps/thematiq/api/setup/config` takes `{hide_slogan, login_watermark, brand_stripe_placement,
  header_style, menu: {...}}`. Login keys and `header_style` go through `LayoutOptionValues`; `menu`
  goes through `AppMenuSettings::saveDefaults()`, the same check as `POST /app-menu/defaults`. An unknown key or a
  wrong value refuses the whole patch with 400 naming it, and nothing is stored.
- `POST /apps/thematiq/api/setup/action/{actionId}`:
  - `apply-house-style` `{tokenSet}`: `ActiveTokenSetService::switchTo()`, so the licence gate, the audit
    entry and the saved version are those of the settings page. An unconfirmed restricted set answers
    403 `restricted_set_not_confirmed`, as the settings page does. `nextcloud` keeps Nextcloud's look.
  - `apply-nextcloud-theming` `{logo, colours, favicon}`: the parts of the set's theming metadata to
    apply, through the same service as `occ thematiq:theme:set --sync-core` and the theming sync
    dialog. A part the set does not bring is reported as skipped. All false records the choice to keep
    Nextcloud's own and changes nothing.
  - `skip`: stores `setup.skipped` (who, when) and changes nothing else. What earlier steps of the run
    saved stays saved (Ruben, 27 November 2026).
  - `complete`: stores `setup.completed` (who, when).
  - any other id answers 404.

  Every write is in thematiq's audit trail with the actor, as the same write from the settings page is.

Under a configuration source the instance counts as configured, so the wizard never opens on its own,
and every write would be refused with 423 by `ConfigSourceLockMiddleware` anyway. The version card's
Set up again is disabled there, with the note the settings page already shows about who manages the
house style.

## The steps

1. **Welcome** (`info`): what thematiq does in three short lines, and the three ways out: Start,
   Later, Skip, keep Nextcloud as it is.
2. **House style** (`component`): the token sets as the settings page lists them, searchable, with
   Nextcloud (keep Nextcloud's look) first. A restricted set shows the warning, holder, source package
   and version, the full licence text and the statement "We are this organisation, or we have its
   permission to use this house style." with a checkbox. Next stays disabled until the box is ticked;
   Next then posts the confirmation (`POST /settings/tokensets/{id}/licence/confirmation`) and then runs
   `apply-house-style`. A confirmed set applies at once.
3. **Nextcloud's own theming** (`component`): what applying the set to Nextcloud would change, from
   the theming plan the sync dialog shows: logo, primary and background colour, favicon, each with
   its current and new value and a checkbox, all ticked when the set brings it. Next runs
   `apply-nextcloud-theming` with the ticked parts. "Keep Nextcloud's own" unticks all. Skipped when the
   house style is Nextcloud.
4. **Login page** (`config-fields`): hide the Nextcloud slogan, the login watermark, and where the brand
   stripe shows, with a preview of the login card. Writes through `POST /api/setup/config`.
5. **Apps menu** (`config-fields`): a switch "Use the workplace apps menu" (`menu.style` workplace or
   classic) and, when on, the defaults columns, rows above More, the Create section and external apps,
   with the grid preview of Menu settings. Writes the administrator's `menu.<key>` defaults. The
   workplace apps menu only shows in the workplace top bar. When the administrator turns the menu on
   and the chosen house style does not draw that bar, the step says the workplace top bar comes with
   it, and Next also stores `header_style` workplace (Ruben, 27 November 2026). With the menu off,
   `header_style` is left as it is.
6. **Finish** (`summary`): what was set, and two buttons: "Finish and show me the apps menu" (only when
   the workplace menu is on) and "Finish". Both run `complete` and reload the page so the new house
   style draws. The first also stores the apps menu tour's progress at its first step for this
   administrator, so on the reloaded page the next open of the menu starts the tour (change
   apps-menu-walkthrough, REQ-TQWT-002).

Later is the dialog's close button and a Later button in the footer of every step; Skip is on the
welcome step and in the footer of every step after it. Skip on a later step keeps what earlier steps
saved; it only ends the run. The walkthrough never starts while the wizard is
open (REQ-TQWT-002).

## Set up again

The Theming settings page gets a version card, drawn as nextcloud-vue's `CnVersionInfoCard` draws it on
the other apps' admin pages: the installed version, the setup state ("Set up on 27 November 2026 by
ruben", "Skipped", "Not set up") and a Set up again button. Set up again opens the same wizard, filled
with the stored values, starting at Welcome. It does not reset anything: a run that is closed with Later
leaves the instance as it was, because `setup.completed` or `setup.skipped` is still there. Finishing a
rerun stores `setup.completed` again with the new actor and time. On an instance that never finished,
Set up again simply opens the pending wizard.

## Edge cases

- **Non-admins**: never see the wizard, never load its script or state; the setup routes answer 403.
- **Delegated theming administrators** (`IDelegatedSettings`) and subadmins: treated as non-admins
  for the wizard; they keep their delegated settings pages. Only the `admin` group gets the wizard
  (Ruben, 27 November 2026).
- **Upgrade from a configured earlier version**: configured, so set up; nothing shows, nothing is
  migrated. The version card reads "Set up" without a date, because no run recorded one.
- **Upgrade from an earlier version that was never configured**: not configured, so the wizard opens
  on the administrator's next page (Ruben, 27 November 2026).
- **Restricted sets**: the wizard applies a set only through the same gate. A confirmation withdrawn
  by another administrator between the tick and Next gives the 403 in the step, which stays open
  and asks again.
- **A step fails**: the step shows the error and stays; nothing after it runs. Steps already saved
  stay saved, and the next page opens the wizard at the failed step.
- **Two administrators at once**: both may see the wizard. Each write is validated on its own; the
  last write wins, as on the settings page. Once one finishes or skips, the other's wizard does not
  return after their next page load.
- **Nextcloud's own first-run wizard** (`firstrunwizard`) is open: thematiq's wizard waits until it is
  closed, so two modals never stack.
- **Theming disabled for the current app** (per-app theming exclusion): the wizard still opens; it
  is about the whole instance.
- **Narrow screens**: the wizard is a full-screen dialog on a phone.
- **Accessibility**: `CnSetupWizard`'s dialog semantics, focus trap and step heading; Later and Skip
  are buttons with their full text; the licence statement is a labelled checkbox.

## Left to the boards

The wording of the version card's setup states and the place of the apps menu tour entry in the user
menu (change apps-menu-walkthrough) follow the boards (Ruben, 27 November 2026). The states above are a
draft.

## Copy (draft until the boards land)

| place | English | Dutch |
|---|---|---|
| welcome title | Welcome to thematiq | Welkom bij thematiq |
| welcome body | thematiq gives all of Nextcloud your organisation's house style: colours, logo, fonts, the login page and the apps menu. Set it up in a few steps, or keep Nextcloud as it is. | thematiq geeft heel Nextcloud de huisstijl van je organisatie: kleuren, logo, lettertypen, de inlogpagina en het appmenu. Stel het in een paar stappen in, of laat Nextcloud zoals het is. |
| later | Later | Later |
| skip | Skip, keep Nextcloud as it is | Overslaan, Nextcloud blijft zoals het is |
| house style title | Choose your house style | Kies je huisstijl |
| Nextcloud theming title | Use it in Nextcloud's own theming | Gebruik hem in de eigen vormgeving van Nextcloud |
| login title | The login page | De inlogpagina |
| apps menu title | The apps menu | Het appmenu |
| finish title | Your house style is set | Je huisstijl staat klaar |
| finish with tour | Finish and show me the apps menu | Afronden en laat me het appmenu zien |
| version card button | Set up again | Opnieuw instellen |
