# Tasks: Brand motif and role layer on portals

## 1. Spec
- [x] 1.1 Proposal and the spec delta.

## 2. Bridge
- [x] 2.1 `css/public-bridge.css`: the role layer the site reads (375 roles, measured).
- [x] 2.2 The brand stripe and its inverse under the library names, without fallbacks.
- [x] 2.3 The accent roles and the badge roles.
- [x] 2.4 The website logo roles and the footer logo.
- [x] 2.5 `css/brand-stripe.css`: hand the two image names to the library.

## 3. Token sets
- [x] 3.1 Accent, footer and website logo tokens in `wilgenboom`, `vaartveld`, `esdoornveen`, `warmtepompacademie` and `zuiddrecht`.
- [x] 3.2 Inverse motifs for `wilgenboom`, `vaartveld` and `esdoornveen`; wilgenboom's green line.
- [x] 3.3 Regenerated dark variants and reference pages.

## 4. Tests
- [x] 4.1 vitest `tests/vitest/publicBridgeRoleLayer.spec.js` (roles, stripe, accent, logo, the control).
- [x] 4.2 `workplaceLayout.spec.js` (the two image names), `schoolTokenSets.spec.js` (wilgenboom 17px).
- [x] 4.3 `bash scripts/token-set-gate.sh` runs the new spec.

## 5. Verify
- [ ] 5.1 Live check on a portal (:8091) with each school set and zuiddrecht: logo, styled buttons, stripe under the header and over the footer (needs portaliq `site-chrome-follows-the-design`).
