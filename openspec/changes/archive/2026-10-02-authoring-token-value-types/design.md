# Design: transparency, dark values and motion in the token editor

## Where it fits (development `b4e7568`)

- **The editor** is vanilla JS. `renderTokenEditor()` (`js/admin.js:2415`) groups
  `tokenRegistry` by tab and calls `buildTokenRow()` (`:2538`). A `color` row is a native
  `<input type="color">` plus a text field (`:2581-2603`). Any other row is one text field
  (`:2604-2615`). `wireTokenRows()` (`:2651`) syncs picker to text (`:2655-2665`, the picker's six
  digits overwrite the text) and text to picker only for six digits (`:2680-2683`).
  `normaliseColorForPicker()` (`:3407`, pure part in `js/lib/tokenTransforms.js:93-105`) turns a
  value into the picker's `#rrggbb`. That is the "editor check" the matrix cites at `:3421`: it is
  a conversion for the picker, not a validation. The text field has no check at all.
- **The registry** (`lib/Service/TokenRegistry.php`) knows two types, `color` and `text`. The two
  motion tokens are `text` (`:232-233`).
- **Saving.** `OverridesController::setOverrides()` (`lib/Controller/OverridesController.php:137-162`)
  passes the payload to `CustomOverridesService::write()` (`lib/Service/CustomOverridesService.php:154`).
  `filterEditable()` (`:172-183`) drops unknown names in silence. `buildDeclarationLines()`
  (`:249-278`) drops values with `{`, `}`, `;` or comment markers, and writes every survivor as
  `name: value !important;` inside one `:root` block (`buildCss()`, `:228-238`). No value is checked
  against a type.
- **Injection.** `CssInjectionService` emits `custom-overrides` after the set layers (`:575`), so
  administrator intent wins on `:root`.
- **Dark variants.** `DarkPaletteService::deriveDarkDeclarations()` (`lib/Service/DarkPaletteService.php:289-325`)
  parses each value with `ContrastService::parseColor()`, skips what it cannot read (`:316-321`),
  and writes a 6-digit hex (`deriveColorToken()`, `:381-406`). `renderDarkCss()` (`:820-846`) writes
  two scopes: a `prefers-color-scheme: dark` block on `body:not([data-theme-light])...`, and an
  unconditional `body[data-theme-dark], body[data-themes*=dark]` block
  (`openspec/specs/dark-mode/spec.md`, "Dark Scope Selectors").
- **Contrast.** `ContrastService::parseColor()` (`lib/Service/ContrastService.php:242-274`) reads
  `#rgb`, `#rrggbb` and `rgb()`/`rgba()`. The alpha of `rgba()` is matched and thrown away
  (`:259-265`). `ratio()` (`:195`) works on opaque triples.
- **Core theming.** `ThemingService::validateColors()` (`lib/Service/ThemingService.php:104-113`)
  uses `isValidHexColor()` (`:91-93`). `CustomTokenSetService::deriveTheming()`
  (`lib/Service/CustomTokenSetService.php:451-462`) copies `--nldesign-color-primary` and
  `--nldesign-color-background` into the manifest verbatim. Nextcloud core refuses anything but 3
  or 6 digits (`apps/theming/lib/Controller/ThemingController.php:119`, `:127`, Nextcloud 35.0.1).
- **Motion.** `defaults.css:198-199` declares `--nldesign-animation-quick: 100ms` and
  `--nldesign-animation-slow: 300ms`. `overrides.css:212-213` maps Nextcloud's `--animation-quick`
  and `--animation-slow` onto them. `theme.css` uses `ease` literally in every transition
  (`:382`, `:465-467`, `:611`, `:663-664`, `:708`). Reduced motion is a separate rule set
  (`theme.css:1139` onward).

## Decision 1: one value grammar per token type, checked on both sides

`TokenRegistry` gains two types: `duration` and `easing`. The grammar per type:

| Type | Accepted |
|---|---|
| `color` | `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`, `rgb()`, `rgba()`, `hsl()`, `hsla()`, a CSS named colour, `transparent` |
| `duration` | a number from 0 to 5000 with `ms`, or 0 to 5 with `s` |
| `easing` | `linear`, `ease`, `ease-in`, `ease-out`, `ease-in-out`, or `cubic-bezier(x1, y1, x2, y2)` with both x in 0..1 |
| `text` | as today |

A new `TokenValueValidator` holds the grammar in PHP. `js/lib/tokenTransforms.js` mirrors it for
the inline message. `setOverrides()` refuses a value that fails its type with 400, naming the token
and the type. Nothing is written.

The same endpoint also answers 400 for a name the registry does not list, instead of dropping it.
`openspec/specs/token-editor-ui/spec.md` already asks for that ("Admin attempts to set excluded
token via API", HTTP 400). Today the code returns 200.

Rejected: validating in the browser only. ADR-005 puts every check on the backend.

## Decision 2: transparency in colour fields

The colour row gets an opacity control (a range from 0 to 100 plus a number field) next to the
picker. The picker sets the colour, the control sets the alpha, and together they write
`#rrggbbaa`, or `#rrggbb` at 100%. Typing any `color` value in the text field updates both
controls when it parses, and leaves them alone when it does not. The swatch shows the colour over a
checkerboard so the transparency is visible.

`normaliseColorForPicker()` keeps returning six digits for the picker. A new `splitAlpha(value)`
in `tokenTransforms.js` returns `{hex, alpha}`.

Rejected: a picker library with an alpha channel. The editor is vanilla JS without a build step
(`openspec/config.yaml`), and the native picker is keyboard accessible as it is.

## Decision 3: a dark value per colour token

Each `color` row gets a second line, "Dark", with the same picker, opacity control and text field.
Empty means "derive". The field then shows the derived value as its placeholder, computed by a new
public `DarkPaletteService::deriveDarkValue(string $token, string $lightValue, array $context)`.
It wraps the existing `deriveColorToken()` so the editor and the generated stylesheets never
disagree.

Storage stays one file. `custom-overrides.css` keeps its `:root` block for light values and gains
the two dark blocks `renderDarkCss()` writes, with the same selectors. Every colour override gets a
dark declaration: the administrator's own value, or the derived one. `POST /settings/overrides`
takes an optional `darkOverrides` object next to `overrides`. `GET /settings/overrides` returns
both. `CustomOverridesService::read()` parses the `body[data-theme-dark]` block back.

This is what makes a light override behave the same for both kinds of dark user. Nextcloud's
dark variables sit on `:root` for a user who follows the system, and on the body for a user who
chose dark (`apps/theming/lib/Service/ThemeInjectionService.php:47-58`,
`apps/theming/lib/Controller/ThemingController.php:418-433`). A body-level dark declaration from
thematiq wins in both cases, as the generated dark stylesheets already rely on.

Rejected: writing `light-dark()` into the `:root` block. It needs `color-scheme` set per element,
which Nextcloud does not do for its explicit dark choice. It would also hide the dark value from
`ContrastService` and the dark palette, which read literals.

Rejected: leaving a colour override without a dark value. That keeps today's split behaviour.

## Decision 4: motion tokens the transitions actually read

Today the editor's "Animation quick" writes Nextcloud's `--animation-quick`. Thematiq's own
transitions read `--nldesign-animation-quick` instead (`theme.css:382`, `:465-467`), and the mapping
runs the other way: `overrides.css:212-213` sets `--animation-quick` from
`--nldesign-animation-quick`. So an administrator who changes "Animation quick" changes Nextcloud's
own components and none of thematiq's styled buttons and fields.

The registry therefore lists the thematiq names, type `duration`:
`--nldesign-animation-quick` and `--nldesign-animation-slow`. Saving one writes two declarations,
the thematiq name and the Nextcloud name with the same value, so both kinds of component follow even
on a set whose design system does not load `overrides.css`. An upgrade step renames existing
`--animation-quick` and `--animation-slow` overrides.

`defaults.css` declares a new `--nldesign-animation-easing: ease`, today's behaviour. Every
transition in `theme.css` that says `ease` reads `var(--nldesign-animation-easing, ease)` instead.
The registry lists it in the content tab, type `easing`, after the two durations.

The editor renders a `duration` as a number field plus a unit select, and an `easing` as a select
with the five keywords and "Custom curve", which opens four number fields.

A small preview next to the motion rows moves a block with the chosen duration and easing. Under
`prefers-reduced-motion: reduce` the preview does not move, and shows the values as text.

Reduced motion stays authoritative. The `@media (prefers-reduced-motion: reduce)` rules in
`theme.css` (`:1139` onward) are not touched, and they win over any duration an administrator sets.

Rejected: keeping `--animation-quick` as the edited name and reversing the mapping in
`overrides.css`. Every shipped set declares the `--nldesign-` name (`defaults.css:198-199`,
`css/tokens/vng.css:187-188`), so the thematiq name is the one a set and the editor share.

Rejected: an easing override on Nextcloud core. Core has no easing variable, so there is nothing
to point at. Its components keep their own curves. Conduction apps can read the token through
`@conduction/nextcloud-vue`.

## Decision 5: contrast of a translucent colour

`ContrastService::parseColor()` also reads `#rgba` and `#rrggbbaa`, and keeps the alpha of `rgba()`
and `hsla()`. It gains `parseColorWithAlpha()` returning `[r, g, b, a]`. The existing
`parseColor()` stays for callers that want a triple.

Before `ratio()`, a translucent foreground is blended over its background, and a translucent
background over the page background (`--nldesign-color-background`, else white). This is how
WCAG measures what renders. A colour that cannot be parsed stays `unevaluated`, and
`unevaluated` never passes, as `openspec/specs/token-set-contrast-audit/spec.md` requires.

## Decision 6: dark variants keep alpha

`deriveColorToken()` derives the dark colour from the opaque channels, and the result keeps the
light value's alpha: `#rrggbbaa` when alpha is below 1. Every `css/tokens/dark/*.css` is
regenerated with `occ thematiq:generate-dark-variants --force`. The generator version constant
bumps so stale files are spotted by their header.

## Decision 7: core theming gets opaque colours

When `deriveTheming()` copies a primary or background colour into the manifest, a translucent
value is blended over the set's background (else white) into 6-digit hex. The theming sync dialog
shows the original next to the blended value with the line "Nextcloud's own theming has no
transparency. It gets this colour instead." `validateColors()` does not change.

Rejected: sending 8 digits to core. Core refuses them on its own route (`ThemingController.php:119`,
`:127`), and its colour helpers assume opaque values.

## Risks

- **Every dark file changes.** Decision 6 regenerates all of them. The diff is mechanical and the
  headers carry the source hash, so a review compares counts, not colours.
- **Existing overrides gain dark declarations.** The first save after upgrade writes derived dark
  values for every colour override. The repair step `MigrateOverrideValueTypes` does the same on upgrade,
  so the change lands with the release and not on someone's next save.
- **Values that used to save now fail.** A typo in a colour field used to save and render nothing.
  It now gets a 400. The message names the token so the fix is obvious.

## Out of scope

- Dark values for text, duration and easing tokens.
- `light-dark()` in uploaded sets. They keep passing through, and stay unevaluated for contrast.
- Transitions inside Nextcloud core components.
- A reduced-motion setting per instance (row `acc-reduced-motion`).
