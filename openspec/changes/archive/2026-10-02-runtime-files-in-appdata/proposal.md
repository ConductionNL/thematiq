---
kind: code
depends_on:
  - nc-variable-inventory
---

## Why

An admin who uploads their own house style, with their own logo and background, gets a code integrity warning in Nextcloud's admin overview. A user reported it on 2 October 2026.

The warning is correct. thematiq writes into its own app directory at runtime, and Nextcloud's integrity check compares that directory with the signature in the release (`appinfo/signature.json`, 3,027 hashed files in 1.2.10):

| Written at runtime | Where | What the check reports |
|---|---|---|
| Admin token overrides | `css/custom-overrides.css`, `css/custom-overrides-{set}.css` | extra file, on every instance that renders a themed page, because `ensureExists()` creates it |
| Freeform custom CSS | `css/custom-css.css` | extra file |
| An uploaded token set | `css/tokens/{id}.css` | extra file |
| Its dark variant | `css/tokens/dark/{id}.css` | extra file |
| Its logo and background | `img/logos/`, `img/backgrounds/` | extra file |
| A shipped set's dark variant, regenerated | `css/tokens/dark/{shipped}.css` | invalid hash |

There is a second cost the warning hides. An app update replaces the app directory, so every one of those files can be lost on upgrade. The code already says where they belong: `CssInjectionService` logs "generated CSS belongs in appdata (see nldesign#264)".

## What Changes

- **thematiq never writes inside its app directory at runtime.** Install, upgrade, a request and an `occ` command all leave the directory exactly as the release shipped it.
- **One store for runtime files.** Overrides, custom CSS, uploaded sets, their dark variants, logos and backgrounds live in Nextcloud's app data for thematiq, the place uploaded fonts and the audit log already use.
- **Served by route, cached by revision.** Stylesheets and images come from two public, rate-limited routes, with a `?v=<revision>` query so a change is a new URL, the same contract the font route has.
- **Reads go through one locator.** Every service that reads a set's stylesheet asks one locator, which returns a shipped file from the app directory or an uploaded one from app data.
- **Shipped dark variants are build output.** They are generated at build time and committed. A test fails when one is stale, so no install ever needs to rewrite them.
- **Existing installs are moved.** A repair step copies every runtime file it finds in the app directory into app data, then removes the copy from the app directory, so the warning clears on the next check.

## Capabilities

### New Capabilities

- `runtime-file-storage`: where thematiq keeps what it writes at runtime, how those files are served, and the promise that the app directory stays as shipped.

### Modified Capabilities

None at requirement level. `custom-overrides`, `custom-token-sets`, `dark-mode` and `custom-css-overrides` keep their behaviour; only the storage behind them moves, and their specs name no paths a reader depends on.

## Impact

- New: `lib/Service/RuntimeFileStore.php`, `lib/Service/TokenSetFileLocator.php`, `lib/Controller/RuntimeFileController.php`, two routes, `lib/Repair/MoveRuntimeFilesToAppData.php`.
- Changed: `CustomOverridesService`, `CustomCssService`, `CustomTokenSetService`, `DarkPaletteService`, `BrandingCaptureService`, `CssInjectionService`, and every reader of a set's file (about 20 services, listed in design.md).
- Deployments with a read-only app directory (nldesign#264) get every feature that depends on these files, for the first time.
- Other Conduction apps are unaffected.
