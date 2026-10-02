# Transparency, dark values and motion in the token editor

## Why

The token editor holds one kind of value well: an opaque colour for the light theme. Three kinds of
value a house style needs are only half there.

**Transparency.** A brand overlay, a focus ring or a translucent surface needs an alpha channel.
Uploaded sets already carry them: `css/tokens/amsterdam.css` declares
`--nldesign-color-focus: rgba(0, 70, 153, 0.5)`. The editor cannot hold one safely. The colour
picker takes six hex digits only, and moving it overwrites the text field without alpha
(`js/admin.js:2655-2665`). The text field syncs the picker only for six digits (`:2680-2683`). The
settings colour check accepts 3 or 6 digits (`lib/Service/ThemingService.php:91-93`, called from
`lib/Controller/SettingsController.php:410` through `validateColors()`). And alpha is dropped where
it is measured: `ContrastService::parseColor()` ignores the alpha of `rgba()` and does not read
8-digit hex at all (`lib/Service/ContrastService.php:242-274`).

**Dark values.** Every set has a derived dark stylesheet (`DarkPaletteService::deriveDarkDeclarations()`,
`lib/Service/DarkPaletteService.php:289`). A set file may hand-author dark values
(`openspec/specs/dark-mode/spec.md`, "Hand-Authored Dark Overrides Win"). The token editor has one
value per token and no dark field. Its overrides are written as `:root { name: value !important; }`
(`lib/Service/CustomOverridesService.php:249-278`). Nextcloud declares its dark variables on `:root`
when dark follows the system (a `media` stylesheet) and on `[data-theme-dark]` when a user picks
dark explicitly (`apps/theming/lib/Service/ThemeInjectionService.php:47-58` and
`apps/theming/lib/Controller/ThemingController.php:418-433` in the Nextcloud 35.0.1 checkout). So a
light override wins in dark mode for one kind of dark user and loses for the other.

**Motion.** The editor lists `--animation-quick` and `--animation-slow` as plain text fields
(`lib/Service/TokenRegistry.php:232-233`), with no check that the value is a duration. There is no
easing token at all: thematiq's own transitions hard-code `ease` (`css/systems/nldesign/theme.css:382`,
`:465-467`, `:611`, `:663-664`, `:708`).

Matrix evidence (quoted from `rowblock.py aut-alpha-hex aut-light-dark-values aut-motion-tokens`):

### Row `aut-alpha-hex` (thematiq matrix, area authoring)

- Capability: Set colour tokens with transparency using 8-digit hex values.
- Own rating: partial; built.state `built`. Built evidence: the settings colour fields accept only
  3 or 6 digit hex (lib/Service/ThemingService.php:91-93 isValidHexColor, called from
  lib/Controller/SettingsController.php:410; js/admin.js:3421 editor check); an uploaded token set
  is validated on token names, not values (lib/Service/CustomTokenSetValidator.php:64,113), so 8
  digit hex in an uploaded set passes through
- Demand: changelog at https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/using-a-style-book-to-standardize-site-appearance
  (brand overlays and translucent surfaces can stay inside the token set; origin is the style book
  documentation because no Liferay release-notes page naming this change was found)
- Liferay DXP (style books, themes, client extensions) rated `yes`:
  https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/using-a-style-book-to-standardize-site-appearance :
  'Starting with Liferay DXP 2025.Q4/Portal 2026.Q1, you can use 8-digit hex plus alpha codes
  (e.g., #RRGGBBAA) to set the color's opacity'
- Rated no or unknown: Nextcloud Theming (built-in app) `unknown`, Microsoft 365 organisational
  branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `unknown`,
  openDesk theming `unknown`, Tokens Studio (Figma plugin and platform) `unknown`

Missing half: 8-digit hex in the settings and token editor colour fields. Uploaded sets already
pass it through.

### Row `aut-light-dark-values` (thematiq matrix, area authoring)

- Capability: Give a colour token separate light and dark values in the house style editor.
- Own rating: partial; built.state `built`. Built evidence: each shipped set has a separate dark
  stylesheet (css/tokens/dark/amsterdam.css) derived by lib/Service/DarkPaletteService.php:289
  deriveDarkDeclarations(); the token editor overrides hold one value per token with no dark field
  (grep -n dark lib/Service/CustomOverridesService.php lib/Controller/OverridesController.php finds
  nothing)
- Demand: changelog at https://liferay.atlassian.net/browse/LPD-96942 (lets a brand define its own
  dark palette instead of an automatic inversion)
- Liferay DXP (style books, themes, client extensions) rated `partial`:
  https://liferay.atlassian.net/browse/LPD-96942 (fixed in 7.4.13 DXP U153) and
  https://liferay.atlassian.net/browse/LPD-93514 : the style book colour picker accepts
  light-dark() values; https://liferay.atlassian.net/browse/LPD-104225 notes site dark mode is
  left to customers and https://learn.liferay.com/w/dxp/personalization/experiences/using-dark-mode
  dark mode is a beta for admin screens
- Rated no or unknown: Nextcloud Theming (built-in app) `unknown`, Microsoft 365 organisational
  branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `unknown`,
  openDesk theming `unknown`, Tokens Studio (Figma plugin and platform) `unknown`

Missing half: a dark value per colour token in the token editor.

### Row `aut-motion-tokens` (thematiq matrix, area authoring)

- Capability: Define motion, duration and easing as tokens of the house style.
- Own rating: partial; built.state `built`. Built evidence: shipped sets carry upstream motion
  values where the design system defines them (css/tokens/rotterdam.css:741
  --utrecht-backdrop-fade-in-animation-duration), but the token editor exposes no duration or
  easing tokens and reduced motion is handled separately (acc-reduced-motion)
- Demand: changelog at https://github.com/tokens-studio/figma-plugin/releases/tag/2.12.1 (motion
  tokens are what a theme needs to honour reduced-motion preferences consistently across apps)
- Liferay DXP (style books, themes, client extensions) rated `partial`:
  https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/developer-guide/frontend-token-definitions :
  a token is any typed value (Integer, Number, String, Boolean) mapped to a CSS variable, so a
  theme can define duration or easing tokens; the Classic categories in
  https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/using-a-style-book-to-standardize-site-appearance
  (colour, spacing, general, layout, typography, buttons) include none
- Tokens Studio (Figma plugin and platform) rated `yes`: tokens-studio/figma-plugin@2.12.1
  packages/tokens-studio-for-figma/src/constants/TokenTypes.ts:28-33 duration, cubicBezier and
  transition types (CHANGELOG.md 09b51eff2)
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, Microsoft 365 organisational
  branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `no`, openDesk
  theming `no`

Missing half: typed duration and easing tokens in the editor. The row's evidence says the editor
has no duration tokens. It has two, as untyped text fields (`TokenRegistry.php:232-233`). The
easing half is fully missing.

## What changes

- Colour fields in the token editor accept transparency. Next to the picker, an opacity control
  sets the alpha, and the text field accepts `#rgba`, `#rrggbbaa`, `rgba()` and `hsla()`. Moving
  the picker keeps the alpha.
- Each colour token gets an optional dark value. When it is empty, the editor shows the value the
  dark palette would derive, and saves that one. Light and dark overrides are written in the same
  scopes the generated dark stylesheets use, so both kinds of dark user see the same result.
- The editor types its motion tokens and points them where the transitions read. Today "Animation
  quick" writes Nextcloud's `--animation-quick`, while thematiq's own button and field transitions
  read `--nldesign-animation-quick` (`css/systems/nldesign/theme.css:382`, `:465-467`), and
  `css/systems/nldesign/overrides.css:212-213` maps only the other way. The rows become duration
  fields that write both names. A new token, `--nldesign-animation-easing`, holds the easing curve,
  and thematiq's own transitions read it. Reduced motion keeps winning.
- The server checks every editor value against its token's type and refuses a wrong one with 400
  naming the token.
- Contrast is measured as a translucent colour renders: blended over what is behind it.
- Derived dark variants keep a light token's alpha. Today `rgba(255, 255, 255, 0.08)` becomes the
  opaque `#141414` (`css/tokens/dark/conduction-new.css:196`).
- Nextcloud core theming takes no transparency. A translucent primary or background colour is
  blended over the background before the theming sync sends it, and the sync dialog says so.

## Capabilities

### New capabilities

- None.

### Modified capabilities

- `token-editor-ui`: transparency in colour fields, a dark value per colour token, typed motion
  tokens, and value checks per type. ADDED requirements.
- `dark-mode`: editor overrides apply in both dark scopes, and derived dark values keep alpha.
  ADDED requirements.
- `theming-sync`: translucent colours are blended before they reach Nextcloud core. ADDED
  requirement. The existing "Color Validation" requirement is unchanged: core still gets 3 or 6
  digits.
- `token-set-contrast-audit`: translucent colours are measured as they render. ADDED requirement,
  inside the one-contrast-implementation rule.

## Impact

- **Code**: `lib/Service/TokenRegistry.php` (types `duration` and `easing`, the easing token),
  `lib/Service/CustomOverridesService.php` (dark values, dark scopes), `lib/Controller/OverridesController.php`
  (typed checks, `darkOverrides`), `lib/Service/ContrastService.php` (alpha), `lib/Service/DarkPaletteService.php`
  (alpha kept, a public derive call for one token), `lib/Service/CustomTokenSetService.php`
  (`deriveTheming()` blends), a new repair step `lib/Repair/MigrateOverrideValueTypes.php`
  registered under `<post-migration>` in `appinfo/info.xml` (renames motion overrides, adds dark
  values to existing colour overrides), `js/admin.js` and `js/lib/tokenTransforms.js` (opacity control, dark
  field, motion inputs), `css/systems/nldesign/defaults.css` and `css/systems/nldesign/theme.css`
  (the easing token), `l10n/en.json`, `l10n/nl.json`, `docs/features/token-editor.md`.
- **Endpoints**: `POST /apps/thematiq/settings/overrides` gains an optional `darkOverrides` object
  and a 400 for a wrong value. No new route.
- **Sibling repos**: `ConductionNL/nextcloud-vue` may read `--nldesign-animation-easing` for its own
  transitions. Not needed for this change's scenarios. Nextcloud core components keep their own
  easing, out of reach.
- **Regenerated files**: every `css/tokens/dark/*.css`, because derived dark values now keep alpha
  (`occ thematiq:generate-dark-variants --force`, the command name at `lib/Command/GenerateDarkVariants.php:67`).

## Rows

- `aut-alpha-hex`, `aut-light-dark-values`, `aut-motion-tokens`, from the thematiq matrix
  (`openspec/parity/capabilities.json`).
