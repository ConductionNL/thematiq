# Schedule a theme switch for a date

## Why

Communication teams plan their looks ahead: a campaign week, King's Day, a remembrance period with a quiet palette. Today an administrator has to be at a keyboard at midnight to switch the token set, and again to switch it back. Switching a theme is the core of thematiq (applying and switching a theme), and it cannot be planned.

#### Row `app-scheduled-switch` (thematiq matrix, area apply)

- Capability: Schedule a theme change for a date, such as a campaign or a holiday look.
- Own rating: no; built.state `none`. Built evidence: grep -rniF 'schedule' across lib/, js/admin.js, templates/ found only js/admin.js:2761 schedulePreviewRepaint() (an animation-frame debounce, unrelated) and UpstreamFreshnessJob's own cron wiring comment; no date-based or calendar theme-change feature exists anywhere
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/sites/publishing-tools/publications/making-and-publishing-changes : publications can be scheduled for a date and time and content pages are supported, so a page style book selection can go live on schedule; style books themselves are not in the compatibility list (https://learn.liferay.com/w/dxp/sites/publishing-tools/publications) and Publications is in maintenance mode as of 2026.Q1
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `unknown`, openDesk theming `no`, Tokens Studio (Figma plugin and platform) `no`

## What changes

- An administrator schedules a switch to a token set at a date and time, with an optional end date that switches back to the set that was active before.
- A background job applies due switches through the same validation as the token set dropdown and writes an audit entry for each one.
- The theming settings page lists planned switches, lets the administrator cancel one, and says which set is active until when.
- Planned switches travel in the configuration bundle, so a campaign prepared on acceptance goes to production with the rest.

## Capabilities

### New capabilities

- `scheduled-switch`: planning, applying and cancelling timed token set switches.

### Modified capabilities

- `config-portability`: the bundle carries planned switches.
- `theming-audit`: the action vocabulary gains `scheduled_switch_applied`.

## Impact

- New `lib/Service/ScheduledSwitchService.php` and `lib/BackgroundJob/ScheduledSwitchJob.php` (registered in `appinfo/info.xml` next to `UpstreamFreshnessJob`).
- `lib/Controller/SettingsController.php`: list, create and cancel endpoints next to `setTokenSet()` (`:226`).
- `templates/settings/admin.php` and `js/admin.js`: a "Planned switches" block under the token set dropdown (`:66-76`).
- `lib/Service/ConfigBundleService.php`: one new bundle key, `bundleVersion` bump.

## Rows

- `app-scheduled-switch` (thematiq matrix).
