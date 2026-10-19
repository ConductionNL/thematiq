# Proposal: button-tokens-not-forced

## Why

The nldesign layer forced every button's weight, corner and border width with `!important` (theme.css, the primary
and secondary rules, and a second corner rule in element-overrides.css). Any component that draws its own button had to
counter-fix it, by handing values to the tokens or by `!important` of its own; nextcloud-vue's board look did both, and
the theme then grew `:not(.cn-look-board *)` exclusions to step aside. Ruben's decision: express these as tokens, not
forced values.

## What changes

- An NcButton gets the button tokens through the Nextcloud variables it reads, `--font-weight-element` and
  `--border-radius-element`, in a zero-specificity rule, so the per-variant scopes in component-scopes.css (a primary or
  secondary button's own token) still decide for their variant.
- Core's plain buttons get the same tokens from a rule with core's own specificity, later in the cascade: it wins over
  core's `font-weight: bold` and loses to any component rule more specific than core's.
- The forced corner on primary and secondary buttons, and the duplicate forced corner rules in theme.css and
  element-overrides.css, go; the corner comes from the token through `--border-radius-element`.

Not in this change: the primary button's forced colours and weight, and the forced button padding, which stay as they
are for now.

## Reach

Only the defaults layer sets the three button tokens (700, the set radius, 1px), so all 48 sets resolve the same values
as before. Measured under Zuiddrecht with the change served in place of theme.css and element-overrides.css: Files
("Nieuw", "Alle bestanden" 700, 8px, 1px; sort buttons 600), Personal settings ("Toevoegen" 700, 8px) and dossiq's
queue are unchanged. What can change is an app that styles its own buttons: its own weight or corner now shows instead
of being overridden.
