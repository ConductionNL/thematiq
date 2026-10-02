# Admin JS Test Coverage — Delta

**Spec refs**: none pre-existing (new capability) — cross-cuts `per-app-theming`, `token-sets`
**Standards**: hydra ADR-008 (testing — distinguish unit vs. e2e reality)

## ADDED Requirements

### Requirement: Admin Panel Logic Has Direct Unit Coverage

The pure logic units of the admin page MUST have dedicated Vitest unit tests that call them directly, independent of e2e/Playwright coverage, and a test from the caller that drives `js/admin.js` so a helper nothing calls cannot pass. This applies to HTML escaping, the disabled-apps payload sent in the app-theming save POST, the app-search filter predicate, and the themed/total counting logic. The app-theming decisions live in `js/lib/appTheming.js`; `escapeHtml()` stays inside `js/admin.js` and is tested through the page.

`npm run lint` MUST run ESLint over the shipped JavaScript; it MUST NOT be a message that exits 0.

#### Scenario: escapeHtml neutralizes script-injection input

@e2e exclude jsdom with real DOM events against js/admin.js; proven by tests/vitest/admin.spec.js 'renders a script-carrying value as inert text in the theming dialog'


- GIVEN the string `<script>alert(1)</script>`
- WHEN `escapeHtml()` is called with that string
- THEN the returned string MUST NOT contain an unescaped `<script>` tag
- AND the returned string, when inserted into `innerHTML`, MUST render as inert text

#### Scenario: Disabled-apps payload reflects unchecked checkboxes only

@e2e exclude pure function plus the caller; proven by tests/vitest/admin.spec.js 'lists exactly the unchecked apps, in order, and never a checked one' and 'posts the unchecked apps as the exclusion list'


- GIVEN a set of app checkbox states where some are checked (themed) and some are unchecked
  (excluded)
- WHEN the disabled-apps payload builder is called with that checkbox state set
- THEN the returned list MUST contain exactly the app ids whose checkbox is unchecked
- AND MUST NOT contain any app id whose checkbox is checked

#### Scenario: Vitest coverage floor reflects real admin.js coverage, not just the pure-helper module

@e2e exclude a CI ratchet, not page behaviour; enforced by tests/coverage-ratchet.sh against tests/.coverage-baseline.json


- GIVEN `tests/.coverage-baseline.json`'s `vitest` value
- WHEN `npm run test:coverage` is run after this change lands
- THEN the measured `vitest` coverage percentage MUST be higher than the pre-change baseline of
  8.18% (it was 56.98% by 2 Oct 2026)
- AND `tests/.coverage-baseline.json` MUST be updated to the new value so the ratchet cannot
  silently fall back to the old, lower floor
