---
description: Run the unit tests (Vitest and PHPUnit)
---

Run the unit tests from the repository root and report passed and failed counts.

1. `npm run test:unit` runs Vitest over `tests/vitest/`. To run one file: `npx vitest run tests/vitest/<name>.spec.js`.
2. `composer test:unit` runs PHPUnit over `tests/Unit/`. It needs a Nextcloud server tree (`../../lib/base.php`); without one it prints `SKIPPED` and runs nothing. Say so instead of reporting a pass.
3. After editing a token set by hand, also run `bash scripts/token-set-gate.sh` (needs `composer install`).

If `$ARGUMENTS` names a file or a test, run only that.
