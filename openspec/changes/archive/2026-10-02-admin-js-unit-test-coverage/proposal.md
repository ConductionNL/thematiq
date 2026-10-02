---
kind: code
---

## Why

`js/admin.js` is 1391 lines — 92% of the app's entire client-side JS surface (`js/admin.js` +
`js/lib/tokenTransforms.js` = 1515 lines total, per `find js -type f | xargs wc -l`) — and is the
only code that: submits authenticated CSRF-protected POSTs
(`OC.generateUrl('/apps/nldesign/settings/app-theming')` at `js/admin.js:1161`, plus theming/token-set
saves), handles file upload/import of custom token sets and CSS overrides
(`js/admin.js:1225-1348`), and renders all interactive admin UI (dropdown, dialogs, color pickers).

Yet `tests/.coverage-baseline.json` — the ratchet floor enforced by
`tests/coverage-ratchet.sh` via `npm run test:coverage-ratchet` — records:

```json
{"phpunit": 11.37, "vitest": 8.18, "tolerance": 0.5}
```

`vitest.config.js`'s own docblock explains why: "nldesign is a CSS/PHP theming app — it has no
Vue/Pinia frontend. The testable client-side logic is the set of PURE design-token / colour
transforms... extracted into `js/lib/tokenTransforms.js`" — and `tests/vitest/tokenTransforms.spec.js`
is in fact the **only** vitest file (`find tests/vitest -type f` → one file), testing only the
124-line pure-helper module. The 1391-line `admin.js` — the CSRF-POST handlers, the custom
dropdown, the two custom dialogs, the file upload/import flow — has **zero unit test coverage**,
and the ratchet mechanism (which only prevents *regression* below the current floor, per
`feedback_coverage-enforcement-and-racing-prs`) locks that 8.18% in as permanently "passing" CI.

Compounding this, `package.json`'s `lint` script is a no-op: `"lint": "echo 'No JavaScript to
lint - nldesign is CSS/PHP only'"` (`package.json:14`) — a factually stale claim now that
`js/admin.js` exists — and `package.json`'s `devDependencies` contain no `eslint` at all (only
`stylelint`, `ts-node`, `typescript`, `vitest`, `@playwright/test`,
`@cyclonedx/cyclonedx-npm`). `.github/workflows/code-quality.yml` sets `enable-eslint: true` in
its call to the shared `ConductionNL/.github` quality workflow, but with no eslint config or
dependency present in this repo, that flag has nothing to lint even if the shared workflow invokes
`npm run lint` (which itself just echoes a message and exits 0). Both the coverage ratchet and the
eslint gate report green while the largest, most security/behavior-relevant file in the app has no
meaningful automated check on it beyond a partial set of Playwright e2e specs.

This is exactly the fleet's "phantom green" failure mode: CI is green, but the checks that are
green do not exercise the code that matters.

## What Changes

- Extract `admin.js`'s pure/testable logic units (URL/payload building for the save handlers,
  the search-filter predicate, the trigger-label counting logic, the escape-HTML helper, the
  contrast-warning HTML builder) into small, unit-testable functions — either moved into
  `js/lib/` alongside `tokenTransforms.js` or exported from `admin.js` behind a
  test-only hook — without changing runtime behavior.
- Add `tests/vitest/admin.spec.js` covering at minimum: `escapeHtml()` XSS-safety on
  script/quote-containing input, the app-search filter predicate used by
  `renderAppThemingList()`'s `search.addEventListener('input', ...)` (`js/admin.js:1122-1127`),
  `updateTriggerLabel()`'s counting logic, and the disabled-apps payload construction in
  `saveAppTheming()` (`js/admin.js:1154-1159`).
- Raise `tests/.coverage-baseline.json`'s `vitest` floor to reflect the new, higher coverage once
  the above tests land (ratchet moves forward, never backward, per existing convention).
- Either wire a real `eslint` config + dependency into `package.json` and replace the no-op
  `lint` script with an actual `eslint js/**/*.js` invocation, or — if the team's intent is
  genuinely "no linting for this app" — update the stale docblock/script message so it stops
  describing a codebase that no longer matches (`js/admin.js` did not exist, or was trivial, when
  that message was written; it is not trivial now).
- Not BREAKING: test/tooling-only change, no runtime behavior change to `admin.js` beyond the
  extraction refactor (which must preserve exact existing behavior).

## Impact

- `js/admin.js` — extract testable helpers (refactor only, no behavior change).
- `js/lib/` — new/extended pure-helper module(s), or `js/admin.js` exports for testing.
- `tests/vitest/admin.spec.js` — new.
- `tests/.coverage-baseline.json` — raised `vitest` floor.
- `package.json` — either a real `lint` script + `eslint` devDependency, or a corrected message.
