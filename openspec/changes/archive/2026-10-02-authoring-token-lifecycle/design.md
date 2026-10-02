# Design: add your own tokens and retire them with notice

## Where it fits (development `b4e7568`)

- **What the editor may edit** is `TokenRegistry::getTokens()` (`lib/Service/TokenRegistry.php`):
  the hand-listed brand tokens plus the component tokens read from
  `scripts/mapping/component-tokens.json`. `isEditable()` answers from that list.
- **What gets written** is `CustomOverridesService::write()` (`lib/Service/CustomOverridesService.php:154`):
  `filterEditable()` (`:172-183`) keeps only registry names, `buildDeclarationLines()` (`:249-278`)
  drops unsafe values and writes `name: value !important;` in one `:root` block. The file is served
  after every set layer (`CssInjectionService`, `:575`).
- **The editor** renders the registry by tab (`renderTokenEditor()`, `js/admin.js:2415`) into four
  tabs, which `openspec/specs/token-editor-ui/spec.md` fixes at exactly four for Nextcloud's
  variables ("Functional Tab Groups").
- **Deprecations today** exist only on import. `DesignTokensMapper::maybeWarnDeprecated()`
  (`lib/Service/DesignTokensMapper.php:564-575`) turns a `$deprecated` into `{path, message}`.
  `TokenSetConverterService` keeps them apart from the report (`:177-186`, `getImportWarnings()` at
  `:436`). The upload returns them as `importWarnings` (`CustomTokenSetController.php:288-291`) and
  stores them on the set (`CustomTokenSetService.php:248-250`). The panel prints them once
  (`js/admin.js:4186-4194`).
- **Consumers** already have a non-admin read endpoint: `CatalogController::tokenSets()`
  (`lib/Controller/CatalogController.php:82-85`, `GET /api/token-sets`, `#[NoAdminRequired]`). Its
  shape is closed at five fields (`openspec/specs/app-token-set-selection/spec.md`), so
  deprecations need their own route beside it.
- **Audit** drops any action outside `ThemingAuditService::VOCABULARY` (`lib/Service/ThemingAuditService.php:108-120`, `:189-194`).
- **Configuration bundle** export (`lib/Service/ConfigBundleService.php:241-262`) carries the raw
  overrides CSS, not their metadata. The importer reads named keys only.

## Decision 1: own tokens live in their own store and render into the overrides file

`OwnTokenService` stores own tokens in appconfig `own_tokens`, a JSON object keyed by name:
`{label, type, value, darkValue?, description?, createdAt, updatedAt}`. Types are the editor's
types: `color` and `text`, plus `duration` and `easing` once `authoring-token-value-types` lands.
Values pass the same checks as editor values.

`CustomOverridesService` renders own tokens into `custom-overrides.css` after the registry
overrides: light values in `:root`, dark values in the dark scopes that
`authoring-token-value-types` adds (or `:root` only until it lands). Own tokens do not get
`!important`: nothing else declares them, so there is nothing to win against.

Rejected: letting own tokens into `TokenRegistry`. The registry is code on purpose (its header
explains why every token must have a stylesheet that applies it). Own tokens have no stylesheet
until someone reads them.

Rejected: a separate stylesheet for own tokens. One more request on every page, and one more layer
to order against the overrides.

## Decision 2: the `--nldesign-org-` prefix

An own token's name is `--nldesign-org-` plus a slug the administrator types
(`[a-z0-9]+(-[a-z0-9]+)*`, at most 48 characters). The editor shows the prefix as fixed text in
front of the field.

- `config.yaml` requires every token to use the `--nldesign-` prefix.
- `--nldesign-custom-` is taken: upstream sets already declare `--nldesign-custom-checkbox-size`
  (`css/tokens/haarlem.css:96`, `css/tokens/provincie-zuid-holland.css:93`).
- `--nldesign-org-` appears nowhere in `css/`, `js/`, `lib/` or `scripts/` today.

Rejected: free names. A name such as `--nldesign-color-primary` or `--color-primary` would silently
override a shipped or core token.

## Decision 3: own tokens sit below the tabs, not in a fifth tab

"Your own tokens" is a section under the four tabs, with an "Add a token" button that opens a
dialog (`src/dialogs/`-style isolation does not apply to this vanilla JS panel, so the dialog is
one function in `js/admin.js` with its own `<dialog>` element). Each own token is a row like a
registry row, plus "Edit", "Deprecate" and "Remove".

Rejected: a fifth tab. The spec fixes four tabs for Nextcloud's variables, and own tokens are not
Nextcloud's.

## Decision 4: a deprecation is a record about a name

`TokenDeprecationService` stores appconfig `token_deprecations`, keyed by token name:
`{severity, replacement?, removalDate?, message?, deprecatedAt, source}`.

- `severity` is `info`, `warning` or `critical`. Task 1.1 checked the values Tokens Studio writes:
  `packages/tokens-studio-for-figma/src/app/components/EditTokenForm.tsx:444-489` at tag 2.12.1
  sets `$deprecated: {severity: 'warning', message: ''}` when the box is ticked, and its two
  severity buttons write `'warning'` and `'error'`. The mapping: Tokens Studio `warning` is
  `warning`, `error` is `critical`; `info` has no Tokens Studio value and exports as `warning`.
  On import, `DesignTokensMapper` reads the object form with the same mapping.
- `replacement` must be a token name that exists: an own token, a registry token or a name in the
  thematiq vocabulary.
- `removalDate` is an ISO date, today or later when set.
- `source` is `admin` or `import`.
- `state` is `active`, or `removed` once the administrator removes the own token. A removed
  token's record stays, so a consumer that missed the date can still read what happened.

Any `--nldesign-*` name can be deprecated. An own token has a full life: deprecated, then removed by
the administrator. A shipped name cannot be removed by an administrator, because its value comes
from a set file. Its deprecation tells consumers not to rely on it. The value stays.

A deprecation never changes a value. Removing a deprecated own token is a separate, deliberate
action. After the removal date, the panel marks the token "due for removal", and nothing happens on
its own.

Rejected: removing a token automatically on its date. A missed migration in one app would break it
overnight, with nobody at the keyboard.

Rejected: aliasing a deprecated token to its replacement. It changes what the old name renders
without the consumer knowing, which is what a deprecation is meant to prevent.

## Decision 5: how consuming apps see a deprecation

1. `GET /apps/thematiq/api/token-deprecations`, `#[NoAdminRequired]`, read-only, beside the
   catalogue in `CatalogController`. It returns `{deprecations: [{token, severity, replacement,
   removalDate, message, deprecatedAt, due}]}`. It holds token names and dates only, no user data,
   and it is the same for every user, so there is no per-object check to make (ADR-005 applies to
   mutations). Anonymous requests are refused, as for the catalogue.
2. `custom-overrides.css` writes a comment above each deprecated own token:
   `/* deprecated (warning): use --nldesign-org-brand-accent, removal 2027-03-01 */`. A developer
   who inspects the variable sees it.
3. The DTCG export (`authoring-dtcg-export`) writes `$deprecated` as a string naming the
   replacement and the date, and keeps severity and date in the `nl.conduction.thematiq` extension.

Rejected: adding deprecations to `GET /api/token-sets`. Its shape is closed at five fields by its
spec, and a deprecation belongs to a name, not to a set.

Rejected: publishing deprecations in the public capabilities document. It is served to anonymous
visitors on every capabilities call, and it would grow with every deprecation.

## Decision 6: adopting imported notices

When an upload returns `importWarnings`, the result panel offers "Record as deprecations". Each
notice becomes a record with `source: import`, severity `warning`, the notice text as message, and
no replacement or date. The administrator can edit them after. Nothing is recorded without that
click.

## Decision 7: audit and bundle

Two new audit actions: `own_token_changed` (add, edit, remove, with name and old and new value) and
`token_deprecation_changed` (add, edit, remove). The configuration bundle gains `ownTokens` and
`tokenDeprecations`. The importer reads named keys only, so an older importer skips both. Task 5.3
proves that with a test.

## Risks

- **Own tokens nobody reads.** A token is useful only when custom CSS or an app reads it. The
  section's hint says so, and links the freeform custom CSS section where it can be used at once.
- **Name clashes after an upgrade.** A future thematiq release could add a name under
  `--nldesign-org-`. A unit test fails when any shipped file under `css/` declares or reads such a
  name (task 2.4), so a release cannot ship one.
- **Deprecating a shipped name the administrator does not own.** The panel labels those as
  "Notice only: the value comes from the token set".

## Out of scope

- Own tokens per token set. They are instance-wide, like the editor's overrides.
- Console warnings inside consuming apps (the `nextcloud-vue` sibling half).
- Deprecating Nextcloud core variables such as `--color-primary`.
