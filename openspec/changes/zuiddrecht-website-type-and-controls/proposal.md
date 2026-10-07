---
kind: code
---

## Why

The Mijn Zuiddrecht boards (the signed-in pages of the Zuiddrecht demo site) draw a type scale,
controls and marks that no token the `--nldesign-*` layer names can carry: 44px page titles where
the public bridge gives every portal 36px, outlined buttons with a 2px border over the bridge's
1px, status tags as pills where the Den Haag data badge has 4px corners, 36px process step markers
filled blue when done and ringed red when current where the bridge gives 32px markers in the
success and primary colours, a light blue notice with a 1px border where a plain notice has none
of its own, and a tab list with a grey line and a red mark under the tab on screen.

Measured on the demo instance (:8097, 6 October): the overview's greeting was 36px, the e-mail
notice had a 2px black border, the case tags were green with square corners, and the steps were
green ticks on 32px markers. A set cannot say otherwise: `--utrecht-*` and `--denhaag-*` roles are
declared by the bridge for every set, and a set that declared them itself would change its
workplace pages too.

## What Changes

- **A website vocabulary for type, controls and marks**, read by `css/public-bridge.css` only:
  `--nldesign-website-heading-1-font-size`, `-2-`, `-3-`; `--nldesign-website-control-border-width`;
  `--nldesign-website-badge-border-radius`; `--nldesign-website-step-marker-size`,
  `-step-done-color`, `-step-done-mark-color`, `-step-current-color`,
  `-step-current-background-color`; `--nldesign-website-notice-background-color`, `-border-color`,
  `-border-width`; `--nldesign-website-tab-line-color` and `-tab-current-color`.
- **Every role keeps today's value as its fallback.** The heading sizes, border widths and the
  notice border width wrap their literal; the Den Haag marker and badge roles are wrapped by the
  generator from a new `website` section of `scripts/mapping/denhaag-component-tokens.json`; the
  notice ground and border and the two new tab roles (`--thematiq-tab-line-color`,
  `--thematiq-tab-current-color`) carry no fallback, as the website radii do, so a consumer's own
  fallback applies where a set names none.
- **The zuiddrecht set names them**, plus the website corners it never declared (4px controls,
  6px cards). Dark variant regenerated.

**Out of scope:** the portal components that read the two tab roles and the notice (portaliq);
a 3px ring on the current step only (Den Haag's step marker has one border width for every state,
so the current step keeps the 2px ring).

## Capabilities

### Modified Capabilities
- `brand-motif-on-portals`: the website vocabulary grows from logo, corners and accent to the
  type scale, controls and marks.

## Impact
- `css/public-bridge.css` (hand-written roles and the generated Den Haag section),
  `scripts/generate-denhaag-bridge.mjs`, `scripts/mapping/denhaag-component-tokens.json`,
  `css/tokens/zuiddrecht.css`, `css/tokens/dark/zuiddrecht.css`,
  `tests/vitest/publicBridgeRoleLayer.spec.js`.
- Every other set: unchanged, measured by the control in the test.
