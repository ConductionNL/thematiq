# Tasks: esdoornveen-hero-image-corner

- [x] **T1**: `--nldesign-hero-image-clip-path` in `css/tokens/esdoornveen.css`
  - vitest `schoolTokenSets.spec.js` "esdoornveen: the photo beside the hero"
- [x] **T2**: live: proof run 2
  - Live 2026-10-10 on the throwaway thematiq-live2 (:8093, NC 34; thematiq build/openspecs-live2, openregister 2.1.38, portaliq 0.2.10, dossiq 0.4.50, learniq 0.3.13 unstable development builds; `portaliq:example-site:install zuiddrecht` + `portaliq:example-resident:install zuiddrecht`, `learniq:example-set:load mbo` and `training`): the Esdoornveen home's hero photo (`pq-hero__photo`) computes `clip-path: polygon(0px 0px, 100% 0px, 100% calc(100% - 120px), calc(100% - 75px) 100%, 0px 100%)`, the board's corner.
