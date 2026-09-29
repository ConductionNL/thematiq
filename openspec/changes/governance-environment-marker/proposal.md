# Environment marker: see at a glance whether you are in test or production

## Why

A tender asks for it in so many words. Gemeente Gulpen-Wittem (TenderNed 407031, 2026-01-06) requires one look and feel in the house style for all users, with a clear difference between the test and the production environment. Staff who click through a test copy that looks exactly like production file real work in the wrong place, or test destructive actions on live data.

Thematiq makes every environment look the same on purpose: the configuration bundle carries the house style from ontwikkeling to productie (OTAP). Nothing marks which environment you are in. The theme preview banner marks a trial session, not an environment.

#### Row `gov-environment-marker` (thematiq matrix, area governance)

- Capability: Show a visible difference between the test and the production environment, so nobody works in the wrong one.
- Own rating: no; built.state `none`. Built evidence: the only per-environment feature is promoting the configuration bundle between OTAP stages (lib/Service/ConfigBundleService.php:31); the trial banner (lib/Service/ThemePreviewBannerService.php) marks a session trial, not an environment; no environment label or colour exists
- Demand: tender at https://www.tenderned.nl/aankondigingen/overzicht/407031 (TenderNed 407031 (Gemeente Gulpen-Wittem, Zaaksysteem, 2026-01-06) requires one look-and-feel in the house style for all users with a clear difference between test and production; requirement 23655 in the intelligence database.)
- Nextcloud Theming (built-in app) rated `partial`: nextcloud/server@v35.0.1 theming values are per instance (apps/theming/lib/ThemingDefaults.php:237-258, set via lib/Controller/ThemingController.php:74-130 or occ theming:config), so a test instance can be given its own name, colour and logo; there is no dedicated environment banner or marker setting in apps/theming
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `partial`: https://learn.microsoft.com/en-us/entra/fundamentals/how-to-customize-branding: branding is per tenant, so a test tenant can carry a visibly different sign-in and header brand, and https://learn.microsoft.com/en-us/entra/fundamentals/reference-company-branding-css-template advises validating 'in a test tenant first'; no built-in environment banner
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/site-building/publishing-tools/staging : with staging enabled a staging bar lets users toggle Staging and Live; https://learn.liferay.com/w/dxp/sites/publishing-tools/publications/making-and-publishing-changes : a Publications bar shows the active publication and warns on context change; no marker distinguishes a separate test installation from production
- Rated no or unknown: openDesk theming `no`, Tokens Studio (Figma plugin and platform) `no`

## What changes

- An administrator declares the environment of a server in `config.php` (`thematiq.environment`: `development`, `test`, `acceptance` or `production`), so the marker never travels with a database copy or a configuration bundle.
- On every page that is not production, including the login page and pages of apps excluded from theming, a stripe and a text label name the environment. Production shows nothing.
- The theming settings page shows the current environment and tells the administrator how to change it.
- The label is text, not only colour, so it meets WCAG 2.1 success criterion 1.4.1 (use of colour).

## Capabilities

### New capabilities

- `environment-marker`: declaring the environment and marking every non-production page.

### Modified capabilities

- `config-portability`: the environment is excluded from the bundle, next to the other values that belong to one server.

## Impact

- New `lib/Service/EnvironmentMarkerService.php`, called from `lib/Listener/ThemeInjectionListener.php` before the per-app exclusion guard.
- New `js/environment-marker.js` and `css/environment-marker.css`, following `js/preview-banner.js`.
- `templates/settings/admin.php`: a read-only environment line in the theming section.
- `lib/Service/ConfigBundleService.php`: no new key; the spec records why.
- l10n: labels in English and Dutch.
- No database change, no new route.

## Rows

- `gov-environment-marker` (thematiq matrix).
