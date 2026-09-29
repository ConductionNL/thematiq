# Contrast evidence download and a dark logo that reaches the page

## Why

Two capabilities were half built. The code behind each works, but nothing an administrator does reaches it. Ruben reversed the decided-no on both on 29 September 2026 (DECISIONS.md row 11): build them as one small change.

#### Row `acc-compliance-report` (thematiq matrix, area accessibility)

- Capability: Download an evidence report of the contrast of your active configuration for an audit.
- Own rating: partial. Built evidence: `SettingsController::complianceReport()` serves `GET /settings/compliance-report` as a download, but no button or link in `templates/settings/admin.php` or `js/admin.js` calls it, so it is reachable only by typing the URL.
- Liferay DXP rated `partial` (a page audit and a product VPAT, neither a report of the active configuration); Nextcloud theming, openDesk and Tokens Studio rated `no`; Microsoft 365 `unknown`.

#### Row `ast-dark-logo` (thematiq matrix, area assets)

- Capability: Use a separate logo for dark mode.
- Own rating: no. Built evidence: `ThemingService::validateImagePaths()` validates `logo_dark`, `DarkPaletteService::generateForSet()` emits a `--nldesign-logo-url` override when a set has `theming.logo_dark`, and the apply dialog shows a dark logo row; but no shipped token set carries `logo_dark`, so the code path never fires.
- Microsoft 365 branding rated `yes` (Entra "Square logo (dark theme)", Microsoft 365 "Alternate logo ... optimized for use in Microsoft 365 dark themes"); the others rated `no`.

## What changes

- The admin settings page gets a "Contrast evidence report" section with two download links, JSON and Markdown, pointing at the existing endpoint. No new endpoint, no new permission.
- The Epe token set ships a dark logo variant (`img/logos/epe-dark.svg`): its letters are near black (`#231f20`) and its deer dark blue (`#00539f`) on a transparent background, both unreadable on the dark header. The dark variant draws them in white. `token-sets.json` names it as `theming.logo_dark`, and the generated `css/tokens/dark/epe.css` carries the logo override.
- A test keeps the flow honest: every `logo_dark` a shipped set declares must exist, must be a valid path, must reach the generated dark stylesheet, and its visible fills must reach 3:1 against the dark background.

## Not in this change

- `acc-contrast-in-editor` stays decided-no (DECISIONS.md row 11).
- Uploading a dark logo with a custom token set. The data flows for shipped sets; an upload path is a later change if someone asks.
- Dark variants for other shipped logos. Only Epe is used by a shipped set and unreadable on dark today among the sets checked; others follow the same recipe.
