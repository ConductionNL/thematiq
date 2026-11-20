---
description: Run the PHP and JavaScript quality checks
---

Run the quality suite from the repository root and report each result.

1. PHP (needs `composer install` first):
   - `composer check:strict` runs lint, phpcs, cs:check, phpmd, psalm, phpstan and test:all.
   - Outside a Nextcloud server tree the PHPUnit part is skipped and the summary says `EXCEPT test:all, WHICH DID NOT RUN`. Report that as "PHP tests not run", not as a pass.
2. JavaScript and CSS (needs `npm ci` first):
   - `npm run lint`
   - `npm run stylelint`
   - `npm run format`
   - `npm run check:manifest`
3. Translations: `npm run test:l10n` and `npm run check:l10n-js`.

Fixers, only when asked: `composer cs:fix`, `composer phpcs:fix`, `npm run lint:fix`, `npm run stylelint:fix`, `npm run format:fix`.
