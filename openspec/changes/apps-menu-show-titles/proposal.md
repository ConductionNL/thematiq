---
kind: code
depends_on: []
---

# The apps menu and the top bar choose between icons and titles

## Why

Ruben's review of thematiq on a reference instance (27 November 2026, item 6): an organisation whose
people know their apps by icon wants a calmer, smaller apps menu, with icons and no names under or
beside them. Today every entry always shows its name. Ruben decided this is a Theming choice of the
menu, "Show titles", on by default.

Ruben's answers on the first draft (27 November 2026) add the top bar: the pinned apps get the same
name tooltip, and titles in the top bar become their own setting, "Show titles in the top bar", off by
default. It replaces the admin page's older option "Show text labels in app menu (hide icons)".

## What changes

- **A new menu setting `titles`, shipped on.** It sits with the other Theming keys: `shape`, `tiles`,
  `payoff`, `iconSize` and `iconColour`. On is today's menu.
- **Off draws icons only, in every section.** The Nextcloud apps, the Create row and the external apps
  lose their visible name. The Create row's More keeps its text.
- **The name stays available.** It is the entry's accessible name (`aria-label`), and it shows as a
  tooltip on hover and on keyboard focus. The tooltip can be dismissed with Escape, stays while the
  pointer is on it, and does not hide the focus ring.
- **Payoff follows titles.** With titles off the payoff is not drawn, and the Show payoff switch is
  disabled with a note saying why. Its stored value is kept, so switching titles back on brings the
  payoff back as it was.
- **Set in Menu settings, Theming tab**, between "Show items as tiles" and "Show payoff". Like every
  Theming choice it is the administrator's, set with For everyone; a person cannot override it. The
  preview and its sentence follow the switch.
- **A second setting, `topBarTitles`, shipped off: Show titles in the top bar.** On, each pinned app in
  the workplace top bar shows its name next to its icon, and needs no tooltip. Off, the pins stay
  icon-only and get the same name tooltip on hover and focus as the menu's entries. On a page with
  Nextcloud's own apps menu it does what the older option did: the names in Nextcloud's menu.
- **The older option moves into it.** "Show text labels in app menu (hide icons)" goes from the admin
  page; "Show titles in the top bar" takes its place in the Menu card. A repair step moves the stored
  value once, so an instance with text labels keeps them. Its endpoint `/settings/menulabels` goes; the
  capability field and the bundle key `showMenuLabels` keep their names and carry the new value.
- **Both are the administrator's.** Like every Theming choice, neither is a personal key.
- **The setup wizard's apps menu step gets Show titles too**, because that step already offers Show payoff, a
  Theming choice: "Show titles" comes before "Show payoff" and is stored as the administrator's default.

## Not in this change

- `titles` on thematiq's admin page. Its Menu card holds no Theming switches of the workplace menu
  today; it only gets `topBarTitles`, in place of the older option.
- The configuration bundle for the menu settings. No `menu.<key>` value is in the bundle today; that is
  a gap of its own, outside this change.
- A personal choice. `titles` and `topBarTitles` are Theming keys, and Theming keys are the administrator's
  (apps-menu-settings). Making it personal would be a separate decision.
- The classic menu style. It leaves Nextcloud's menu alone, so `titles` does nothing there.
- Headings and fold toggles. "Apps", "Create", the external apps heading, More and Fewer keep their
  text: they are not app entries.

## Boards

Merged in ConductionNL/design-system#1220: the Theming tab with the new switch (werkplek/AppMenuVormgeving)
and the menu with titles off (werkplek/AppMenuZonderTitels). thematiq/TqLayout still draws the older
option and needs "Show titles in the top bar" in its place (board follow-up). Boards are
canon. See `screens.md`.

## Impact

- `lib/Service/AppMenuSettings.php`: `titles` (true) and `topBarTitles` (false) in `DEFAULTS` and
  `SWITCHES`, not in `PERSONAL`; `topBarTitles` reads `show_menu_labels` while it is absent
- `lib/Repair/MigrateMenuLabelsToTopBarTitles.php`: new, moves `show_menu_labels` once
- `lib/Service/CssInjectionService.php`: `show-menu-labels` only with `topBarTitles` on and Nextcloud's
  own menu on the page
- `lib/Controller/SettingsController.php`, `appinfo/routes.php`, `js/admin.js`: the menulabels endpoint
  and its handler go
- `lib/Capabilities.php`, `lib/Service/ConfigBundleService.php`, `lib/Service/ThemeVersionRestoreService.php`,
  `lib/Settings/Admin.php`, `templates/settings/admin.php`, `js/admin-layout-panel.js`: `showMenuLabels`
  reads and writes `menu.topBarTitles`; the Menu card switch
- `lib/Service/SetupRequest.php`: `MENU_KEYS` names `titles`
- `lib/Service/AppMenuDialogLabels.php`: the switch, its help, the payoff note and the preview words
- `js/app-menu.js`: icon-only entries, the tooltip, payoff not drawn with titles off; pins with their
  name or with the tooltip
- `css/app-menu.css`: the icon-only entry, the tooltip and the focus ring
- `js/dialogs/menu-settings-dialog.js`: the switch, payoff disabled, preview and sentence
- the setup wizard's apps menu step (first-time-setup, bundled as `vendor/setupWizard.js`, its step
  not built yet): the switch, built with the step
- tests: PHPUnit `AppMenuSettingsTest`, `AppMenuSettingsControllerTest`, `SetupRequestTest`,
  `CssInjectionServiceTest`, `CapabilitiesTest`, `ConfigBundleServiceTest`, new
  `MigrateMenuLabelsToTopBarTitlesTest`; vitest `appMenuSettings.spec.js`, `appMenuPins.spec.js`,
  `menuSettingsDialog.spec.js`, `setupWizard.spec.js`, new `appMenuTitles.spec.js` and
  `adminLayoutTopBarTitles.spec.js`; e2e `tests/e2e/spec-coverage/apps-menu-show-titles.spec.ts`
- l10n: the new strings in English and Dutch
