# 0002. Token sets are data, discovered from files and a manifest

- Status: Accepted

## Context

The app ships dozens of organisation token sets and the number keeps growing. A hardcoded PHP array of sets did not scale and needed a code change for every new organisation.

## Decision

- Available token sets are discovered by scanning `css/tokens/` for CSS files.
- Display metadata (name, description, primary colour) comes from the `token-sets.json` manifest, indexed by id.
- A set's `design_system` field decides which CSS stack it loads.
- Set ids are validated against path traversal before any file is read.

## Consequences

- Adding an organisation means adding `css/tokens/{id}.css` and a `token-sets.json` entry, with no PHP change (see `docs/features/token-sets.md`).
- The manifest and the CSS files must stay in step; the token-set tests and `scripts/token-set-gate.sh` check them.

## Source

`openspec/changes/archive/2026-03-21-token-sets/design.md` (Decisions 1 to 4) and `openspec/changes/archive/2026-03-06-full-nextcloud-token-support-from-nldesign/design.md` (Decision 2).
