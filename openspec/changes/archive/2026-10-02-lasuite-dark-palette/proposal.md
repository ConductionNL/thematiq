---
kind: code
---

## Why

The `lasuite` design system has **no dark palette at all**, and its element
overrides pin light values with `!important` — so on an instance in dark mode
the theme forces a light interface anyway.

This was found while auditing the parity work of 2026-07-28/29. It is not a
regression introduced by that work; it is a gap the work made larger, because
several new rules were written against the same light-only ramp.

### The evidence

1. **No dark scope exists in the system.** No file under
   `css/systems/lasuite/` contains `prefers-color-scheme` or
   `data-themes*=dark`. Every rule there applies unconditionally.

2. **The dark variant file does not touch the ramp the overrides use.**
   `css/tokens/dark/lasuite.css` redefines 44 custom properties, and all of them
   are `--nldesign-*`. Not one `--lasuite-color-*` token is redefined. Checked
   individually: `gray-000`, `gray-025`, `gray-050`, `gray-100`, `gray-500`,
   `gray-850`, `brand-550` and `brand-050` all keep their light values in the
   dark variant.

3. **The overrides consume exactly those tokens, with `!important`.** So the
   dark variant's `--nldesign-color-header-background` is computed and then
   discarded, because `element-overrides.css` sets
   `#header { background: var(--lasuite-color-gray-000) !important }` — white,
   in dark mode.

The visible consequences, all following from the same cause:

| Surface | Dark-mode result |
|---|---|
| Header | `gray-000` → white bar |
| Content canvas | `gray-025` → near-white ground |
| Content card | `gray-000` → white card |
| Search field | `gray-025` fill, `gray-100` hairline — light on light |
| Active row wash | `rgba(27,27,35,.05)` — a dark wash designed for a light surface; near-invisible on a dark one |
| Logo | masked to `brand-550`; readable, but on a bar that should not be white |

### Why the existing test suite did not catch it

`tests/e2e/spec-coverage/dark-mode.spec.ts` asserts that the dark stylesheet is
**injected in the right order** and is **dual-scoped**, and that the admin toggle
reflects persisted state. It never renders a page in dark mode and contains zero
references to `#header`, `#content`, the logo or any shell element. The
stylesheet is correctly ordered and correctly scoped — and has no effect on this
design system. The suite is measuring plumbing, not outcome.

## What Changes

- Author a **dark ramp for the `--lasuite-color-*` tokens**, sourced the same way
  the light ramp was: from La Suite's own shipped Cunningham dark palette rather
  than by mechanically inverting the light values. La Suite ships a dark theme;
  its values are the reference, exactly as they were for the light ramp.
- Emit them into `css/tokens/dark/lasuite.css` under the **existing** dark scope
  selectors, so no new scoping mechanism is introduced and the ordering the
  current spec already guarantees continues to hold.
- Re-express the **translucent** values in `element-overrides.css` so they work
  on both grounds. The active-row wash is the clear case: `rgba(27,27,35,.05)`
  is a dark wash for a light surface and cannot simply be reused.
- Extend the dark-mode e2e spec to **render** and assert computed values on the
  shell — header, canvas, card, active row, search field — instead of only
  checking injection order.

Explicitly **not** in scope: changing the light palette, the reserved
REQ-CSS-007 variables, or the `nldesign` / `summer-breeze` / `high-contrast`
systems.

## Capabilities

### Modified Capabilities

- `dark-mode`: gains the requirement that a design system's own token ramp — not
  merely the `--nldesign-*` layer — must have a dark counterpart when its
  element overrides consume that ramp directly, plus rendered assertions rather
  than injection-order assertions alone.
- `lasuite-stack`: gains a dark palette.

## Impact

- **Visual**: instances in dark mode currently get a light interface under this
  theme. After the change they get a dark one — a large, intended, visible
  change for anyone already running `lasuite` with dark mode on.
- **Risk**: the reserved REQ-CSS-007 variables are what Nextcloud's own dark
  derivation depends on. This change must not touch them; it adds a dark ramp
  for the theme's OWN tokens and nothing else.
- **Accessibility**: the dark ramp has to clear the same WCAG loop the existing
  `dark-mode` spec already requires for generated variants, so contrast is
  verified rather than assumed.
