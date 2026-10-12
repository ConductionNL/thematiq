# Tasks: school sets type scale

- [x] 1. The four school sets name heading 1, page title, content and lead sizes; the academy the month case.
- [x] 2. `css/public-bridge.css`: `--thematiq-date-month-text-transform`.
- [x] 3. `tests/vitest/publicBridgeRoleLayer.spec.js`.
- [x] 4. Measured on :8092 with the set swapped in.
   - Measured on the throwaway instead of :8092. Live 2026-10-10 on the throwaway thematiq-live2 (:8093, NC 34; thematiq build/openspecs-live2, openregister 2.1.38, portaliq 0.2.10, dossiq 0.4.50, learniq 0.3.13 unstable development builds; `portaliq:example-site:install zuiddrecht` + `portaliq:example-resident:install zuiddrecht`, `learniq:example-set:load mbo` and `training`): Esdoornveen content pages (`/vakanties`, `/voor-studenten`) title 44px, the academy's `/cursusaanbod` 48px; the date tile month on the academy home renders `text-transform: uppercase` (portaliq already reads the role); `--utrecht-paragraph-lead-font-size` resolves to 21px and `--nldesign-website-content-font-size` to 17px. The body paragraphs on those pages render 18px: portaliq's Contentpagina rule (`site-theme.css`, `[data-widget-key='nlParagraph'] .utrecht-paragraph` at 1.125rem) beats the content size for every designed-header portal. That is portaliq's rule, not this set; written to for-ruben/thematiq-sibling-asks.md.
