---
kind: code
---

## Why

Demonstrations and acceptance environments need a municipality that does not exist, so a demo
never wears the house style of a real one. Gemeente Zuiddrecht is that municipality. Its design is
a light workplace: a white top bar, a white app navigation, a grey workspace with white cards, a
red accent for "you are here" and a three-colour brand stripe.

Most of that look is a token set. The rest needs things thematiq could not express yet. The
selected navigation entry could not get a label colour of its own. The count badge could only be
coloured everywhere at once. The content surface was always the colour of the cards on it. The
light tints behind status pills had no token. And two choices were not choices at all: a light top
bar for a set that was drawn with a coloured one, and a brand stripe under the header.

## What Changes

- **Token set `zuiddrecht`** (`css/tokens/zuiddrecht.css`, generated dark variant, four logos,
  `token-sets.json` entry, reference page). Blue #3669A5 for actions, red #CC0000 as the accent,
  Fira Sans, Nextcloud's radius scale (8px controls, 12px containers).
- **Fira Sans 500 and 600** join the bundled 400 and 700, from the same Fontsource package.
- **Six new component token groups** in `scripts/mapping/component-tokens.json`, each undeclared
  until a set or an administrator sets it, so every other set renders as before:
  the selected navigation entry's label, the count badge in the navigation, the shadow colour of
  cards, the content surface, and the tint and label of the four status pills.
- **Three generator features** in `scripts/generate-component-scopes.mjs` that those tokens need:
  `selfOnly` (redirect a variable for the component's own box and hand it back to its children),
  `fallback` (a token that refines another token falls back to it before the global) and
  `alsoGlobals` (one token answers for a role that is read from more than one variable).
- **Workplace layout option** (`workplace_layout`: follow the theme, `default` or `light`).
  `light` gives any theme a top bar on the main background with its text.
- **Brand stripe option** (`brand_stripe`: follow the theme, on or off). Three colours, their
  ratio and the height come from tokens.
- **A token set may carry layout defaults** in a `layout` block in `token-sets.json`. Zuiddrecht
  turns both options on. An administrator's own choice always wins.

**Out of scope:** the two options do not travel in the configuration bundle yet. The login card
does not draw the stripe. On Nextcloud 32 and 33 the selected navigation entry keeps Nextcloud's
solid fill; the soft wash is how Nextcloud 34 and later draw it.

## Capabilities

### New Capabilities
- `zuiddrecht-token-set`: what the set declares and the contrast every text pair reaches.
- `workplace-layout`: the layout option, its three states and the stylesheet behind `light`.
- `brand-stripe`: the stripe option, its tokens and its stylesheet.

### Modified Capabilities
- `component-tokens`: the new tokens and the three generator features.

## Impact

- New files: `css/tokens/zuiddrecht.css`, `css/tokens/dark/zuiddrecht.css`,
  `css/token-overrides/zuiddrecht.css`, `css/workplace-layout.css`, `css/brand-stripe.css`,
  `img/logos/zuiddrecht*.svg`, `lib/Service/LayoutOptionsService.php`, four font files per font
  directory.
- `css/component-scopes.css` and `css/primary-lock.css` gain rules for the new tokens. Every new
  rule falls back to the value the element had before, so a set that declares none of the tokens
  renders the same.
- No migration. No stored value changes meaning. Additive, so a minor version.
