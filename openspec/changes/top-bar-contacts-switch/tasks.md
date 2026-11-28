# Tasks: top-bar-contacts-switch

Nothing here is built until this change, quick-actions-toggles (whose switches and admin Menu card this
extends) and the boards are merged. Copy and placement follow the boards.

## 1. Server

- [x] 1.1 `lib/Service/AppMenuSettings.php`: `showContactsMenu` in `DEFAULTS` (true), `SWITCHES` and
      `PERSONAL`. REQ-TQCM-001.
- [x] 1.2 `lib/Service/AppMenuService.php`: add `css/top-bar-no-contacts.css` when the effective value
      is off, before the classic style's early return, so both headers get it. REQ-TQCM-001.
- [x] 1.3 `css/top-bar-no-contacts.css`: new, with the SPDX header; `#contactsmenu` gets
      `display: none`; check the bar's spacing against AppMenu and DqKop. REQ-TQCM-001.
- [x] 1.4 `lib/Service/AppMenuDialogLabels.php`: the label, help and the shared note of `design.md`.
- [x] 1.5 `lib/Settings/Admin.php`: the administrator's value for the admin page. REQ-TQCM-002.
- [x] 1.6 PHPUnit `AppMenuSettingsTest` (default, a person's value wins, reset),
      `AppMenuSettingsControllerTest` (a person saves the key), `AppMenuServiceTest` (the stylesheet
      only when off, in both styles).

## 2. Scripts

- [x] 2.1 `js/dialogs/menu-settings-dialog.js`: the switch after the create switches, editable in Only
      for me, saved as a personal key. REQ-TQCM-001.
- [x] 2.2 `templates/settings/admin.php`, `js/admin-layout-panel.js`, `js/admin.js`: the switch in the
      Menu card, saved at once, live region, alert, enabled with classic, session list. REQ-TQCM-002.
- [x] 2.3 vitest: `menuSettingsDialog.spec.js`, `adminLayoutCreateSwitches.spec.js`.

## 3. Copy

- [x] 3.1 l10n: the new strings in English and Dutch; `npm run test:l10n` green.

## 4. End to end

- [x] 4.1 `tests/e2e/spec-coverage/top-bar-contacts-switch.spec.ts` with `@e2e` tags for "A person
      switches the contacts menu off", "On by default" and "The administrator switches the contacts
      menu off for everyone": set the keys through the settings and defaults endpoints, open Files,
      check that `#contactsmenu` is not visible and not reached with Tab from the bell, and that the
      Contacts app opens; put every value back in a `finally`.

## 5. Verification

- [x] 5.1 `hydra/scripts/diff-check.sh` green on the changed files while building; `composer
      check:strict` and `npm run lint` once before push.
- [x] 5.2 Live check on the dev instance (light and dark, a Zuiddrecht set and a plain one, the
      workplace and the classic style): with the switch off the bar ends bell, divider, person, with no
      gap; keyboard and screen reader pass over
      no contacts button; with it on the bar is unchanged.
      (28 Nov, sandbox NC 34.0.6: zuiddrecht and rijkshuisstijl (light layout + workplace header) x
      workplace/classic menu x light/dark x on/off, 16 states measured. Off: the bar ends bell, divider,
      person, and the person stays at the same x as with it on. Red found and fixed: the divider is drawn
      on the element right before `#user-menu`, which was the hidden contacts menu, so it disappeared.
      `css/top-bar-no-contacts.css` now puts it on the control before the contacts menu. Keyboard: Tab
      from the bell skips it (e2e). The `nextcloud` set and plain sets without the workplace header
      leave Nextcloud's header alone by design (the menu layer runs only with `header-workplace`).)
