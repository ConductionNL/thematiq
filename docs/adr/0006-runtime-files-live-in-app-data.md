# 0006. Files written at runtime live in app data, not the app directory

- Status: Accepted

## Context

The app wrote admin overrides, custom CSS, uploaded token sets, their dark variants, logos and backgrounds into its own app directory. Nextcloud's code integrity check correctly reported those files, and a read-only app directory broke the features.

## Decision

- One store over Nextcloud `IAppData` holds every runtime-written file, with a closed set of allowed names.
- One locator returns a set's stylesheet, dark stylesheet and logo from the release for shipped ids and from the store otherwise; a shipped id can never be overridden from app data.
- Stored files are served from a public, rate-limited route with immutable caching and a revision in the URL.
- Dark variants of shipped sets are build output, checked by a test, never rewritten at runtime.
- A repair step moves existing runtime files into the store and removes them from the app directory.

## Consequences

- The app directory stays byte-identical after any admin action, so the integrity check stays clean and a read-only install works.
- Code must ask the locator for set files instead of building paths under the app directory. The implementation is in `lib/Service/RuntimeFile/` and `lib/Repair/MoveRuntimeFilesToAppData.php`.

## Source

`openspec/changes/archive/2026-10-02-runtime-files-in-appdata/design.md` (Decisions).
