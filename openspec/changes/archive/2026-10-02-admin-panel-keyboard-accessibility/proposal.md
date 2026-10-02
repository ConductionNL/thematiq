---
kind: code
---

## Why

`js/admin.js` (1391 lines, the entire client-side surface of the admin settings page — nldesign
ships no Vue frontend) builds two hand-rolled interactive widgets that bypass native semantics
and have no keyboard-close path, in violation of WCAG 2.1 AA (2.1.1 Keyboard, 2.1.2 No Keyboard
Trap, 4.1.2 Name/Role/Value):

1. **Custom app-theming combobox** (`renderAppThemingList()`, `js/admin.js:1063-1144`). A
   `<button class="nldesign-app-dropdown-trigger">` opens a filterable checkbox panel on `click`
   (`js/admin.js:1129-1133`) and the panel is closed only by a document-level `click` listener
   (`js/admin.js:1134-1136`). There is no `keydown` handler anywhere in the file (confirmed:
   `grep -n "keydown\|Escape\|keyCode" js/admin.js` matches nothing except an unrelated code
   comment). Consequences: the trigger carries no `aria-expanded`/`aria-haspopup`, the panel
   carries no `role="listbox"`/`role="dialog"`, and a keyboard-only user who tabs to the trigger
   and presses Enter to open it has **no way to close the panel without a mouse click** —
   Escape does nothing, and Tab merely cycles through the (potentially long, unfiltered) list of
   every installed app's checkbox.
2. **Custom confirmation dialogs** — the theming-sync dialog (`showThemingSyncDialog()`,
   `js/admin.js:283-373`, built via `insertAdjacentHTML` into `#nldesign-theming-dialog-overlay`)
   and the apply-token-set dialog (~`js/admin.js:900-940`). Neither carries `role="dialog"` nor
   `aria-modal="true"`, neither moves focus into the dialog when it opens (focus stays wherever it
   was, e.g. the button that triggered it), and neither traps focus — Tab can cycle focus out to
   the page behind the overlay while it is "open". The only way to dismiss either dialog is a
   mouse click on `.nldesign-dialog-cancel` or the overlay backdrop (`js/admin.js:318-327`,
   `927-933`); Escape is not wired.

This is not covered by existing tests: `tests/e2e/spec-coverage/app-theming.spec.ts`'s
`checkboxes-are-accessible` scenario (`@e2e openspec/specs/per-app-theming/spec.md#checkboxes-are-accessible`)
only asserts the per-app `<label for=...>` association is present — it does not open the dropdown
via keyboard, does not assert `aria-expanded`, and never exercises the theming-sync/apply dialogs
at all. The fleet's ADR-004 hard rules address `NcSelect`/`NcModal` usage inside Vue apps; nldesign
has no Vue frontend, so nothing today enforces equivalent keyboard/ARIA semantics on its vanilla-JS
widgets.

## What Changes

- `renderAppThemingList()` (`js/admin.js:1063-1144`): give the trigger `aria-haspopup="listbox"`
  and `aria-expanded` (toggled with `.open`), give the panel `role="listbox"` (or
  `role="dialog"` if that reads better with the search input inside), and add a `keydown` handler
  on the dropdown that closes the panel and returns focus to the trigger on `Escape`.
- `showThemingSyncDialog()` (`js/admin.js:283-373`) and the apply-token-set dialog
  (`js/admin.js:~900-940`): add `role="dialog"` and `aria-modal="true"` to each
  `.nldesign-dialog-overlay`/`.nldesign-dialog` pairing, move focus to the dialog (its heading or
  first focusable control) when it opens, trap Tab/Shift+Tab within the dialog while open, restore
  focus to the triggering element on close, and add an `Escape`-closes-dialog `keydown` handler
  equivalent to the existing cancel-button behavior.
- Not BREAKING: presentation/interaction-only additions; no API, storage, or route changes.

## Impact

- `js/admin.js` — `renderAppThemingList()`, `showThemingSyncDialog()`, apply-token-set dialog
  builder function.
- `tests/e2e/spec-coverage/app-theming.spec.ts` — extend or add a scenario asserting
  keyboard-driven open/close and `aria-expanded` state.
- New/extended e2e coverage for the theming-sync and apply-token-set dialogs' Escape-to-close and
  focus-trap behavior (currently no e2e spec targets either dialog's accessibility at all).
