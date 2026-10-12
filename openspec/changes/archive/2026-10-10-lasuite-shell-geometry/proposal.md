---
kind: code
---

## Why

Issue #182. The `lasuite` set matches La Suite's components and colours, and the
`lasuite-wireframe-alignment` change made the chrome flat and flush. The shell still reads as
Nextcloud in one place: the header row. La Suite Docs draws a 64px row; Nextcloud 35 draws 44px
(Nextcloud 32 to 34 draw 50px). Two shared La Suite overrides also hard-code Nextcloud 34's 50px,
so on Nextcloud 35 the content shell opens a 6px band under the header and the search field sits
off-centre.

The issue also asks for a version-scoped stylesheet, so a point release that reshapes the header
cannot silently break the shell.

## What Changes

- **Version-scoped stylesheets.** `design-systems.json` entries gain an optional
  `versioned_stylesheets` map, keyed by Nextcloud major. `DesignSystemService::getDesignSystem()` appends the entries for the running major to the system's `stylesheets`, so `CssInjectionService` emits them unchanged
  for the running major right after the design system's own stylesheets, and the stylesheet
  manifest carries them, so apply-without-reload swaps them too.
- **La Suite shell layer, Nextcloud 35 only.** `css/systems/lasuite/shell-nc35.css` sets
  `--header-height: 64px` (La Suite Docs' `HEADER_ROW_MIN_HEIGHT`). Only the `lasuite` bundle
  declares it; `cunningham` does not.
- **Shared overrides follow the header height.** `element-overrides.css` clears the header with
  `var(--header-height)` instead of 50px and centres the 34px search field with
  `calc((var(--header-height) - 34px) / 2)`. This also fixes the 6px band on Nextcloud 35 for
  both `lasuite` and `cunningham`.

**Measured, and already equal, so not changed:** the app navigation is 300px in both; the main area
spans the remaining width with no max-width in both; the spacing grid is 4px in both
(`--default-grid-baseline`, Cunningham `st`).

**Out of scope:** Playwright screenshot baselines per Nextcloud release. They need a live instance
per major to record; the geometry e2e spec added here asserts the measured numbers instead, and the
baselines are owed as a follow-up.

## Capabilities

### Modified Capabilities
- `lasuite-stack`: adds the shell geometry layer and the version-scoped stylesheet mechanism.

## Impact

`design-systems.json`, `lib/Service/CssInjectionService.php`, `css/systems/lasuite/shell-nc35.css` (new), `css/systems/lasuite/element-overrides.css`,
`tests/Unit/Service/LasuiteShellGeometryTest.php` (new), `tests/e2e/spec-coverage/lasuite-shell-geometry.spec.ts` (new).
