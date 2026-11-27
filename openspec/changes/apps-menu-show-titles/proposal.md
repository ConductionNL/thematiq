---
kind: code
depends_on: []
---

# The apps menu can show icons only

## Why

Ruben's review of thematiq on a reference instance (27 November 2026, item 6): an organisation whose
people know their apps by icon wants a calmer, smaller apps menu, with icons and no names under or
beside them. Today every entry always shows its name. Ruben decided this is a Theming choice of the
menu, "Show titles", on by default.

## What changes

- **A new menu setting `titles`, shipped on.** It sits with the other Theming keys: `shape`, `tiles`,
  `payoff`, `iconSize` and `iconColour`. On is today's menu.
- **Off draws icons only, in every section.** The Nextcloud apps, the Create row and the external apps
  lose their visible name. The pins in the top bar are icon-only already and do not change.
- **The name stays available.** It is the entry's accessible name (`aria-label`), and it shows as a
  tooltip on hover and on keyboard focus. The tooltip can be dismissed with Escape, stays while the
  pointer is on it, and does not hide the focus ring.
- **Payoff follows titles.** With titles off the payoff is not drawn, and the Show payoff switch is
  disabled with a note saying why. Its stored value is kept, so switching titles back on brings the
  payoff back as it was.
- **Set in Menu settings, Theming tab**, between "Show items as tiles" and "Show payoff". Like every
  Theming choice it is the administrator's, set with For everyone; a person cannot override it. The
  preview and its sentence follow the switch.
- **The setup wizard's apps menu step gets it too**, because that step already offers Show payoff, a
  Theming choice: "Show titles" comes before "Show payoff" and is stored as the administrator's default.

## Not in this change

- thematiq's admin page. Its Menu card holds no Theming switches of the workplace menu today, so it
  gets none. The older option there, "Show text labels in app menu (hide icons)", belongs to
  Nextcloud's own menu (menu-labels) and is left as it is.
- A personal choice. `titles` is a Theming key, and Theming keys are the administrator's
  (apps-menu-settings). Making it personal would be a separate decision.
- The classic menu style. It leaves Nextcloud's menu alone, so `titles` does nothing there.
- Headings and fold toggles. "Apps", "Create", the external apps heading, More and Fewer keep their
  text: they are not app entries.

## Boards

Being drawn on design-system branch `design/thematiq-show-titles`: the Theming tab with the new switch
(werkplek/AppMenuVormgeving) and the menu with titles off (werkplek/AppMenuZonderTitels). Boards are
canon. See `screens.md`.

## Impact

- `lib/Service/AppMenuSettings.php`: `titles` in `DEFAULTS` (true) and `SWITCHES`; not in `PERSONAL`
- `lib/Service/SetupRequest.php`: `MENU_KEYS` names `titles`
- `lib/Service/AppMenuDialogLabels.php`: the switch, its help, the payoff note and the preview words
- `js/app-menu.js`: icon-only entries, the tooltip, payoff not drawn with titles off
- `css/app-menu.css`: the icon-only entry, the tooltip and the focus ring
- `js/dialogs/menu-settings-dialog.js`: the switch, payoff disabled, preview and sentence
- the setup wizard's apps menu step (first-time-setup, bundled as `vendor/setupWizard.js`, its step
  not built yet): the switch, built with the step
- tests: PHPUnit `AppMenuSettingsTest`, `AppMenuSettingsControllerTest`, `SetupRequestTest`; vitest
  `appMenuSettings.spec.js`, `menuSettingsDialog.spec.js`, `setupWizard.spec.js`, new
  `appMenuTitles.spec.js`; e2e `tests/e2e/spec-coverage/apps-menu-show-titles.spec.ts`
- l10n: the new strings in English and Dutch
