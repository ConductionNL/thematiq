# Proposal: primary-buttons-not-forced

## Why

Follow-up to button-tokens-not-forced. The nldesign layer still forced the primary button's ground, border, label
colour, weight and corner with `!important`, forced the label's spans to the primary label colour at id-level
specificity, and forced every button's 8px 16px padding (with its overflow fixes) with `!important`. Apps and the board
look had to outrank them.

## What changes

- The primary button's ground, label, hover and border come from the set's tokens through the variables Nextcloud's
  buttons read, already declared per variant in component-scopes.css; the forced primary rule and its hover rule go.
  `input.primary` and `a.primary` join the primary scope, so core's primary links and inputs follow it too.
- The label's spans follow the button through `--nldesign-color-on-surface`, the opt-out the body-ink rule already
  reads, set on the primary button at zero specificity; Nextcloud's colour pickers stay excluded.
- The button padding and overflow rule keeps its selectors without `!important`.

Not changed: the disabled primary colours stay forced, a state the set paints on purpose; so does the login page's primary button (`#body-login`), which sits on core's own login sheet where no app draws.

## Reach

All 48 sets resolve the same values: the primary tokens already reached NcButton through component-scopes. Measured
under Zuiddrecht with the change served in place of theme.css and component-scopes.css: Files ("Nieuw"), Personal
settings › Security ("Wachtwoord wijzigen", "Genereer back-upcodes") and dossiq's queue keep the same ground, label,
weight, corner and padding. An app that styles its own primary button or button padding now shows its own.
