# Spec delta: token editor UI (authoring-token-value-types)

The token editor holds three more kinds of value: a colour with transparency, a separate dark
colour, and typed motion values.

## ADDED Requirements

### Requirement: Colour fields accept transparency

Every `color` row MUST offer an opacity control next to the colour picker. The text field MUST
accept `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`, `rgb()`, `rgba()`, `hsl()`, `hsla()`, a named colour
and `transparent`. Moving the picker MUST keep the current alpha. At full opacity the editor MUST
write `#rrggbb`, below it `#rrggbbaa`. The swatch MUST show the colour over a checkerboard.

#### Scenario: An administrator makes the focus colour half transparent
- GIVEN an administrator on Settings > Administration > Theming, in the token editor
- WHEN the administrator sets the opacity of "Primary light" to 50% and saves
- THEN `custom-overrides.css` MUST declare `--color-primary-light` with an 8-digit hex ending in `80`
- AND the page MUST show the translucent colour without a reload

#### Scenario: Moving the picker keeps the alpha
- GIVEN "Primary light" holds `#15427380`
- WHEN the administrator picks `#01689b` with the colour picker
- THEN the text field MUST read `#01689b80`

#### Scenario: A typed 8-digit hex updates both controls
- GIVEN the token editor is open
- WHEN the administrator types `#154273cc` into the text field of "Primary color"
- THEN the picker MUST show `#154273`
- AND the opacity control MUST show 80%

### Requirement: Each colour token has an optional dark value

Every `color` row MUST offer a "Dark" value with its own picker, opacity control and text field.
When the dark value is empty, the field MUST show the derived dark value as its placeholder, and
saving MUST store that derived value. The derived value MUST come from the same code that
generates the set's dark stylesheet. `GET /apps/thematiq/settings/overrides` MUST return the dark
values, and `POST` MUST accept them as `darkOverrides`.

#### Scenario: An administrator gives the primary colour its own dark value
- GIVEN an administrator in the token editor
- WHEN the administrator sets "Primary color" to `#154273` and its dark value to `#8fb8e6`, and saves
- THEN a user who chose the dark theme MUST see `#8fb8e6` as the primary colour
- AND a user on the light theme MUST see `#154273`

#### Scenario: An empty dark value is derived and shown
- GIVEN an administrator sets "Background hover" to `#f0f4f8` and leaves its dark value empty
- WHEN the row renders
- THEN the dark field's placeholder MUST show the value the dark palette derives for `#f0f4f8`
- AND saving MUST store that value as the dark override

### Requirement: Motion tokens are typed and reach every transition

The editor MUST list `--animation-quick` and `--animation-slow` as `duration` rows: a number
field and a unit select (`ms`, `s`). Saving one MUST write the Nextcloud name and the matching
thematiq name (`--nldesign-animation-quick`, `--nldesign-animation-slow`) with the same value. A new
editable token `--nldesign-animation-easing` MUST be an `easing` row: a select with `linear`,
`ease`, `ease-in`, `ease-out`, `ease-in-out` and "Custom curve", which opens four number fields for
`cubic-bezier()`. Every transition in `css/systems/nldesign/theme.css` that uses `ease` today MUST
read `--nldesign-animation-easing` instead. A preview MUST move a block with the chosen duration
and easing, and MUST stay still under `prefers-reduced-motion: reduce`.

#### Scenario: An administrator slows down quick animations
- GIVEN an administrator in the token editor, content area tab
- WHEN the administrator sets "Animation quick" to 150 ms and saves
- THEN `custom-overrides.css` MUST declare `--nldesign-animation-quick: 150ms !important`
- AND it MUST declare `--animation-quick: 150ms !important`
- AND the hover transition of a primary button MUST take 150 ms

#### Scenario: An administrator picks a custom easing curve
- GIVEN an administrator in the token editor
- WHEN the administrator chooses "Custom curve" and enters 0.2, 0, 0, 1, and saves
- THEN `--nldesign-animation-easing` MUST be `cubic-bezier(0.2, 0, 0, 1)`
- AND the hover transition of a primary button MUST use that curve

#### Scenario: Existing motion overrides survive the upgrade
@e2e exclude Upgrade step, covered by PHPUnit on the repair step
- GIVEN `custom-overrides.css` declares `--animation-quick: 200ms !important` before the upgrade
- WHEN the app is upgraded
- THEN the file MUST declare `--nldesign-animation-quick: 200ms !important` and `--animation-quick: 200ms !important`

#### Scenario: Reduced motion still wins
- GIVEN "Animation quick" is set to 400 ms
- AND a user whose system asks for reduced motion
- WHEN the user hovers a primary button
- THEN no transition MUST run, as the reduced motion rules in `css/systems/nldesign/theme.css` require

### Requirement: The server checks each value against its token type

`POST /apps/thematiq/settings/overrides` MUST check every value against its token's type:
`color`, `duration` (0 to 5000 ms, or 0 to 5 s), `easing` (a keyword, or `cubic-bezier()` with both
x values in 0..1) or `text`. A value that fails MUST be refused with 400 naming the token and the
type, and nothing MUST be written. A token name the registry does not list MUST also be refused
with 400.

#### Scenario: A duration without a unit is refused
@e2e exclude API validation branch, covered by PHPUnit on OverridesController and the Newman collection
- GIVEN an administrator
- WHEN they post `{"overrides": {"--nldesign-animation-quick": "150"}}`
- THEN the response MUST be 400 naming `--nldesign-animation-quick` and the type `duration`
- AND `custom-overrides.css` MUST be unchanged

#### Scenario: A malformed colour is refused
@e2e exclude API validation branch, covered by PHPUnit on OverridesController
- GIVEN an administrator
- WHEN they post `{"overrides": {"--color-primary": "#15427"}}`
- THEN the response MUST be 400 naming `--color-primary` and the type `color`

#### Scenario: An easing curve out of range is refused
@e2e exclude API validation branch, covered by PHPUnit on TokenValueValidator
- GIVEN an administrator
- WHEN they post `{"overrides": {"--nldesign-animation-easing": "cubic-bezier(1.5, 0, 0, 1)"}}`
- THEN the response MUST be 400, because x1 is outside 0..1
