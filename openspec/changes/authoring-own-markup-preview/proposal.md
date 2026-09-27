# Preview your own components with the house style

## Why

A team that builds components for a municipal design system wants to see them in the real brand
while it builds. Thematiq has the place for that: the component playground in the token editor
draws components with the live tokens before anything is saved. It draws only the components in
its own inventory, `js/playground/components.json`, which `lib/Settings/Admin.php` publishes
through `PlaygroundStateService::getInitialState()` (`lib/Service/PlaygroundStateService.php`,
key `playgroundInventory`). A builder's own card, form or banner is not in that file, and there is
no way to put it there without a release.

Matrix evidence (quoted from `rowblock.py aut-component-preview`):

### Row `aut-component-preview` (thematiq matrix, area authoring)

- Capability: See your own components rendered with the house style tokens while you build them,
  before publishing.
- Own rating: partial; built.state `built`. Built evidence: the component playground in the token
  editor (js/playground.js header, templates/settings/admin.php:32-42,
  lib/Service/PlaygroundStateService.php) renders the inventory in js/playground/components.json
  with the live tokens before saving; a builder's own new components are not in that inventory
- Demand: featureRequest at https://liferay.atlassian.net/browse/LPD-88654 (component builders for
  a municipal design system need to see the real brand while building)
- Rated no or unknown: Nextcloud Theming (built-in app) `unknown`, Microsoft 365 organisational
  branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `unknown`,
  openDesk theming `unknown`, Liferay DXP (style books, themes, client extensions) `no`, Tokens
  Studio (Figma plugin and platform) `unknown`

The row is partial. Previewing shipped components with live tokens is built. Previewing a
builder's own markup is missing.

## What changes

- The playground gets a chip "Your component" in every tab's chip row. It opens a stage with two
  fields, HTML and CSS, and a preview frame beside them.
- The preview frame is a sandboxed `<iframe srcdoc>`: no scripts, no forms, no popups, no top
  navigation. The markup is cleaned against an allowlist before it reaches the frame, and the
  frame carries its own Content Security Policy with no `script-src`. Three locks, so no single
  one has to be perfect.
- The frame wears the house style: every `var(--...)` the builder's code reads gets the value the
  playground's preview has at that moment, including unsaved edits. Editing a token repaints the
  frame at once.
- The token list filters to the tokens the builder's code reads, as it does for a shipped
  component. A name the editor cannot write is listed with its value and marked read-only here.
- A switch shows the frame in the light or the dark theme.
- An administrator can save up to 20 own components by name and open them again. They are stored
  in the app's data folder, and they change nothing any user sees.
- The selection is addressable like any other chip: `#preview={tab}/own-{slug}`.

## Capabilities

### New capabilities

- `own-component-preview`: pasting, cleaning, framing, token filtering, saving and addressing a
  builder's own component inside the playground.

### Modified capabilities

- None. The capability builds on `component-playground`, which the open change
  `openspec/changes/component-playground` adds. Its requirements stay as they are: the inventory
  test ("The inventory cannot name a token that does not exist") covers
  `js/playground/components.json` only, and own components are not part of it.

## Impact

- **Code**: `js/playground.js` (the chip, the stage, the frame, the token bridge, the hash), new
  `js/lib/markupSanitizer.js` (dual-mode like `js/lib/layerSwap.js`), `css/playground.css`, new
  `lib/Service/OwnComponentService.php` (IAppData storage and limits), new
  `lib/Controller/OwnComponentController.php`, `appinfo/routes.php`, `lib/Service/PlaygroundStateService.php`
  (saved component names in the initial state), `l10n/en.json`, `l10n/nl.json`,
  `docs/features/token-editor.md`.
- **Endpoints**: `GET|POST /apps/thematiq/settings/playground/components` and
  `DELETE /apps/thematiq/settings/playground/components/{slug}`, admin-only.
- **Depends on**: the open change `component-playground` (the chip row, the stage, the cloned
  rows and the hash). This change lands after its tasks for those parts.
- **Sibling repos**: none.
- **Security**: the only new surface that takes markup. See design decisions 2 to 4.

## Rows

- `aut-component-preview`, from the thematiq matrix (`openspec/parity/capabilities.json`).
