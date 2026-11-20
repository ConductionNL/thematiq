---
description: Run the Playwright end-to-end tests against a disposable Nextcloud
---

The Playwright suite lives in `tests/e2e/` and is configured by `playwright.config.ts`. It seeds and deletes data, so it must run against your own instance.

1. Make sure a disposable Nextcloud with thematiq enabled is running, for example the stack from `docker compose -f docker-compose.yml up -d --build` (port from `APP_PORT`, default 8080).
2. Run `PLAYWRIGHT_BASE_URL=<that url> npx playwright test`. Add a file path or `--grep` from `$ARGUMENTS` to run a subset.
3. The suite refuses http://localhost:8080 unless `THEMATIQ_E2E_ALLOW_SHARED_INSTANCE` names that origin (see `tests/e2e/shared-instance.ts`). Only set it when the instance is yours.
4. Do not pass `--workers`; the config runs one worker on purpose.

Report the pass, fail and flaky counts. The HTML report is written to `tests/e2e/playwright-report/`.
