# Tasks: apps-menu-show-titles

Nothing here is built until this change and its boards (design-system branch
`design/thematiq-show-titles`) are merged. Copy, entry size and tooltip placement follow the boards.

## 1. Server

- [ ] 1.1 `lib/Service/AppMenuSettings.php`: `titles` in `DEFAULTS` (true) and `SWITCHES`, not in
      `PERSONAL`. REQ-TQMT-001.
- [ ] 1.2 `lib/Service/SetupRequest.php`: `MENU_KEYS` names `titles`. REQ-TQMT-004.
- [ ] 1.3 `lib/Service/AppMenuDialogLabels.php`: `titles`, `titlesHelp`, `payoffNeedsTitles`,
      `withoutTitles` as in `design.md`; the menu's own labels get what the tooltip needs. REQ-TQMT-004.
- [ ] 1.4 PHPUnit `AppMenuSettingsTest` (shipped on, the administrator's value, a switch only, reset
      for everyone), `AppMenuSettingsControllerTest` (a person's `titles` refused with 400),
      `SetupRequestTest` (the wizard sends `titles`).

## 2. The menu

- [ ] 2.1 `js/app-menu.js`: with `titles` off, `data-titles="false"` on the menu; Nextcloud apps,
      create chips and external apps drawn as the icon with the name as `aria-label` (an external app's
      ending in "(opens in a new tab)"); no payoff; headings, More and Fewer keep their text; pins
      untouched. REQ-TQMT-002.
- [ ] 2.2 `js/app-menu.js`: `nameOf()` reads the `aria-label` when no label text is drawn, so drag,
      Alt with an arrow key and Alt+P still name the app. REQ-TQMT-002.
- [ ] 2.3 `js/app-menu.js`: one `role="tooltip"` per menu, not referenced by `aria-describedby`; shown
      on hover after 300 ms and at once on `:focus-visible`; kept while the pointer is on the entry or
      the tooltip or the entry has focus; hidden on leave, drag start and close; Escape hides it before
      the menu closes, after a running walkthrough. REQ-TQMT-003.
- [ ] 2.4 `css/app-menu.css`: the icon-only entry as AppMenuZonderTitels draws it, the tooltip in
      Nextcloud tokens (light and dark, contrast at least 4.5:1), placed off the focus ring, and the
      focus ring kept. REQ-TQMT-003.
- [ ] 2.5 vitest new `appMenuTitles.spec.js` (icon only per section, names and the new tab note,
      Alt+P announces, tooltip on focus and hover, hoverable, Escape order); `appMenuSettings.spec.js`
      (titles on draws as before).

## 3. Menu settings and the setup wizard

- [ ] 3.1 `js/dialogs/menu-settings-dialog.js`: "Show titles" between "Show items as tiles" and "Show
      payoff"; Show payoff disabled with "Only with Show titles." and its value kept; preview without
      names and payoff; sentence "without titles". REQ-TQMT-004.
- [ ] 3.2 The setup wizard's apps menu step (first-time-setup, REQ-TQFS-010): "Show titles" before
      "Show payoff", payoff disabled while titles is off, Apply stores `menu.titles`. Built with the
      step. REQ-TQMT-004.
- [ ] 3.3 vitest `menuSettingsDialog.spec.js` (switch place, payoff disabled and kept, preview,
      sentence), `setupWizard.spec.js` (the step offers Show titles before Show payoff).

## 4. Copy

- [ ] 4.1 l10n: the new strings in English and Dutch; `npm run test:l10n` green.

## 5. End to end

- [ ] 5.1 `tests/e2e/spec-coverage/apps-menu-show-titles.spec.ts` with `@e2e` tags for "The
      administrator switches titles off", "Keyboard focus shows the name", "Escape dismisses the
      tooltip and keeps the menu open" and "The administrator switches titles off in Menu settings":
      store `{titles: false}` through `POST /apps/thematiq/app-menu/defaults`, open Files and the apps
      menu, check the accessible names, the missing text, the tooltip on focus, its Escape and the
      focus ring; then the Theming tab with payoff disabled; put every value back in a `finally`.

## 6. Verification

- [ ] 6.1 `hydra/scripts/diff-check.sh` green on the changed files while building; `composer
      check:strict` and `npm run lint` once before push.
- [ ] 6.2 Live check on the dev instance, light and dark, a Zuiddrecht set and a plain one: titles off
      matches AppMenuZonderTitels in every section; keyboard through the whole menu shows a tooltip per
      entry and a focus ring; Escape hides the tooltip, then closes the menu; a screen reader reads each
      name once; 200% zoom keeps the tooltip inside the viewport; titles on is unchanged.
