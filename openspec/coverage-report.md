# Coverage Report — nldesign

Generated: 2026-05-24 00:00 UTC
Branch: feature/i18n-complete-translations
Scanner: opsx-coverage-scan v1

## Summary

| Bucket | Count | Next action |
|---|---|---|
| annotated | 0 | — (already tagged) |
| plumbing | 16 | — (never tagged) |
| 1 — REQ matched | 57 | `/opsx-annotate nldesign` |
| 2a — existing capability, no REQ | 0 (0 clusters) | — |
| 2b — no capability owner | 0 (0 clusters) | — |
| 3a — REQ broken (code removed) | 1 | Separate fix PR |
| 3b — REQ never implemented | 28 | **Mostly false alarms** — see Notes |
| 4 — ADR conformance | 18 findings across 1 rule | `/opsx-annotate nldesign` (same PR can fix) |

**Per the skill format:**
`Buckets: annotated=0 | plumbing=16 | 1=57 | 2a=0/0 clusters | 2b=0/0 clusters | 3a=1 | 3b=28 | 4=18`

## Bucket 1 — Ready to annotate (via ghost change `retrofit-2026-05-24-annotate-nldesign`)

### capability: css-architecture → task-1

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/AppInfo/Application.php | boot | REQ-CSS-001 | 0.92 | Boot delegates to injectThemeCSS |
| lib/AppInfo/Application.php | injectThemeCSS | REQ-CSS-001/009, REQ-SLGN-002, REQ-MLBL-002, REQ-TSET-003 | 0.97 | Direct design-system bundle loading + custom-overrides + conditional CSS |
| lib/Service/DesignSystemService.php | getDesignSystems | REQ-CSS-011/012 | 0.95 | Reads design-systems.json with per-request cache |
| lib/Service/DesignSystemService.php | getDesignSystem | REQ-CSS-011 | 0.96 | Empty-stylesheets fallback for unknown systems |
| lib/Service/DesignSystemService.php | getTokenSetMeta | REQ-CSS-011 | 0.96 | Returns design_system field per scenario |
| lib/Service/DesignSystemService.php | getDesignSystemsList | REQ-CSS-011 | 0.72 | **NEEDS-REVIEW** — no internal caller found |
| lib/Service/DesignSystemService.php | readJsonManifest | REQ-CSS-011 + REQ-TSET-002 | 0.90 | Shared JSON parser with malformed-input tolerance |

### capability: prometheus-metrics → task-2

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/HealthController.php | index | REQ-PROM-009 | 0.95 | NoCSRFRequired + configuration check |
| lib/Controller/MetricsController.php | index | REQ-PROM-001/002/003/007/010 | 0.97 | Exposition format + content-type + info/up/theming_syncs |
| lib/Controller/MetricsController.php | collectTokenSetMetrics | REQ-PROM-004/005/008 | 0.95 | token_sets_total + active_token_set with fallback |
| lib/Controller/MetricsController.php | collectOverrideMetrics | REQ-PROM-006/008 | 0.95 | custom_overrides_total with fallback |

### capability: custom-css-overrides → task-3

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/OverridesController.php | getOverrides | Read current overrides | 0.93 | GET /settings/overrides — overrides+registry+tabs |
| lib/Controller/OverridesController.php | setOverrides | Write new overrides | 0.92 | POST /settings/overrides |
| lib/Controller/SettingsController.php | getOverrides | Read current overrides | 0.90 | Duplicate of OverridesController::getOverrides |
| lib/Controller/SettingsController.php | setOverrides | Write new overrides | 0.88 | Duplicate of OverridesController::setOverrides |
| lib/Service/CustomOverridesService.php | ensureExists | File does not exist on fresh install | 0.96 | Called by Application::injectThemeCSS |
| lib/Service/CustomOverridesService.php | read | Read current overrides | 0.96 | Returns only explicit overrides |
| lib/Service/CustomOverridesService.php | write | Write new overrides + No DB storage | 0.96 | filterEditable + atomic write |
| lib/Service/CustomOverridesService.php | filterEditable | Read/Write PHP Endpoint | 0.85 | TokenRegistry gate |
| lib/Service/CustomOverridesService.php | writeFile | Read/Write PHP Endpoint (atomic) | 0.95 | tmp+rename, RuntimeException on fail |
| lib/Service/CustomOverridesService.php | buildCss | File Format | 0.93 | Header + :root block exactly per spec |
| lib/Service/CustomOverridesService.php | buildDeclarationLines | File Format | 0.90 | Two-space indent + trailing semicolon |
| lib/Service/CustomOverridesService.php | parseDeclarations | Read current overrides | 0.85 | :root extraction regex |

### capability: token-import-export → task-4

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/OverridesController.php | exportOverrides | Export Current Overrides | 0.96 | text/css DataDownloadResponse |
| lib/Controller/OverridesController.php | importOverrides | Import Token File + Upload Endpoint | 0.96 | multipart, parses, filters via TokenRegistry |
| lib/Controller/OverridesController.php | validateUploadedFile | Import Validation (size) | 0.94 | 256 KB guard |
| lib/Controller/OverridesController.php | readUploadedContent | Upload Endpoint | 0.85 | private helper for importOverrides |
| lib/Controller/OverridesController.php | writeImportedTokens | Import Validation (unknown skipped) | 0.93 | imported/skipped counts |
| lib/Service/CssParserService.php | parseDeclarations | Import Validation (parse CSS) | 0.92 | Used by importOverrides |
| lib/Service/CssParserService.php | parseRootBlock | Import Validation | 0.78 | **NEEDS-REVIEW** — no caller in lib/ |
| lib/Service/CustomOverridesService.php | getRawContent | Export Current Overrides | 0.94 | Raw file content for download |

### capability: token-sets → task-5

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/SettingsController.php | setTokenSet | REQ-TSET-003/004/006 | 0.96 | POST /settings/tokenset |
| lib/Controller/SettingsController.php | getTokenSet | REQ-TSET-003/006 | 0.95 | GET /settings/tokenset |
| lib/Controller/SettingsController.php | getAvailableTokenSets | REQ-TSET-001/006 + REQ-ASET-002 | 0.96 | GET /settings/tokensets |
| lib/Controller/SettingsController.php | getTokenSetPreview | REQ-TSET-009 + apply-dialog comparison | 0.96 | GET /settings/tokenset-preview/{id} |
| lib/Service/TokenSetService.php | getAvailableTokenSets | REQ-TSET-001/002/008 + dropdown sort + extended discovery | 0.97 | FS scan + manifest merge + alphabetical sort |
| lib/Service/TokenSetService.php | isValidTokenSet | REQ-TSET-004 + validation | 0.97 | Path-traversal guards + file_exists |
| lib/Service/TokenSetService.php | readManifest | REQ-TSET-002 | 0.95 | Indexed by id, error tolerant |
| lib/Service/TokenSetService.php | formatName | REQ-TSET-001 (auto-generated names) | 0.92 | ucwords + str_replace |

### capability: token-set-apply-dialog → task-6

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Service/TokenSetPreviewService.php | getResolvedColors | Resolved Value Comparison | 0.95 | Server-side resolution pipeline |
| lib/Service/TokenSetPreviewService.php | parseCssVars | Resolved Value Comparison | 0.85 | Helper, called twice |
| lib/Service/TokenSetPreviewService.php | parseMappings | Resolved Value Comparison | 0.85 | Parses overrides.css mappings |
| lib/Service/TokenSetPreviewService.php | resolveVarReference | Resolved Value Comparison | 0.85 | One-level var() chase |

### capability: token-editor-ui → task-7

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Service/TokenRegistry.php | getTokens | Tab Groups + Excluded Token Registry | 0.97 | Canonical registry of all 4 tabs |
| lib/Service/TokenRegistry.php | getTabLabels | Tab Groups | 0.95 | Returns Login/Content/Status/Typography |
| lib/Service/TokenRegistry.php | isEditable | Excluded Token Registry + Write Validation | 0.97 | Gate used by both Overrides controllers |
| lib/Service/TokenRegistry.php | getTokenNames | Editable Token Input | 0.70 | **NEEDS-REVIEW** — no internal caller |
| lib/Service/TokenRegistry.php | getTokensByTab | Tab Groups | 0.70 | **NEEDS-REVIEW** — no internal caller |

### capability: theming-sync → task-8

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/SettingsController.php | updateThemingValues | REQ-SYNC-007/009 + dialog Update Endpoint | 0.97 | Colors-then-images validation order |
| lib/Controller/SettingsController.php | getThemingValues | REQ-SYNC-002 + dialog Get Endpoint | 0.96 | Via buildThemingSnapshot |
| lib/Controller/SettingsController.php | buildThemingSnapshot | REQ-SYNC-002 | 0.92 | Scenario names this exact method |
| lib/Service/ThemingService.php | isValidHexColor | REQ-SYNC-003 | 0.96 | Hex regex matches spec exactly |
| lib/Service/ThemingService.php | validateColors | REQ-SYNC-003 | 0.96 | Iterates primary_color + background_color |
| lib/Service/ThemingService.php | validateImagePaths | REQ-SYNC-004 | 0.96 | Spec lists exact validation rules |
| lib/Service/ThemingService.php | validateSinglePath | REQ-SYNC-004 | 0.95 | Path traversal + allowed-dir + file existence |
| lib/Service/ThemingService.php | applyColors | REQ-SYNC-005 | 0.96 | ThemingDefaults::set per spec |
| lib/Service/ThemingService.php | applyImages | REQ-SYNC-006 | 0.96 | ImageManager::updateImage with IAppManager path |

### capability: hide-slogan → task-9

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/SettingsController.php | setSloganSetting | REQ-SLGN-001/005/006 | 0.97 | POST /settings/slogan exactly named |
| lib/Controller/SettingsController.php | saveBooleanSetting | REQ-SLGN-005 + REQ-MLBL-001 | 0.95 | Shared strict-true → '1'/'0' helper |

### capability: menu-labels → task-10

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/SettingsController.php | setMenuLabelsSetting | REQ-MLBL-001 | 0.97 | POST /settings/menulabels exactly named |

### capability: admin-settings → task-11

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Settings/Admin.php | getForm | REQ-ASET-002 + REQ-ASET-001 | 0.97 | TemplateResponse with all 4 required params |
| lib/Settings/Admin.php | getSection | REQ-ASET-001 (theming section) | 0.97 | Returns 'theming' |
| lib/Settings/Admin.php | getPriority | REQ-ASET-001 (priority 50) | 0.97 | Returns 50 |

## Bucket 2a — Existing capability, no REQ (reverse-spec --extend)

_None._ Every code unit cleanly maps to an existing capability REQ.

## Bucket 2b — No capability owner (reverse-spec --cluster)

_None._ All code units belong to an established capability directory.

## Bucket 3 — Surfaced for human triage

### 3a — possibly broken

- **prometheus-metrics#REQ-PROM-007 (`theming_syncs_total` counter increment)** — The metric is READ by `MetricsController::index` (line 88) but is **never INCREMENTED** anywhere in `lib/`. The removed-lines cache matched 4 hits for `theming_syncs_total`, suggesting the counter write may have been lost in a past refactor. `SettingsController::updateThemingValues` does NOT call `setAppValue` for this key. The metric will perpetually report `0`. **Recommend small follow-up PR** to add the increment.

### 3b — never implemented (mostly false alarms, see notes)

**Legitimate false alarms** (REQ describes CSS/template/JS/script/CI behaviour outside the scanner's `lib/` + `src/` scope):

- `admin-settings#REQ-ASET-003..012` (11 REQs) — implemented in `templates/settings/admin.php` + `js/admin.js`
- `admin-settings#REQ-ASET-009` (admin-only access) — implemented via `@AuthorizedAdminSetting` annotations (annotation-level, not method)
- `component-tokens` (entire spec — 4 REQs) — pure CSS, in `css/systems/nldesign/utrecht-bridge.css` + `defaults.css`
- `css-architecture#REQ-CSS-002..010` (9 REQs) — pure CSS layer behaviour in `css/systems/nldesign/*.css`
- `docs-content` (entire spec — 7 REQs) — markdown + Docusaurus under `docs/` + `docusaurus/`
- `extended-token-sets` Auto-Generated CSS + Manifest auto-update — implemented in `scripts/generate-tokens.mjs`
- `hide-slogan#REQ-SLGN-002/003/004/007/009` — CSS behaviour in `css/hide-slogan.css` (compliance assertion for REQ-SLGN-009)
- `menu-labels#REQ-MLBL-002..007/009/010/011` — CSS behaviour in `css/show-menu-labels.css`
- `nextcloud-variable-mapping` (entire spec — 5 REQs) — `css/systems/nldesign/overrides.css` + `defaults.css` + `docs/reference/mappings.md`
- `nl-design` delta spec — composite of token-sets PHP + CSS
- `prometheus-metrics#REQ-PROM-011` (DI dependencies) — constructor signature (plumbing-bucketed)
- `theming-sync-dialog` Confirmation Dialog + Preview Boxes + User Actions + Bundled Images — implemented in `js/admin.js` (confirmed: `checkAndShowThemingDialog` line 142, `showThemingDialog` line 201)
- `theming-sync#REQ-SYNC-008` (DI) + `REQ-SYNC-010` (frontend dialog) + `REQ-SYNC-011` (routes)
- `token-editor-ui` Panel + Editable Input + Live Preview + Save + Per-Token Reset — UI rendering in `js/admin.js` (`saveOverrides` line 665)
- `token-import-export` Import Result Feedback — UI message rendering in `js/admin.js`
- `token-set-apply-dialog` Dialog Trigger + Checkbox Selection + Live Preview + Apply Action + Applied Together With Overrides — UI in `js/admin.js` (`showApplyDialog` line 786)
- `token-set-dropdown` (entire spec — 3 REQs) — `<select>` rendering in `templates/settings/admin.php`; sort logic IS covered by PHP
- `token-sets#REQ-TSET-005` (CSS file structure) + `REQ-TSET-007` (filesystem count) + `REQ-TSET-010` (DI) + `REQ-TSET-011` (routes)
- `token-sync-workflow` (entire spec — 5 REQs) — `.github/workflows/sync-tokens.yml` + `scripts/generate-tokens.mjs` + README
- `vng-token-set` (entire spec — 7 REQs) — `css/tokens/vng.css` + `token-sets.json` (manifest exposure IS covered by `TokenSetService`)

## Bucket 4 — ADR conformance findings

### missing-spec-in-file-docblock (18 files)

All 18 PHP files in `lib/` lack `@spec openspec/changes/...` tags in their file docblocks. This is the expected state for a pre-retrofit codebase. `/opsx-annotate nldesign` would create the ghost change and apply tags in one PR.

Files:
- lib/AppInfo/Application.php
- lib/Controller/HealthController.php
- lib/Controller/MetricsController.php
- lib/Controller/OverridesController.php
- lib/Controller/SettingsController.php
- lib/Service/CssParserService.php
- lib/Service/CustomOverridesService.php
- lib/Service/DesignSystemService.php
- lib/Service/ThemingService.php
- lib/Service/TokenRegistry.php
- lib/Service/TokenRegistryInterface.php
- lib/Service/TokenSetPreviewService.php
- lib/Service/TokenSetService.php
- lib/Settings/Admin.php

### Other ADR conformance

- `@license` + `@copyright` (PHPDoc style): **present** on all 18 files (header docblocks include `@license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0-or-later` and `@author Conduction <info@conduction.nl>`).
- Forbidden debug patterns: **none found** (no `var_dump`, `dd(`, `die(`, `print_r(`, `error_log(` outside tests).
- Hardcoded user-facing strings not wrapped in `t()` / `$this->l10n->t()`: **not flagged** — JSON responses use API field names, not user-facing copy; user-facing strings live in `templates/` + `js/admin.js`.
- Direct SQL (`$this->db->query`, `prepare`): **none found** — nldesign has no DB tables and uses only `IConfig`.

## Notes for the human reviewer

1. **Scanner scope mismatch.** nldesign is a CSS-only theming app with PHP backend, vanilla PHP admin template (`templates/settings/admin.php`), and vanilla JS admin script (`js/admin.js` — 965 lines). The skill enumerates `lib/**/*.php` + `src/**/*.{vue,ts,js}`, but nldesign has **no `src/` directory** — frontend lives at `js/admin.js`. As a result, dozens of frontend-bound REQs (apply dialog, theming dialog, token editor UI, import/export UI, dropdown rendering, all 11 admin-settings template REQs) flag as Bucket 3b **false alarms**. Grep confirmed every flagged frontend handler exists in `js/admin.js`:
   - `checkAndShowThemingDialog` line 142
   - `showThemingDialog` line 201
   - `showApplyDialog` line 786
   - `saveOverrides` line 665
   - `exportOverrides` line 710
   - `importOverrides` line 719
   - `tokenset-preview` fetch line 755

   **Suggestion**: for CSS-only theming apps, the scanner should optionally enumerate `templates/**/*.php` + `js/**/*.{js,mjs}` + `css/**/*.css` (the last with very limited classification).

2. **Single REAL Bucket 3a finding.** `nldesign_theming_syncs_total` counter is read but never incremented. Small follow-up PR territory — add `$this->config->setAppValue('nldesign', 'theming_syncs_total', ...)` after a successful `updateThemingValues`.

3. **Duplicate Overrides handlers.** `SettingsController::getOverrides` + `setOverrides` are functional duplicates of `OverridesController::getOverrides` + `setOverrides`. Both routes are registered (`settings#getOverrides` and `overrides#exportOverrides/importOverrides`). Worth consolidating to one controller — annotate both for now; refactor later.

4. **NEEDS-REVIEW Bucket 1 entries** (4 methods): `CssParserService::parseRootBlock`, `DesignSystemService::getDesignSystemsList`, `TokenRegistry::getTokenNames`, `TokenRegistry::getTokensByTab`. All public helpers with no internal caller in `lib/`. Possibly dead public API or anticipated future endpoint surface. Annotate to the closest REQ and flag for a separate dead-code sweep.

5. **Status frontmatter.** All 20 specs carry `status: implemented`. No `redirect` / `deprecated` / `moved` specs found. Several specs (admin-settings, css-architecture, hide-slogan, menu-labels, prometheus-metrics, theming-sync, token-sets) also carry `reviewed_date` + `enriched_date` plus detailed `Current Implementation Status` sections — exceptionally well-maintained spec corpus.

6. **No in-flight changes.** `openspec/changes/` contains only `archive/`. Bucket extraction was scoped to `openspec/specs/` only.

7. **Branch oddity.** Working branch is `feature/i18n-complete-translations`, not `development`. Specs are present on this branch — no need to switch.
