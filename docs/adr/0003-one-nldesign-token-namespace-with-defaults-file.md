# 0003. One token namespace with a separate defaults file

- Status: Accepted

## Context

Component tokens arrive from upstream under other prefixes, such as `--utrecht-*`, which the NL Design System plans to change. Every token also needs a value when an organisation set is incomplete.

## Decision

- Component tokens are exposed under one `--nldesign-component-*` prefix. A bridge stylesheet translates upstream prefixes into it internally.
- Default values for all tokens live in a dedicated `defaults.css` that is loaded first, instead of inline `var()` fallbacks repeated in every file.

## Consequences

- Consumers only need to know the `--nldesign-*` prefix. These names belong to the NL Design System and are not renamed with the app id (see the comment in `appinfo/info.xml`).
- Every token always has a value, and fallback values are kept in one place per design system (for example `css/systems/nldesign/defaults.css`).

## Source

`openspec/changes/archive/2026-03-06-full-nextcloud-token-support-from-nldesign/design.md` (Decisions 4 and 5).
