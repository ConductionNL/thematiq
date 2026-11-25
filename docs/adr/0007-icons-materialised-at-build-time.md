# 0007. Icon packs are materialised as SVG files at build time

- Status: Accepted

## Context

The NL government icon packs ship in `@conduction/nextcloud-vue` as JavaScript modules of data URIs. Thematiq's public contract for icons is plain SVG files served from the app directory, used by PHP templates, `img` tags and CSS alike.

## Decision

- A build script decodes the packs and writes each icon to `img/icons/<set>/<key>.svg`; the files are committed and ship in the release.
- Icons keep a per-set folder layout so overlapping names across packs do not collide.
- `@conduction/nextcloud-vue` stays a devDependency used only by the build script; nothing under `js/` or `lib/` imports it.

## Consequences

- No runtime coupling, controller or route is needed for icons, and the release is self-contained. Regenerate with `npm run build:icons` (`scripts/build-icons.js`).
- Updating icons means updating the devDependency and rebuilding.

## Source

`openspec/changes/archive/2026-07-24-icons-from-ncvue/design.md` (Decisions 1 and 2).
