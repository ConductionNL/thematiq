# 0004. Upstream tokens are synced in Node.js through reviewed pull requests

- Status: Accepted

## Context

Organisation tokens come from the `nl-design-system/themes` repository, which builds with style-dictionary (JavaScript). Upstream changes can contain breaking changes, renamed tokens or errors.

## Decision

- Token generation is written in Node.js, not Python, so it can reuse the upstream JavaScript tooling and the app's existing npm setup.
- The sync opens a pull request for review instead of committing directly.

## Consequences

- Upstream token changes get a review step and an audit trail before they reach users. The workflow is `.github/workflows/sync-tokens.yml`; the scripts are in `scripts/` (for example `scripts/sync-upstream-tokens.mjs`).
- The token tooling needs Node.js only, alongside the PHP app.

## Source

`openspec/changes/archive/2026-03-06-full-nextcloud-token-support-from-nldesign/design.md` (Decisions 1 and 3).
