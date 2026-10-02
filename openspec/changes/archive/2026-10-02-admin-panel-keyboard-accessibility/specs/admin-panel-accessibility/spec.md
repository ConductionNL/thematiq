# Admin Panel Keyboard Accessibility — Delta

**Spec refs**: none pre-existing (new capability) — cross-cuts `per-app-theming`,
`token-set-apply-dialog`, `theming-sync-dialog`
**Standards**: WCAG 2.1 AA 2.1.1 (Keyboard), 2.1.2 (No Keyboard Trap), 4.1.2 (Name, Role, Value)

## ADDED Requirements

### Requirement: App-Theming Dropdown Exposes Combobox Semantics and Closes on Escape

The per-app theming dropdown built by `renderAppThemingList()` MUST expose its open/closed state
via `aria-expanded` on the trigger button and `aria-haspopup`, MUST expose the option panel via an
appropriate ARIA role (`listbox` or `dialog`), and MUST close on `Escape` with focus returned to
the trigger — a mouse click MUST NOT be the only way to close it.

#### Scenario: Keyboard user opens and closes the app-theming dropdown without a mouse

@e2e exclude covered in jsdom with real keyboard events against js/admin.js; proven by tests/vitest/admin-a11y.spec.js 'closes on Escape, sets aria-expanded=false, and returns focus to the trigger' and 'closes when Tab moves focus out of the dropdown, without pulling focus back'

- GIVEN the admin settings page at `/settings/admin/theming` with keyboard focus on the app-theming
  dropdown trigger button
- WHEN the user presses Enter or Space
- THEN the dropdown panel MUST open and the trigger's `aria-expanded` attribute MUST become `"true"`
- WHEN the user then presses `Escape`
- THEN the dropdown panel MUST close, the trigger's `aria-expanded` MUST become `"false"`, and
  keyboard focus MUST return to the trigger button

### Requirement: Confirmation Dialogs Are Modal with Focus Management

The theming-sync dialog (`showThemingSyncDialog()`) and the apply-token-set dialog MUST carry
`role="dialog"` and `aria-modal="true"`, MUST move keyboard focus into the dialog when it opens,
MUST trap `Tab`/`Shift+Tab` within the dialog's focusable elements while open, MUST close on
`Escape`, and MUST restore keyboard focus to the element that triggered the dialog when it closes
by any path (cancel, overlay click, confirm, or Escape).

#### Scenario: Theming-sync dialog traps focus and closes on Escape

@e2e exclude covered in jsdom with real keyboard events against js/admin.js; proven by tests/vitest/admin-a11y.spec.js 'traps Tab focus within the dialog (wraps from last back to first)' and 'closes on Escape and restores focus to the element that opened it'

- GIVEN the theming-sync dialog is open (triggered by selecting a token set that changes Nextcloud
  theming values)
- WHEN the user repeatedly presses `Tab`
- THEN keyboard focus MUST cycle only through the dialog's own focusable elements (never landing
  on background page content)
- WHEN the user presses `Escape`
- THEN the dialog MUST close without applying any theming change, and keyboard focus MUST return
  to the control that opened the dialog

#### Scenario: Apply-token-set dialog traps focus and closes on Escape

@e2e exclude covered in jsdom with real keyboard events against js/admin.js; proven by tests/vitest/admin-a11y.spec.js 'closes on Escape the way Cancel does: the old set comes back and focus returns'

- GIVEN the apply-token-set dialog is open (triggered by switching the active token set)
- WHEN the user presses `Escape`
- THEN the dialog MUST close via the same rollback path as clicking Cancel (token set selector and
  CSS custom properties revert to their pre-dialog values), and keyboard focus MUST return to the
  control that opened the dialog

### Requirement: Admin Panel Tablists Follow the Tab Pattern

Every `role="tablist"` on the admin settings page MUST contain elements with `role="tab"` and
`aria-selected`. Only the selected tab MUST be in the tab order, and the arrow, Home and End keys
MUST move the selection and focus between tabs.

#### Scenario: The preview switch is operated with the arrow keys

@e2e exclude covered in jsdom with real keyboard events against js/admin.js; proven by tests/vitest/admin-a11y.spec.js 'gives each view button the tab role, with only the selected one in the tab order' and 'moves to the next view with the right arrow and shows its stage'

- GIVEN the App/Login preview switch with keyboard focus on the App tab
- WHEN the user presses the right arrow key
- THEN the Login tab MUST be selected and focused, with `aria-selected="true"`
- AND the login preview MUST show and the app preview MUST hide
- AND only the Login tab MUST have tab index 0
