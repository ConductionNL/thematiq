# Add your own tokens and retire them with notice

## Why

A house style grows and changes while apps depend on it. Two steps of that life are missing in
thematiq.

**Adding a token.** The token editor edits the tokens in `TokenRegistry`, which is code
(`lib/Service/TokenRegistry.php`: the brand tokens are hand-listed, the component tokens come from
`scripts/mapping/component-tokens.json`). `CustomOverridesService::write()` drops every name the
registry does not list (`lib/Service/CustomOverridesService.php:172-183`). An organisation that
wants a token for its own portal or its own custom CSS has to upload a whole token set that declares
it, or wait for a release.

**Retiring a token.** Thematiq reads a DTCG `$deprecated` notice on import and shows it to the
administrator once (`lib/Service/DesignTokensMapper.php:564-575`,
`lib/Controller/CustomTokenSetController.php:288-291`, `js/admin.js:4186-4194`). The notice is stored
in the set's manifest entry (`lib/Service/CustomTokenSetService.php:248-250`) and goes no further.
An administrator cannot mark a token as deprecated at all, and an app that reads the house style
has no way to learn that a token is going away.

Matrix evidence (quoted from `rowblock.py aut-add-token gov-token-deprecation`):

### Row `aut-add-token` (thematiq matrix, area authoring)

- Capability: Add a new design token from the house style editor without changing the theme code.
- Own rating: partial; built.state `built`. Built evidence: the token editor only edits tokens that
  already exist; a new token is added without code by uploading a token set that declares it
  (aut-upload-css, lib/Controller/CustomTokenSetController.php), not from the editor
- Demand: roadmap at https://liferay.atlassian.net/browse/LPD-80283 (organisations can extend a
  shared token vocabulary without a developer release)
- Liferay DXP (style books, themes, client extensions) rated `partial`:
  https://liferay.atlassian.net/browse/LPD-80283 (In Development) : a New Token modal stores custom
  tokens per style book; persistence stories https://liferay.atlassian.net/browse/LPD-80290 and
  https://liferay.atlassian.net/browse/LPD-80291 are closed, the editor button
  https://liferay.atlassian.net/browse/LPD-80271 is still in development
- Rated no or unknown: Nextcloud Theming (built-in app) `unknown`, Microsoft 365 organisational
  branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `unknown`,
  openDesk theming `unknown`, Tokens Studio (Figma plugin and platform) `unknown`

Missing half: adding a new token from the editor.

### Row `gov-token-deprecation` (thematiq matrix, area governance)

- Capability: Mark a token as deprecated with a severity, a replacement token and a removal date,
  so teams that consume the house style see it before it disappears.
- Own rating: partial; built.state `built`. Built evidence: thematiq reads a DTCG $deprecated
  notice on import and shows it to the admin (lib/Controller/CustomTokenSetController.php:288-291,
  js/admin.js:4186-4189, lib/Service/TokenSetConverterService.php:177-181), but cannot author a
  deprecation with a replacement and a removal date
- Demand: changelog at https://github.com/tokens-studio/figma-plugin/releases/tag/2.12.1 (a
  government house style changes under many consuming apps; a deprecation with a replacement and a
  date lets suppliers migrate before a token is removed)
- Tokens Studio (Figma plugin and platform) rated `yes`: tokens-studio/figma-plugin@2.12.1
  packages/tokens-studio-for-figma/src/app/components/EditTokenForm.tsx:450-468: a $deprecated
  block with severity is written on the token; CHANGELOG.md 8ad16a0e3 adds visual indicators and
  two-way sync of the state to Studio
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, Microsoft 365 organisational
  branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `no`, openDesk
  theming `no`, Liferay DXP (style books, themes, client extensions) `unknown`

Missing half: authoring a deprecation with a severity, a replacement and a removal date, and
showing it to the apps that consume the house style.

## What changes

- The token editor gets a section "Your own tokens" below the tabs. An administrator adds a token
  with a name, a label, a type, a light value and, for a colour, a dark value.
- Own tokens are named `--nldesign-org-*`. The prefix keeps them apart from Nextcloud's variables,
  from the thematiq vocabulary and from upstream names such as `--nldesign-custom-checkbox-size`,
  which `css/tokens/haarlem.css:96` already uses.
- Own tokens are served from `custom-overrides.css`, so they load last, work in both themes and
  travel with the overrides download.
- An administrator can mark any `--nldesign-*` token as deprecated, own or shipped, with a
  severity, a replacement token, a removal date and a message.
- Apps that consume the house style can read every deprecation from
  `GET /apps/thematiq/api/token-deprecations`, as any logged-in user. The served stylesheet carries
  a comment above each deprecated own token, and the DTCG export writes `$deprecated`.
- A deprecated own token keeps its value until the administrator removes it. After its removal
  date the panel flags it as due. A shipped token's value is never removed by this change: its
  deprecation is a notice.
- When an upload carries `$deprecated` notices, the result offers to record them as deprecations.
- Own tokens and deprecations are written to the theming audit log and to the configuration bundle.

## Capabilities

### New capabilities

- `own-tokens`: adding, editing and removing organisation tokens from the token editor.
- `token-deprecations`: authoring deprecations, publishing them to consuming apps, and the due flag.

### Modified capabilities

- `theming-audit` (`openspec/specs/theming-audit/spec.md`): two new actions in the closed
  vocabulary, `own_token_changed` and `token_deprecation_changed`. ADDED requirement.

## Impact

- **Code**: new `lib/Service/OwnTokenService.php` and `lib/Service/TokenDeprecationService.php`;
  new `lib/Controller/OwnTokenController.php` (admin) and a `deprecations()` method on
  `lib/Controller/CatalogController.php` (non-admin, read-only, next to the catalogue);
  `lib/Service/CustomOverridesService.php` (renders own tokens and deprecation comments);
  `lib/Service/ThemingAuditService.php` (vocabulary); `lib/Service/ConfigBundleService.php` (two
  keys); `appinfo/routes.php`; `js/admin.js` (own tokens section, deprecate dialog, upload
  adoption); `l10n/en.json`, `l10n/nl.json`; `docs/features/token-editor.md`.
- **Endpoints**: `GET|POST /apps/thematiq/settings/tokens/own`, `DELETE /apps/thematiq/settings/tokens/own/{name}`,
  `GET|POST /apps/thematiq/settings/tokens/deprecations`, `DELETE /apps/thematiq/settings/tokens/deprecations/{name}`
  (all admin-only), and `GET /apps/thematiq/api/token-deprecations` (`#[NoAdminRequired]`, read-only).
- **Depends on**: `authoring-token-value-types` for the typed value grammar and dark values, and
  `authoring-dtcg-export` for writing `$deprecated`. Both are written as optional: without them the
  own token types are `color` and `text`, and the export step waits.
- **Sibling repos**: `ConductionNL/nextcloud-vue` could read `/api/token-deprecations` in its
  development build and warn in the console when a component reads a deprecated token. Not needed
  for this change's scenarios.

## Rows

- `aut-add-token`, `gov-token-deprecation`, from the thematiq matrix
  (`openspec/parity/capabilities.json`).
