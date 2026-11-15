# Design: give the main button a colour of its own

## Where it fits (development `e13b8252`)

- **The primary element colour** is `--color-primary-element`, with `-hover` and `-text`. Nextcloud
  and nc-vue fill the main button with it. The NL Design System bundle declares it twice: on
  `:root` in `css/systems/nldesign/overrides.css` and on `body` in `css/systems/nldesign/theme.css`,
  both from `--nldesign-color-primary`. The La Suite bundle declares it on `body` in
  `css/systems/lasuite/bridge.css`, from its own brand scale.
- **The main button itself** is painted from component tokens in the NL Design System bundle
  (`--nldesign-component-button-primary-action-*`, `theme.css`). `utrecht-bridge.css` fills them
  from a set's Utrecht button tokens, else from the primary. `defaults.css` declares the same
  tokens for everything that resolves a set without a browser. The La Suite bundle paints the
  button from Cunningham's brand fill in `element-overrides.css`.
- **A dark variant** is a generated file that declares literal dark colours on `body`
  (`DarkPaletteService`). It derives them from `defaults.css` plus the set's own file.
- **Optional tokens with a fallback** already exist: `--nldesign-color-accent`, the login button
  tokens, every component token nobody set.
- **The variable-to-token map** is parsed out of `overrides.css` by
  `TokenSetPreviewService::parseMappings()`. The preview swatches, the stock set and the
  playground's export all read it.

## Decision 1: the token is optional and has no declared default

Every reader is written `var(--nldesign-color-action, <what the line was before>)`.

The brief suggested a token declared like the primary, with the primary as its default. That
breaks dark mode. A default in `defaults.css` is resolved where it is declared, on `:root`, with
the light primary. A dark variant puts its colours on `body`, and `theme.css` reads the element
colour there. The default would hand the light primary to a dark page. Measured in a browser on
development: in dark mode `--color-primary-element` on `body` is the dark primary today.

Without a default there is nothing to resolve in the wrong place. A set without the token takes
the fallback at every scope, and the fallback is the old value.

## Decision 2: three tokens, and a hover step that follows the action colour

| Token | Reads as |
|---|---|
| `--nldesign-color-action` | the main button's fill |
| `--nldesign-color-action-hover` | the fill on hover |
| `--nldesign-color-action-text` | text and icons on the fill |

A set that declares only the action colour must not hover to the primary's blue. So each bundle
declares one internal step:

```css
--thematiq-action-hover: var(
	--nldesign-color-action-hover,
	color-mix(in srgb, var(--nldesign-color-action) 85%, black)
);
```

With an action colour it is the declared hover, or the colour 15% darker. Without one, its own
`var()` has nothing to read, the property is invalid, and a line that reads it takes its fallback.

The text has no such rule. A set that declares an action colour and no text keeps the text of its
primary, and the contrast check says so when that does not read.

The light tints (`--color-primary-element-light` and its text and hover) stay with the primary.
They are a wash of the brand behind brand-coloured text. A green wash under blue text helps nobody.

## Decision 3: what follows the action colour

Everything that reads `--color-primary-element`, its hover or its text. In thematiq's own
component table (`scripts/mapping/component-tokens.json`) that is:

- the main button, and the confirm button of a dialog
- the login button
- a checked checkbox, radio button and switch
- the progress bar
- the selected entry in the app navigation, and the active tab of the sidebar
- the focus border of a text input and a text area
- the counter badge

In nc-vue 9.11 twenty-eight components read the variable, among them `NcButton`,
`NcCheckboxRadioSwitch`, `NcAppNavigationItem`, `NcListItem`, `NcActions`, `NcChip`,
`NcCounterBubble`, `NcInputField`, `NcTextArea`, `NcDateTimePicker`, `NcModal` and
`NcAppSidebarTab`. Nextcloud core reads it in thirteen stylesheets, and Files, Sharing, Settings,
Workflow engine and User status in theirs.

What stays with the primary: the header, links, `--color-primary` itself, the light tints, the
selected row of a list.

## Decision 4: which bundles read it

The NL Design System bundle and the La Suite bundle (which the Cunningham sets wear). Summer
Breeze reads only its own `--summer-*` names, and High contrast is black on white by definition.
Neither reads the token.

## Decision 5: proof that an unset token changes nothing

Three layers.

1. **By construction.** `ActionColourTokenTest` pins each reading declaration to
   `var(<action token>, <old value>)`, fails on any read without a fallback and on any declared
   default, and fails when a shipped set declares the token.
2. **Every shipped set, resolved.** The same test runs the resolver, the contrast check and the
   shell role mapper over all 65 token files, and regenerates five dark variants byte for byte.
3. **A browser.** Chromium loaded every set's stylesheets in the order `CssInjectionService`
   links them, light and dark, before and after the change. It compared every custom property on
   `:root`, `body` and a child, and the computed colours of five buttons at rest and on hover.
   64 sets, two schemes, 357,383 values: no difference other than the new internal step, which is
   empty. A forced regeneration of all 62 dark variants wrote identical files.

The browser run is also a check in the repository, `npm run test:action-colour-unset`. It has no
earlier commit to compare with, so it rebuilds the stylesheets as they were: each reader replaced by
its fallback. It proves the readers are transparent. That each fallback is the old value is pinned
by `ActionColourTokenTest`.

## Decision 6: the contrast check

`ContrastService::check()` is what an upload, a multi-brand import, the shipped set audit and the
dark generator all call. It gains one pair, the action text on the action colour at 4.5:1, measured
only when a set moved a side of it. A set without the token, or with an action pair equal to its
primary pair, gets no entry: that pair is already measured, and a second entry would change what
every existing set is told.

`ComplianceReportService` already measures `--color-primary-element-text` on
`--color-primary-element` for the active configuration, through `overrides.css`. It follows the
action colour without a change.

## Decision 7: the variable-to-token map stays what it was

`parseMappings()` reads `var(--optional, var(--token))` as a mapping to `--token`. For the three
element variables that is the primary, as before, so the stock set, the playground export and the
reference pages get the same map. The optional name is kept beside it, and the preview shows a
set's action colour when the set declares one.

## Not in this change

- No shipped set declares the token.
- No field in the "from colours" form (`BrandFormService`, `scripts/mapping/brand-form.json`). It
  needs a rule, a field, strings in two languages and the parity fixture.
- No DTCG path (`color.action`) in `DesignTokensMapper`, and no target in the NL Design System
  converter (`scripts/mapping/nlds-to-nextcloud.json`).
- `scripts/mapping/variable-status.json` still names the primary as the token of the three element
  variables. That is the token every set has; the generated `docs/reference/mappings.md` follows it.
- No brand exception for the action colour in a dark variant. The primary keeps its light value in
  dark when it reads well there; an action colour is derived like any other colour.
