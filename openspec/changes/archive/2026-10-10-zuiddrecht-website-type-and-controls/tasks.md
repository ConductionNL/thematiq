# Tasks: The website type scale, controls and marks are vocabulary

## 1. Spec
- [x] 1.1 Proposal and the spec delta.

## 2. Bridge
- [x] 2.1 `css/public-bridge.css`: the heading sizes, control border width and notice border width wrap their literal; the notice ground and border and the two tab roles without a fallback; the vocabulary documented.
- [x] 2.2 `scripts/generate-denhaag-bridge.mjs` and the mapping's `website` section: the marker and badge roles wrapped, the section regenerated.

## 3. Token set
- [x] 3.1 `css/tokens/zuiddrecht.css`: the vocabulary and the website corners.
- [x] 3.2 Dark variant regenerated.

## 4. Tests
- [x] 4.1 `tests/vitest/publicBridgeRoleLayer.spec.js`: zuiddrecht resolves each role; the school sets and vng keep every value (the control).

## 5. Verify
- [x] 5.1 Live check on the demo instance (:8097) with portaliq reading the tab and notice roles.
      Run on the throwaway instead of :8097. Live 2026-10-10 on the throwaway thematiq-live2 (:8093, NC 34; thematiq build/openspecs-live2, openregister 2.1.38, portaliq 0.2.10, dossiq 0.4.50, learniq 0.3.13 unstable development builds; `portaliq:example-site:install zuiddrecht` + `portaliq:example-resident:install zuiddrecht`, `learniq:example-set:load mbo` and `training`): Mijn zaken's tab list draws a 2px #D3D8DF line and the current tab a 4px #CC0000 mark; the e-mail prompt notice is #EAF0F7 with a 1px #B9CBE2 border.
