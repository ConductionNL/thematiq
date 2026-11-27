# Tasks: apps-menu-show-titles

Nothing here is built until this change is merged; its boards are merged in
ConductionNL/design-system#1220 and #1221; TqSetupAppmenu has a follow-up. Copy, entry size and tooltip placement follow the boards.

## 1. Server

- [x] 1.1 `lib/Service/AppMenuSettings.php`: `titles` in `DEFAULTS` (true) and `SWITCHES`, not in
      `PERSONAL`. REQ-TQMT-001.
- [x] 1.2 `lib/Service/SetupRequest.php`: `MENU_KEYS` names `titles`. REQ-TQMT-004.
- [x] 1.3 `lib/Service/AppMenuDialogLabels.php`: `titles`, `titlesHelp`, `payoffNeedsTitles`,
      `withoutTitles` as in `design.md`; the menu's own labels get what the tooltip needs. REQ-TQMT-004.
- [x] 1.4 `lib/Service/AppMenuSettings.php`: `topBarTitles` in `DEFAULTS` (false) and `SWITCHES`, not
      in `PERSONAL`; read `show_menu_labels` while `menu.topBarTitles` is absent. REQ-TQMT-005.
- [x] 1.5 `lib/Repair/MigrateMenuLabelsToTopBarTitles.php`: new, registered in `appinfo/info.xml` after
      `MigrateAppConfigKeys`; moves `show_menu_labels` once and removes it. menu-labels.
- [x] 1.6 `lib/Service/CssInjectionService.php`: `show-menu-labels` only with `topBarTitles` on and no
      workplace apps menu on the page. menu-labels.
- [x] 1.7 Remove `POST /settings/menulabels` (`appinfo/routes.php`, `SettingsController`) and its
      handler in `js/admin.js`. menu-labels.
- [x] 1.8 `lib/Capabilities.php`, `lib/Service/ConfigBundleService.php` (export and import),
      `lib/Service/ThemeVersionRestoreService.php`: `showMenuLabels` reads and writes
      `menu.topBarTitles`. REQ-TQMT-005, theming-capability.
- [x] 1.9 `lib/Settings/Admin.php`, `templates/settings/admin.php`, `js/admin-layout-panel.js`: "Show
      titles in the top bar" in the Menu card in place of the older checkbox, saved through the menu
      defaults. admin-settings.
- [x] 1.10 PHPUnit `AppMenuSettingsTest` (both shipped values, the administrator's value, switches
      only, the `show_menu_labels` fallback, reset for everyone), `AppMenuSettingsControllerTest` (a
      person's `titles` and `topBarTitles` refused with 400), `SetupRequestTest` (the wizard sends
      `titles`), new `MigrateMenuLabelsToTopBarTitlesTest`, `CssInjectionServiceTest`,
      `CapabilitiesTest`, `ConfigBundleServiceTest`, `SettingsControllerEndpointsTest` (the old
      endpoint gone).

## 2. The menu

- [x] 2.1 `js/app-menu.js`: with `titles` off, `data-titles="false"` on the menu; Nextcloud apps,
      create chips and external apps drawn as the icon with the name as `aria-label` (an external app's
      ending in "(opens in a new tab)"); no payoff; headings, More and Fewer keep their text; pins
      untouched. REQ-TQMT-002.
- [x] 2.2 `js/app-menu.js`: `nameOf()` reads the `aria-label` when no label text is drawn, so drag,
      Alt with an arrow key and Alt+P still name the app. REQ-TQMT-002.
- [x] 2.3 `js/app-menu.js`: one `role="tooltip"` per menu, not referenced by `aria-describedby`; shown
      on hover after 300 ms and at once on `:focus-visible`; kept while the pointer is on the entry or
      the tooltip or the entry has focus; hidden on leave, drag start and close; Escape hides it before
      the menu closes, after a running walkthrough. REQ-TQMT-003.
- [x] 2.4 `css/app-menu.css`: the icon-only entry as AppMenuZonderTitels draws it, the tooltip in
      Nextcloud tokens (light and dark, contrast at least 4.5:1), placed off the focus ring, and the
      focus ring kept. REQ-TQMT-003.
- [x] 2.5 `js/app-menu.js`: pins draw the name next to the icon with `topBarTitles` on (ellipsis, the
      tooltip only when cut off), and the shared tooltip under the pin with it off. REQ-TQMT-003,
      REQ-TQMT-006.
- [x] 2.6 vitest new `appMenuTitles.spec.js` (icon only per section, names and the new tab note,
      Alt+P announces, tooltip on focus and hover, hoverable, Escape order); `appMenuSettings.spec.js`
      (titles on draws as before); `appMenuPins.spec.js` (pins with names, pins with the tooltip, drag and
      Alt+P unchanged); new `adminLayoutTopBarTitles.spec.js`.

      (2.x built in cloud/b-thematiq-apps-menu-show-titles-menu. Nextcloud's own tiles are Vue's
      nodes, so their label is not removed from the DOM: `data-titles="false"` takes it out of the
      layout and the accessibility tree with `display: none`, which a screen reader does not read either.
      The tooltip follows board AppMenuZonderTitels: dark ground (`--color-main-text`) with
      `--color-main-background` text, under the entry. It sits on the body, fixed, so pins share it.)

## 3. Menu settings and the setup wizard

- [x] 3.1 `js/dialogs/menu-settings-dialog.js`: "Show titles" after "Show items as tiles", "Show
      titles in the top bar" directly under it, then "Show payoff"; `topBarTitles` saved with For
      everyone (REQ-TQMT-005); Show payoff disabled with "Only with Show titles." and its value kept; preview without
      names and payoff; sentence "without titles". REQ-TQMT-004.
- [ ] 3.2 The setup wizard's apps menu step (first-time-setup, REQ-TQFS-010): "Show titles" before
      "Show payoff", payoff disabled while titles is off, Apply stores `menu.titles`. Built with the
      step. REQ-TQMT-004.
- [ ] 3.3 vitest `menuSettingsDialog.spec.js` (switch places, payoff disabled and kept, preview,
      sentence, top bar titles saved for everyone), `setupWizard.spec.js` (the step offers Show titles before Show payoff).

## 4. Copy

- [x] 4.1 l10n: the new strings in English and Dutch; `npm run test:l10n` green.

## 5. End to end

- [ ] 5.1 `tests/e2e/spec-coverage/apps-menu-show-titles.spec.ts` with `@e2e` tags for "The
      administrator switches titles off", "Keyboard focus shows the name", "Escape dismisses the
      tooltip and keeps the menu open", "The administrator switches titles off in Menu settings", "A
      pin shows its name on focus", "Pins show their names" and menu-labels "Checkbox change triggers
      save":
      store `{titles: false}` through `POST /apps/thematiq/app-menu/defaults`, open Files and the apps
      menu, check the accessible names, the missing text, the tooltip on focus, its Escape and the
      focus ring; then the Theming tab with payoff disabled; then `topBarTitles` on and off with two
      pins; put every value back in a `finally`. (written: `tests/e2e/spec-coverage/apps-menu-show-titles.spec.ts`, runs in nightly)

## 6. Verification

- [ ] 6.1 `hydra/scripts/diff-check.sh` green on the changed files while building; `composer
      check:strict` and `npm run lint` once before push.
- [ ] 6.2 Live check on the dev instance, light and dark, a Zuiddrecht set and a plain one: titles off
      matches AppMenuZonderTitels in every section; keyboard through the whole menu shows a tooltip per
      entry and a focus ring; Escape hides the tooltip, then closes the menu; a screen reader reads each
      name once; 200% zoom keeps the tooltip inside the viewport; titles on is unchanged. Titles in the top bar on
      and off with pins, on the workplace menu and on the classic style (Nextcloud 32 header and 33
      waffle); an instance upgraded with `show_menu_labels` on keeps its labels.

## Notes from the build

- `lib/Service/AppMenuTopBarTitles.php` reads `menu.topBarTitles` with the older `show_menu_labels`
  as fallback, writes it, and says whether a page draws the workplace apps menu; the capability,
  the bundle (export and import) and the admin page read it. `ThemeVersionRestoreService` needs no
  change: it compares and restores bundles, whose `showMenuLabels` now carries the new key.
- A save of `topBarTitles` through the menu defaults, and a reset for everyone, remove the older
  `show_menu_labels`, so it never stands in again after the administrator chose.
- The Newman request and the e2e specs that called `POST /settings/menulabels` now save through
  `POST /apps/thematiq/app-menu/defaults` (`menu-labels.spec.ts`, the workflow helpers).
- 3.1 is built (Theming tab order, Show payoff disabled with its note and its value kept, the preview
  without names, the sentence's "without titles"); 3.3's menuSettingsDialog.spec.js part is written,
  its setupWizard.spec.js part waits for 3.2.
- `npm run test:l10n` stays red on development for js/admin.js strings that are not this change's.
