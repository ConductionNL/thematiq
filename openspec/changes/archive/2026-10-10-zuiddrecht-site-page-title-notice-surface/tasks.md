# Tasks: The site's page title, lead, notice text and surface are vocabulary

## 1. Spec
- [x] 1.1 Proposal and the spec delta.

## 2. Bridge
- [x] 2.1 `css/public-bridge.css`: the lead size wraps the Utrecht literal; the notice text next to the notice ground and border; the page title and surface roles; the vocabulary documented.

## 3. Token set
- [x] 3.1 `css/tokens/zuiddrecht.css`: the five names, every pair computed.
- [x] 3.2 Dark variant and reference page regenerated.
- [x] 3.3 The attention strip: three names, the bridge roles, zuiddrecht's values.

## 4. Tests
- [x] 4.1 `tests/vitest/publicBridgeRoleLayer.spec.js`: zuiddrecht resolves each role; the school sets and vng keep every value (the control).
- [x] 4.2 `tests/vitest/zuiddrechtTokenSet.spec.js`: the notice text and the surface pairs in both schemes.

## 5. Verify
- [x] 5.1 Live check on the demo instance with portaliq `feat/zuiddrecht-site-pixel-match`: the content page title at 44px, the band and the table header #F4F6F9.
      Run on the throwaway with portaliq development (the pixel-match branch has landed). Live 2026-10-10 on the throwaway thematiq-live2 (:8093, NC 34; thematiq build/openspecs-live2, openregister 2.1.38, portaliq 0.2.10, dossiq 0.4.50, learniq 0.3.13 unstable development builds; `portaliq:example-site:install zuiddrecht` + `portaliq:example-resident:install zuiddrecht`, `learniq:example-set:load mbo` and `training`): `/afval` page title 44px, its table header cells #F4F6F9, the home's surface band (`nl-link-columns--surface`) #F4F6F9, `--thematiq-surface-color` #F4F6F9.
