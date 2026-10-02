## Context

See proposal.md for why. What the code does today, read on 2 October 2026:

- Five services write inside the app directory: `CustomOverridesService` (`css/custom-overrides*.css`), `CustomCssService` (`css/custom-css.css`), `CustomTokenSetService` (`css/tokens/{id}.css`, `img/logos/`), `DarkPaletteService` (`css/tokens/dark/{id}.css`) and `BrandingCaptureService` (`img/logos/`, `img/backgrounds/`).
- About twenty services read a set's stylesheet by path from `getAppPath('thematiq')`, among them `TokenSetService`, `DarkPaletteService`, `ComplianceReportService`, `TokenSetConverterService`, `TokenReferenceService`, the two audit services, `StockTokensService`, `DesignSystemService` and `CssParserService`.
- `CssInjectionService` builds an ordered manifest of layers, each `kind: 'file'` (an app stylesheet emitted with `Util::addStyle()`) or inline. `logoUrlLayer()` re-declares the active set's logo as an absolute URL.
- `FontService` already stores uploads in `IAppData` and `FontController::css()` serves a stylesheet from a public, rate-limited route with an immutable cache and a `?v=<revision>` URL. That is the pattern to copy.
- A dark file counts as fresh when its header records its source's hash. A shipped set's committed dark file is rewritten whenever the generator changes or regeneration is forced.

## Goals / Non-Goals

**Goals:**
- The app directory is byte-identical before and after any admin action.
- Every feature that writes a file keeps working on a read-only app directory.

**Non-Goals:**
- Moving shipped files. Shipped token sets, logos and dark variants stay in the release and are only read.
- Changing what any stylesheet contains. Only where it lives and how it is linked changes.

## Decisions

### One store: `RuntimeFileStore`

A small service over `IAppData` with `read`, `write`, `exists`, `delete`, `revision` and `url`. Names are a closed set of patterns: `overrides/custom-overrides.css`, `overrides/custom-overrides-{set}.css`, `custom-css.css`, `tokens/{id}.css`, `tokens/dark/{id}.css`, `img/logos/{id}.{ext}`, `img/backgrounds/{id}.{ext}`, with `{id}` restricted to the slug pattern the custom set validator already enforces. Anything else is refused. That one check is what keeps the public route safe.

Each write bumps a revision stored in app config, so a linked URL changes when its content does.

### One locator for set files: `TokenSetFileLocator`

`stylesheet(id)`, `darkStylesheet(id)` and `logo(id)` return the content or URL from the release when the id is shipped, and from the store otherwise. Every reader switches from building a path to asking the locator. A shipped id can never be overridden from app data, so an upload cannot replace a shipped set's file.

Alternative considered: keep paths and point them at the app-data folder on disk. Rejected. `IAppData` is not guaranteed to be a local directory, for example with object storage as primary storage, so a path is the wrong abstraction.

### Serving: two public routes

`/runtime/css/{name}` and `/runtime/img/{name}`, `#[PublicPage]`, `#[NoCSRFRequired]` and rate-limited like the font route. They return the stored file with `Cache-Control: public, max-age=31536000, immutable` and its revision as ETag. They must be public: the login page and public share pages are themed too.

### Injection: a third layer kind

The layer manifest gains `kind: 'runtime'`, emitted as a `<link rel="stylesheet">` header to the route with `?v=<revision>`, the way `injectCustomFontLink()` already emits fonts. Shipped layers keep `addStyle()`. `logoUrlLayer()` asks the locator, so an uploaded logo resolves to the route URL.

### Shipped dark variants are build output

The repair step and the `occ` command generate dark variants for uploaded sets only, into the store. A new vitest case reads every shipped set and its committed dark file and fails when the recorded source hash no longer matches. That makes a stale shipped dark file a red build instead of a runtime write.

### The repair step moves, then removes

`MoveRuntimeFilesToAppData` runs on install and post-migration. For every file in the app directory that matches a runtime pattern and is not in `appinfo/signature.json` (or, without a signature, is not tracked by the release's file list), it copies the file into the store unless the store already holds that name, then deletes the app-directory copy. It logs one line per file. A failed delete on a read-only directory is logged and does not fail the upgrade.

## Risks / Trade-offs

- [A reader is missed and still builds a path] → A PHPUnit guard greps `lib/` for `getAppPath(` next to `css/tokens/`, `img/logos`, `img/backgrounds` or `custom-overrides` and fails on any hit outside the locator and the repair step.
- [An extra request per page for the overrides stylesheet] → It is cached immutably and only linked when something is stored, as today.
- [Object storage latency on the first request] → The response is cached by the browser for a year per revision; the route reads app data once per revision.
- [A delete during the move fails on a read-only app directory] → The store copy wins, so behaviour is correct; the integrity warning stays until an admin removes the file, and the log says which.

## Migration Plan

Upgrade runs the repair step, which moves existing files. Rollback to an older release restores code that reads the app directory, where the moved files no longer exist: an admin rolling back loses overrides until they save them again. Document this in the release notes.

## Seed Data

Not applicable. This change adds no OpenRegister schema.
