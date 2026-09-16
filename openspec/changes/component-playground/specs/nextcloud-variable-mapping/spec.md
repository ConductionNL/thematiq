# Spec delta: Nextcloud Variable Mapping (component-playground)

The mapping itself is unchanged: `overrides.css` still says which `--color-*` variable reads
which `--nldesign-*` token, and the load order is untouched. What is added is a reader of that
mapping in the other direction — for a given token, which Nextcloud variable holds its stock
value — so the `nextcloud` token set can be resolved from the running instance instead of from
a hand-copied file.

## ADDED Requirements

### Requirement: The Stock Set Is Resolved From The Instance
The `nextcloud` token set exists to reproduce the appearance of the running instance, and that
appearance changes with every Nextcloud release. Its `--nldesign-*` values MUST therefore be
resolved from the instance's own stock theme rather than read from a shipped snapshot, and
`css/tokens/nextcloud.css` MUST be kept as the fallback for when they cannot be.

The values MUST come from the same computation the server serves: the theming app's
`DefaultTheme::getCSSVariables()`, which is what produces `/apps/theming/theme/default.css`.
This is the instance's stock theme rather than Nextcloud's factory one — an admin who has set
a primary colour in core theming is wearing that colour, so that colour is what is reported.

Resolution MUST produce literal values only. A `--nldesign-*` token MUST NOT be emitted as a
`var()` reference to a Nextcloud variable: `overrides.css` already declares the opposite
direction, so the reference would close a loop and CSS discards a custom property that depends
on itself.

The inversion of the mapping is many-to-one, and the choice of source MUST be deterministic:
where several variables read one token, the variable carrying the token's own name defines it,
and where none does, the candidates are taken in sorted order so the result does not depend on
the order declarations appear in `overrides.css`.

Every failure path MUST degrade to the shipped file. A stale stock theme is a cosmetic defect;
no stock theme at all is a blank page.

#### Scenario: The set follows the installed version
- GIVEN an instance running a Nextcloud version whose stock values differ from `css/tokens/nextcloud.css`
- WHEN the `nextcloud` token set is applied
- THEN the `--nldesign-*` token values MUST be the ones the instance's own theme computes
- AND the shipped file MUST NOT be loaded for that set

#### Scenario: The set follows core theming
- GIVEN an admin who has set a primary colour in Nextcloud's own theming settings
- WHEN the `nextcloud` token set is applied
- THEN the resolved tokens MUST carry that colour, because it is the colour the instance wears

#### Scenario: A token read by several variables takes the defining one
- GIVEN `--color-primary`, `--color-primary-element`, `--color-primary-light-text` and
  `--color-primary-element-light-text` all read `--nldesign-color-primary`
- WHEN the stock set is resolved
- THEN `--nldesign-color-primary` MUST take the value of `--color-primary`
- AND MUST NOT take a text colour from one of the other three

#### Scenario: A value that cannot be frozen is dropped
- GIVEN a stock variable whose value is a gradient, a `color-mix()` or an unresolvable `var()` chain
- WHEN the stock set is resolved
- THEN no `--nldesign-*` token MUST be emitted for it, rather than one carrying a reference

#### Scenario: The theming app is absent or fails
- GIVEN an instance where the theming app is disabled, or where reading the theme throws
- WHEN the `nextcloud` token set is applied
- THEN `css/tokens/nextcloud.css` MUST be loaded instead
- AND the failure MUST be recorded in the log, because the fallback is otherwise silent
