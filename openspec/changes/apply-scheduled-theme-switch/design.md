# Design: scheduled theme switch

## Where it fits (development b4e7568)

- `lib/Controller/SettingsController.php:226-240` `setTokenSet()` checks `TokenSetService::isValidTokenSet()` (`:227`), writes app config `token_set` (`:232`) and logs `token_set_changed` (`:234`).
- `lib/BackgroundJob/UpstreamFreshnessJob.php:38-89` is the one `TimedJob` in the app (`setInterval()` at `:70`, `TIME_INSENSITIVE` at `:71`), registered in `appinfo/info.xml:132-134`.
- `templates/settings/admin.php:66-76` renders the token set dropdown.
- `openspec/specs/config-portability/spec.md:6-20`: every new instance-wide value joins the bundle with a `bundleVersion` bump.
- `openspec/specs/theme-preview/spec.md:145` "Publish Runs The Existing Instance-Wide Dialogs": an interactive switch offers the theming-sync dialog for core logo and colours.

## Decisions

### 1. One stored list in app config

`scheduled_switches` holds a JSON list of `{id, tokenSet, startAt, endAt|null, syncCoreTheming, createdBy, createdAt}` with times in UTC ISO 8601. The list is short (a handful of planned looks), so app config fits, like `group_token_sets`. Overlapping windows are refused on save: one planned look at a time is what people plan, and two overlapping windows have no obvious winner.

### 2. A time-sensitive job every five minutes

`ScheduledSwitchJob` runs every 300 seconds and is time sensitive. It applies a switch whose `startAt` has passed, records the set it replaced as `revertTo` on the entry, and at `endAt` switches back to `revertTo`. A switch that is due while the set it names no longer exists is skipped, logged, audited as failed and left in the list marked `failed`, so the administrator sees it.

Rejected: cron entries per switch. Nextcloud apps have no per-date job API beyond jobs checking a date, and one job keeps the logic testable.

### 3. The same validation, no dialog

The job calls the same token set path as `setTokenSet()` (extracted to a service method both use), so the same `isValidTokenSet()` check guards it. Nobody is there to answer the theming-sync dialog, so core logo and colours are only synced when the administrator ticked `syncCoreTheming` when planning, using the set's theming metadata the dialog would have offered.

### 4. Audited as its own action

`scheduled_switch_applied` with `old` and `new` token set ids, actor `system`, and the schedule id in context. A failed switch writes the same action with `new` null and the reason.

### 5. In the bundle

`config.scheduledSwitches` joins the bundle with a `bundleVersion` bump. Import validates every entry (set exists, windows do not overlap, times parse) in phase 1 like every other section.

## Risks

- Background jobs that do not run (AJAX cron on a quiet instance) delay a switch. The settings block shows the time the job last ran and warns when cron mode is AJAX.
- Time zones: times are entered in the administrator's browser time zone and stored in UTC; the list shows both.

## Out of scope

- Scheduling other settings than the token set (fonts, overrides). A later change can widen the entry.
- Per-group schedules.
