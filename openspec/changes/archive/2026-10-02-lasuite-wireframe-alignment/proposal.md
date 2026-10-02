---
kind: code
---

## Why

Colour parity for the `lasuite` design system already shipped (brand-override violet, bridge
coverage, Cunningham token base). Live measurement of La Suite Docs (localhost:3000) and La Suite
Messages (localhost:8900) against the current `lasuite`-themed Nextcloud (localhost:8080) at
1280x720 shows the *layout* still reads as a different product: the top navbar carries a shadow
and a visible border La Suite doesn't have, a 54px dead-space gap separates the header from the
content area (La Suite and stock Nextcloud both have 0px), the main content area is flat white
instead of La Suite's grey/white-card figure-ground separation, and the content shell and sidebar
carry a rounded, floating-card look where La Suite is flush and full-bleed. None of this is a
colour-token gap — it is unmatched structural CSS in the `lasuite` design system's own
element-overrides layer, including one self-inflicted regression (`position: relative !important`
on `#header`, added by this app, which pulls Nextcloud's absolutely-positioned header back into
document flow and stacks its 50px height on top of `#content-vue`'s 50px top margin).

## What Changes

- **Top navbar**: restyle `#header` to match La Suite Messages' measured chrome — white
  background, no box-shadow, no visible bottom border (border-bottom kept at 1px but transparent,
  matching the measured rule), horizontal padding `18px`. Nextcloud's 50px header height is
  unchanged (a platform given).
- **Remove the 54px header/content gap**: drop the `position: relative !important` declared on
  `#header` in `css/systems/lasuite/element-overrides.css` (line 28) so the header returns to
  Nextcloud's own out-of-flow (`position: absolute`) behaviour, matching both La Suite (0px gap)
  and stock Nextcloud (0px gap). The accompanying `overflow: visible !important` is re-verified
  once `position` is no longer overridden, not removed pre-emptively.
- **Grey main canvas**: paint `#app-content-vue` / `.app-content` with `--lasuite-color-gray-025`
  (`#f8f8f9`) instead of white, giving the content area figure/ground separation from the content
  card, as measured on La Suite Docs' `<main>`.
- **White content card**: give the content list a white background, `border-radius:
  var(--lasuite-border-radius)` (4px), and no shadow, sitting on the new grey canvas — matching
  the measured La Suite Docs content card (`x=322`, `background:#ffffff`, `border-radius:4px`, no
  shadow).
- **Full-bleed shell + sidebar**: `#content-vue` goes flush (`border-radius: 0`, `margin: 0`,
  dropping the current rounded translucent "floating card" treatment); the sidebar
  (`#app-navigation-vue` / `.app-navigation`) goes flush too (`border-radius: 0`) and gains the
  measured drop shadow `box-shadow: 10px 0 10px rgba(0,0,0,.05)` that La Suite Docs' sidebar uses
  in place of a visible border for depth.

All five changes are confined to `css/systems/lasuite/element-overrides.css` (and
`css/systems/lasuite/bridge.css` only if a genuinely new `--color-*` mapping turns out to be
needed while implementing — none is anticipated, since every value above is expressible with
existing `--lasuite-*` tokens).

**Explicitly out of scope**: row heights, nav-item font sizes, and pane counts (2-pane vs 3-pane
list/detail layouts). These are Nextcloud component metrics and information-architecture
decisions, not design-system theming, and are not touched by this change.

**Scope correction (verified live during implementation)**: the rules land in
`css/systems/lasuite/element-overrides.css`, which the `cunningham` design system **also loads**
(its bundle is fonts → defaults → bridge → element-overrides, i.e. the same layer minus the
violet `brand-override`). The wireframe therefore applies to `cunningham` too. This is correct
rather than a leak: `cunningham` is the same Cunningham design system in its unbranded blue form,
and layout is not brand-specific. Verified on the live instance — under `cunningham` the canvas
resolves to `#f7f8f8` (the neutral npm-base gray-025) instead of `#f8f8f9`, with the same 0px gap,
flush sidebar and white 4px card. `nldesign`, `summer-breeze` and `high-contrast` are untouched,
as are the generated `defaults.css` / `brand-override.css` layers.

**ADR-031 declarative-vs-imperative behaviour**: not applicable. This change is pure CSS layout
theming with no lifecycle, aggregation, or notification behaviour involved.

**Seed data**: not applicable. No OpenRegister schemas or registers are involved in this change,
so no seed data section or seed task is required.

## Capabilities

### New Capabilities
(none — this change modifies existing theming behaviour only)

### Modified Capabilities
- `lasuite-stack`: the "La Suite Element Overrides Layer" requirement's described chrome — header
  surface treatment, navigation/content surfaces, and shell geometry — changes to match the five
  measured La Suite layout characteristics (navbar chrome, header/content gap, grey canvas, white
  content card, full-bleed shell + sidebar shadow).

`css-architecture` and `component-tokens` were both read as candidates and ruled out as the
*modified* capability, though `css-architecture` was the first one checked (per its role as the
canonical Layer-ordering spec): its "Layer 7 -- Element-Level Overrides" requirement is
design-system-agnostic (layer existence and ordering, not per-system pixel values) and none of the
five changes touch layer order, so it is unaffected. `component-tokens` governs
`--nldesign-component-*` tokens, none of which this change touches. The precise, already-existing
home for La Suite's specific chrome description is `lasuite-stack`'s own requirement, so that is
the capability with an actual delta spec below.

## Impact

- **Files changed**: `css/systems/lasuite/element-overrides.css` (primary; `#header`,
  `#content-vue`, `#app-content-vue`/`.app-content`, `#app-navigation-vue`/`.app-navigation`
  rules). `css/systems/lasuite/bridge.css` only if implementation reveals a genuine new
  `--color-*` mapping need (not anticipated).
- **Files explicitly NOT touched**: `css/systems/lasuite/defaults.css` and `brand-override.css`
  (generated, drift-guarded, shared with `cunningham` — must stay on the blue base); any
  `nldesign`, `summer-breeze`, or `high-contrast` design-system file.
- **Tests affected**: `tests/e2e/spec-coverage/lasuite-parity.spec.ts` (currently asserts
  `#header` border-style `solid` and a visible gray-100 border colour — must be updated to the new
  transparent/no-visible-border assertion and any other now-inaccurate layout expectation) and
  `tests/Unit/LasuiteDesignStackTest.php`. Must stay green: `npm run test:lasuite-tokens`,
  `npm run test:lasuite-override`, `npm run test:lasuite-bridge-coverage`,
  `npx stylelint css/systems/lasuite/*.css`, `npm run test:unit`.
- **No new Nextcloud CSS custom properties**: all five changes use tokens that already exist
  (`--lasuite-color-gray-000`, `--lasuite-color-gray-025`, `--lasuite-color-gray-100`,
  `--lasuite-border-radius`).
- **Invariant preserved**: the `--color-background-plain` / `--color-main-background*` family
  remains unmapped in `bridge.css` (existing `REQ-CSS-007` dark-mode-derivation invariant) — this
  change does not touch that mapping.
