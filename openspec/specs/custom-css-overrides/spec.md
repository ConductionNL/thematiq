---
status: done
---

# Custom CSS Overrides Specification

## Purpose
Defines the CSS file persistence layer for user-defined token customizations.

`custom-overrides.css` is the single write target for all theme editor output. It is loaded last in the CSS stack so user intent always wins. NL Design token set CSS files are read-only presets and are never modified.

## Requirements

### Requirement: Custom Overrides File
The system MUST maintain a `custom-overrides.css` file in the nldesign app's CSS directory. This file MUST be written exclusively by the theme editor backend — no other write path exists.

#### Scenario: File does not exist on fresh install
@e2e exclude a browser cannot delete a file from the app directory, and every themed render recreates it; PHPUnit tests/Unit/Service/CustomOverridesServicePerSetTest.php::testNoSetNamedMeansTheActiveSet asserts ensureExists() creates the missing file as an empty block, and tests/Unit/Service/CssInjectionServiceTest.php::testCustomOverridesAlwaysLoadedLast asserts it runs before the link is emitted
- GIVEN the nldesign app is freshly installed
- WHEN Nextcloud loads the theming CSS
- THEN `custom-overrides.css` MUST NOT be required to exist
- AND the CSS stack MUST function correctly with the file absent

#### Scenario: File exists with custom tokens
- GIVEN `custom-overrides.css` contains one or more `--color-*` overrides
- WHEN the browser loads the CSS stack
- THEN the overrides MUST apply on top of all other CSS layers
- AND they MUST take effect without a page reload (only on next load)

### Requirement: CSS Stack Load Order
`custom-overrides.css` MUST be registered as the final CSS file in the nldesign app's CSS load order, after `element-overrides.css`.

Load order (final):
```
fonts.css → defaults.css → utrecht-bridge.css → theme.css → overrides.css
  → element-overrides.css → tokens/{set}.css → the other set layers
  → custom-overrides.css
```

The token set file loads after the whole design-system bundle, emitted by
`CssInjectionService::designSystemLayers()`, as the css-architecture scenario "Token set CSS
loaded after design system stylesheets" states. It only has to follow `defaults.css`, whose
`:root` declarations it overrides; the other bundle layers read its values through `var()`.
"The other set layers" are the ones `designSystemLayers()` lists after the token file (logo,
token-overrides, dark variant, contrast fixes, theme and component scopes).

#### Scenario: Custom override wins over NL Design token set
- GIVEN `tokens/utrecht.css` sets `--nldesign-color-primary: #CC0000`
- AND `overrides.css` maps `--color-primary: var(--nldesign-color-primary)`
- AND `custom-overrides.css` sets `--color-primary: #0000FF`
- WHEN the browser resolves `--color-primary`
- THEN the resolved value MUST be `#0000FF` (custom override wins)

#### Scenario: Missing file does not break stack
@e2e exclude a browser cannot make the overrides file absent on an instance that has saved overrides. Since the runtime files left the app directory (#811) rendering never creates the file. PHPUnit tests/Unit/Service/CssInjectionServiceTest.php::testRenderingWritesNothingAndLaterLayersStillRun renders with no saved overrides and asserts no file is created and every later layer still loads; tests/Unit/Service/CssInjectionServiceTest.php::testNoSavedOverridesIsNotAWarning asserts nothing is logged for it.
- GIVEN `custom-overrides.css` does not exist on disk
- WHEN Nextcloud loads the CSS stack
- THEN the remaining CSS layers MUST apply normally
- AND no PHP error or missing-file warning MUST appear in logs

### Requirement: File Format
`custom-overrides.css` MUST open with the header comment and one `:root` block holding the custom property declarations the admin set, one per line. `OverridesCssBuilder::declarationLines()` (the former `CustomOverridesService::buildDeclarationLines()`) MUST write every editor token with `!important`. Load order alone does not win: the nldesign design-system stylesheets re-declare every editable token with `!important`, and Nextcloud core theming sets variables such as `--color-primary` itself. The file only holds tokens the admin set, so this does not put `!important` on the whole registry. The administrator's own tokens follow the editor's lines without `!important`, because nothing else declares them.

When a brand-group colour token is set, the file MUST also carry its dark value, with `!important`, in two dark scopes after the `:root` block: a `@media (prefers-color-scheme: dark)` block scoped to a `body` with no chosen theme, and a `body[data-theme-dark], body[data-themes*=dark]` block. A user who chose the dark theme gets Nextcloud's dark colours declared on `body`, which a `:root` value never reaches. The dark value is derived by `DarkPaletteService`, or is the admin's own dark value when one was given; a settable Nextcloud variable gets a dark line only from the admin's own value. PHPUnit tests/Unit/Service/CustomOverridesServiceDarkScopesTest.php::testDarkScopesWritten asserts both scopes.

#### Scenario: File is written by the save endpoint
- GIVEN the admin saves token overrides via the editor
- WHEN the backend writes `custom-overrides.css`
- THEN the file MUST match the format:
  ```css
  /* NL Design — custom token overrides. Generated by theme editor. Do not edit manually. */
  :root {
    --color-primary: #c00000 !important;
    --color-error: #b30000 !important;
  }
  ```
- AND the file MUST contain only tokens that differ from the resolved default
- AND each token MUST appear on its own line, ending in ` !important;`
- AND a posted brand-group colour MUST also get its dark value in the two dark scopes the file format requirement names, below the `:root` block

#### Scenario: No custom tokens results in empty overrides block
- GIVEN the admin saves with all tokens reset to default
- WHEN the backend writes `custom-overrides.css`
- THEN the file MUST contain only the header comment and an empty `:root {}` block
- AND the file MUST NOT be deleted (absence vs empty file has different semantics)

### Requirement: Read/Write PHP Endpoint
The backend MUST expose a PHP service that reads the current `custom-overrides.css` and writes a new version atomically. Direct file manipulation from Vue components MUST NOT be used.

The overrides endpoint remains the only way the client writes `custom-overrides.css`, and the
token editor remains its only caller. The component playground MUST reach it through that
editor's own rows and its own Save rather than through a path of its own, so there is one
implementation of "what an edit is" and it cannot disagree with itself.

#### Scenario: Read current overrides
- GIVEN `custom-overrides.css` exists with some overrides
- WHEN the admin settings panel loads
- THEN a GET request to `/settings/overrides` MUST return the list of currently overridden token names and values as JSON
- AND the response MUST include only tokens present in `custom-overrides.css` (not defaults or resolved values)

#### Scenario: Write new overrides
- GIVEN the admin clicks Save with a new set of token values
- WHEN a POST request is made to `/settings/overrides` with the token map
- THEN the backend MUST validate each token name against the editable token registry
- AND it MUST write the validated tokens to `custom-overrides.css` atomically (write to temp file, rename)
- AND it MUST return HTTP 200 with the final set of written tokens

#### Scenario: Write fails due to filesystem permissions
- GIVEN the CSS directory is not writable by the web server process
- WHEN the save endpoint is called
- THEN the server MUST return HTTP 500
- AND the error response MUST include a message indicating the file could not be written
- AND the existing `custom-overrides.css` MUST remain unchanged

#### Scenario: The playground saves through the editor, not beside it
- GIVEN an edit made under a component in the playground
- WHEN the admin saves
- THEN the request MUST be the one the token editor would have sent for the same edit
- AND the playground MUST NOT issue a write of its own

#### Scenario: An edit under a component is an edit in the editor
- GIVEN a component open in the playground
- WHEN one of its tokens is edited
- THEN the token editor MUST report an unsaved change, exactly as if the value had been typed
  in the full list

### Requirement: No Database Storage
Token overrides MUST NOT be stored in Nextcloud's `appconfig` table or any database table. The CSS file is the sole persistence mechanism.

#### Scenario: Token overrides survive app reinstall if CSS directory is preserved
@e2e exclude disabling thematiq from the suite takes down the endpoints every test restores state through; PHPUnit tests/Unit/Service/CustomOverridesServicePerSetTest.php::testOverridesLiveInTheFileAndNeverInAppConfig asserts the overrides live in the file alone and never in app config
- GIVEN `custom-overrides.css` exists in the app's CSS directory
- WHEN the nldesign app is disabled and re-enabled
- THEN `custom-overrides.css` MUST still apply after re-enable
- AND no database query MUST be required to restore custom tokens

#### Scenario: Resetting to defaults requires only file deletion
- GIVEN an admin wants to remove all custom overrides
- WHEN they delete `custom-overrides.css` (or use a "Reset all" action that empties it)
- THEN the CSS stack MUST revert entirely to the NL Design token set and Nextcloud defaults
- AND no database record needs to be cleared
