# Design

## Download links (acc-compliance-report)

`templates/settings/admin.php` gets a section `#nldesign-compliance-report` after the theming audit log, with a hint and two `<a class="button" download>` links, `#nldesign-compliance-report-json` and `#nldesign-compliance-report-markdown`. The template renders them without an `href`; `js/admin.js` `initComplianceReport()` sets `href` from `OC.generateUrl('/apps/thematiq/settings/compliance-report')` plus `?format=json` or `?format=markdown`, the same way the audit log and the configuration bundle build their URLs. A link, not a button with a click handler, because the browser handles the download and the `Content-Disposition: attachment` the endpoint already sends.

The endpoint is unchanged: `SettingsController::complianceReport()`, `#[AuthorizedAdminSetting(Admin::class)]`. A delegated theming admin who can open the panel can also download the report.

## Dark logo (ast-dark-logo)

Data path, all existing code:

1. `token-sets.json` entry `epe` gains `theming.logo_dark: "img/logos/epe-dark.svg"`.
2. `DarkPaletteService::generateForSet('epe')` reads it through `loadSetMeta()` and adds `--nldesign-logo-url: url('../../../img/logos/epe-dark.svg')` to the dark declarations.
3. `scripts/generate-dark-variants.php --force` writes `css/tokens/dark/epe.css`, which is committed like every other dark variant.
4. `ThemingService` never passes `logo_dark` to Nextcloud core theming (one logo slot upstream); the dark stylesheet delivers it.

Known limit, not changed here: `isFresh()` keys on the token CSS hash only, so a later edit of `logo_dark` alone does not make the dark file stale. The committed file is regenerated with `--force` in this change, so installs get the override.

## Tests

- `tests/Unit/Templates/AdminComplianceReportTest.php`: the template ships both links inside the section, and the route the JS builds exists in `appinfo/routes.php` as `settings#complianceReport`.
- `tests/vitest/admin-compliance-report.spec.js`: after admin.js loads, both links carry the endpoint URL with the right `format`.
- `tests/Unit/Service/ShippedDarkLogoTest.php`: every shipped `logo_dark` exists, passes `ThemingService::validateImagePaths()`, appears in the committed dark stylesheet, and every fill colour in it reaches 3:1 (WCAG 1.4.11) against the dark background `#141414`; Epe declares one.
- `tests/e2e/spec-coverage/evidence-download-and-dark-logo.spec.ts`: the two scenarios in a browser.
