# Tasks: contrast evidence download and dark logo

Tick a box when the work is merged to `development`.

## 1. Contrast evidence download

- [ ] 1.1 Red test: `tests/Unit/Templates/AdminComplianceReportTest.php` and `tests/vitest/admin-compliance-report.spec.js` fail on development.
- [ ] 1.2 Section and links in `templates/settings/admin.php`; `initComplianceReport()` in `js/admin.js`. Verify: both tests green.
- [ ] 1.3 Strings in `l10n/en.json` and `l10n/nl.json` (and the locales that carry every key), `.js` catalogues rebuilt. Verify: `npm run test:l10n`, `npm run check:l10n-js`.

## 2. Dark logo

- [ ] 2.1 Red test: `tests/Unit/Service/ShippedDarkLogoTest.php` fails on development (no shipped set declares `logo_dark`).
- [ ] 2.2 `img/logos/epe-dark.svg`, `theming.logo_dark` on the `epe` entry, `php scripts/generate-dark-variants.php --force` for epe. Verify: the test green, `git diff css/tokens/dark` touches `epe.css` only.

## 3. Quality

- [ ] 3.1 Playwright `tests/e2e/spec-coverage/evidence-download-and-dark-logo.spec.ts`.
- [ ] 3.2 Matrix: `acc-compliance-report` and `ast-dark-logo` move to `built` naming this change; `gap-decisions.json` records the reversal.
