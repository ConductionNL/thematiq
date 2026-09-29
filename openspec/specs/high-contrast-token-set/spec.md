# high-contrast-token-set Specification

## Purpose
TBD - created by archiving change high-contrast-token-set. Update Purpose after archive.
## Requirements
### Requirement: High-Contrast Design System and Token Set
The app MUST ship a `high-contrast` design system in `design-systems.json` — an ordered list of stylesheets under `css/systems/high-contrast/` — and a `hoog-contrast` token set in `token-sets.json` (backed by `css/tokens/hoog-contrast.css`) bound to that design system. The token set MUST be selectable exactly like any other shipped set, with no new UI, controller, route, or config key. Like every shipped brand it is offered in the admin dropdown under the token-sets requirement "Only Fully Functional Brands Are Selectable": today that means while it is active or mapped to a group, until the vocabulary audit admits it to `SELECTABLE_SHIPPED_SETS`. Its fixed WCAG pairs MUST meet WCAG 2.2 AAA: `--nldesign-color-primary` vs `--nldesign-color-primary-text` ≥ 7:1, and `--nldesign-color-primary` vs the set background ≥ 4.5:1.

#### Scenario: High-contrast set is selectable and themes the instance
- GIVEN an admin on the NL Design settings panel
- AND the `hoog-contrast` set is selectable under the token-sets requirement "Only Fully Functional Brands Are Selectable"
- WHEN the admin selects "Hoog contrast (WCAG AAA)" and applies it
- THEN the `high-contrast` design system stylesheets and the `hoog-contrast` token CSS MUST be injected on themed pages
- AND the resolved primary/primary-text contrast MUST be at least 7:1

#### Scenario: High-contrast set passes the AAA contrast gate
@e2e exclude backend computation — asserted by the shipped-token-set-contrast-audit PHPUnit gate
- GIVEN the `hoog-contrast` token set
- WHEN the contrast audit evaluates it at the AAA threshold
- THEN the primary/primary-text pair MUST be `AA`-and-`AAA` compliant (≥ 7:1)
- AND the primary/background pair MUST be at least 4.5:1

### Requirement: Operating-System High-Contrast Cooperation
The high-contrast design system stylesheet MUST honor `@media (prefers-contrast: more)` and `@media (forced-colors: active)`. Under `forced-colors: active` (e.g. Windows High Contrast Mode), the theme MUST use CSS `system-color` keywords and MUST preserve visible text, borders, and focus indicators rather than hardcoding colors the operating system overrides. This satisfies the EN 301 549 expectation referenced by Digitoegankelijk.

#### Scenario: Forced-colors mode keeps the UI operable
@e2e exclude OS-level rendering — verified under forced-colors emulation, not a standard localhost UI flow
- GIVEN the `hoog-contrast` set is active
- WHEN the page is rendered with `forced-colors: active`
- THEN text, input borders, and focus indicators MUST remain visible
- AND the stylesheet MUST NOT suppress the OS-provided high-contrast colors

#### Scenario: prefers-contrast:more strengthens the theme
@e2e exclude media-query branch — computed-style assertion under an emulated media feature
- GIVEN a user whose environment reports `prefers-contrast: more`
- WHEN a themed page is rendered with the `hoog-contrast` set
- THEN the applied contrast MUST be at least as strong as the default `hoog-contrast` values

### Requirement: Status Reflects the Verified Feature
`GOVERNMENT-FEATURES.md` A-08 MUST NOT be marked "Beschikbaar" until the `hoog-contrast` set ships AND passes the AAA verdict in the contrast audit. Until both hold, A-08 MUST remain "Gepland".

#### Scenario: A-08 status matches reality
@e2e exclude documentation invariant — checked against the shipped doc and the audit report
- GIVEN `docs/GOVERNMENT-FEATURES.md` row A-08
- WHEN its status is "Beschikbaar"
- THEN the `hoog-contrast` set MUST exist in `token-sets.json`
- AND the contrast report MUST record an AAA-passing verdict for it

