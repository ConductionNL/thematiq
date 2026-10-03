# Tasks: environment marker

Tick a box when the work is merged to `development`.

## 1. Service and injection

- [x] 1.1 Add `lib/Service/EnvironmentMarkerService.php`: reads `thematiq.environment` through `IConfig::getSystemValueString()`, maps it to a label key and a styling class, fails open. Verify: `tests/Unit/Service/EnvironmentMarkerServiceTest.php` covers each allowed value, unset, `production` and an unknown value.
- [x] 1.2 Call it from `ThemeInjectionListener::handle()` before the per-app exclusion guard, for the login and the template events. Verify: `tests/Unit/Listener/ThemeInjectionListenerTest.php` asserts the marker is emitted for an excluded app and for the login context, and not emitted when the value is `production`.
- [x] 1.3 Add `js/environment-marker.js` and `css/environment-marker.css` (stripe with `role="note"`, text label, title prefix), following `js/preview-banner.js`. Verify: the stripe does not overlap header controls at 320 px width and at 200 percent zoom.

## 2. Settings page

- [x] 2.1 `templates/settings/admin.php`: a read-only line under the theming heading showing the current environment, or "not set" with the `occ config:system:set` command. Verify: Playwright scenario "An administrator sees which environment the server is".

## 3. Configuration bundle

- [x] 3.1 Keep `thematiq.environment` out of `ConfigBundleService::export()` and ignore it on import. Verify: `tests/Unit/Service/ConfigBundleServiceTest.php` asserts an exported bundle has no environment key.

## 4. Quality

- [x] 4.1 Contrast: each stripe colour and its label reach 4.5:1 in light and dark mode; record the ratios in the test. Verify: a unit test runs `ContrastService` over the fixed pairs.
- [x] 4.2 l10n: the three labels, "Unknown environment" and the settings line in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run test:l10n`.
- [x] 4.3 Docs: a section in `docs/` on declaring the environment, with the Helm or `config.php` example. Verify: the docs build.
- [x] 4.4 Playwright: on a server with `thematiq.environment=test`, the login page and the Files app show the label and the title prefix. Verify: `tests/e2e/environment-marker.spec.ts`.
