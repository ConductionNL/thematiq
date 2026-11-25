# 0001. Design systems are declared stylesheet bundles

- Status: Accepted

## Context

Thematiq themes Nextcloud through a stack of CSS layers: fonts, token defaults, organisation tokens, bridges, element mappings and the admin's own overrides. More than one design system has to be supported, and stock Nextcloud must stay available as a choice.

## Decision

- `design-systems.json` is the single source of truth for which stylesheets a design system loads and in which order.
- The `none` design system loads no stylesheets, so Nextcloud looks stock.
- Custom overrides are always loaded last; conditional CSS (such as hide-slogan and show-menu-labels) is loaded after them.

## Consequences

- Adding or reordering a layer is a change to `design-systems.json`, not to PHP code. `lib/Service/DesignSystemService.php` reads the file; the stylesheets live in `css/systems/`.
- The admin's own overrides always win over the shipped layers.

## Source

`openspec/changes/archive/2026-03-21-css-architecture/design.md` (Decisions 1 to 4).
