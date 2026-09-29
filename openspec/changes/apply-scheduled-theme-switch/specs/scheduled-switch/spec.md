# Spec delta: scheduled switch (scheduled-switch)

A new capability. An administrator plans a token set switch for a date, optionally with an end date, and the app applies it on time.

## ADDED Requirements

### Requirement: An administrator plans a switch

Settings > Administration > Theming MUST let an administrator plan a switch to any available token set at a start date and time, with an optional end date and time. The app MUST refuse a planned switch whose token set does not exist, whose end is not after its start, or whose window overlaps another planned switch, and MUST say why.

#### Scenario: An administrator plans a campaign look

- GIVEN an administrator on Settings > Administration > Theming with `rijkshuisstijl` active
- WHEN they plan a switch to `koningsdag-oranje` from 26 April 18:00 to 28 April 08:00 and save
- THEN the "Planned switches" list MUST show the switch with both times in their own time zone
- AND the active token set MUST still be `rijkshuisstijl`

#### Scenario: Overlapping plans are refused

- GIVEN a planned switch from 1 May to 7 May
- WHEN an administrator plans a second switch from 5 May to 10 May
- THEN the save MUST fail with a message that the windows overlap
- AND the list MUST still hold only the first switch

### Requirement: The app applies a due switch and switches back

A background job MUST run at least every five minutes and apply every planned switch whose start has passed, through the same token set validation as the dropdown. When the switch has an end, the app MUST switch back to the token set that was active just before the switch, once the end has passed. The switch MUST reach every page on the next page load, like a switch from the dropdown.

#### Scenario: The look starts and ends on time

- GIVEN a planned switch to `koningsdag-oranje` from 26 April 18:00 to 28 April 08:00, and `rijkshuisstijl` active
- WHEN the background job runs at 26 April 18:03
- THEN a user who opens the dashboard MUST see the `koningsdag-oranje` colours
- AND when the job runs at 28 April 08:02, the dashboard MUST show `rijkshuisstijl` again

#### Scenario: A switch to a deleted set fails visibly

- GIVEN a planned switch to the custom set `custom-campagne`, which an administrator deleted afterwards
- WHEN the switch becomes due
- THEN the active token set MUST stay unchanged
- AND the planned switch MUST show as failed in the list with the reason
- AND the audit log MUST contain a `scheduled_switch_applied` entry with `new` empty and the reason

### Requirement: Core theming is synced only when asked

A planned switch MUST NOT change Nextcloud core theming values (logo, primary colour, background) unless the administrator ticked "also update the Nextcloud logo and colours" when planning it. When ticked, the app MUST apply the theming metadata of the token set that the theming-sync dialog would have offered.

#### Scenario: A switch without core sync keeps the core logo

- GIVEN a planned switch without the core sync option
- WHEN the switch is applied
- THEN the Nextcloud core logo and primary colour MUST be unchanged

### Requirement: An administrator cancels a planned switch

An administrator MUST be able to cancel a planned switch that has not started, and a running switch, which then switches back at once. The endpoints MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]`.

#### Scenario: Cancelling a running campaign

- GIVEN a running switch to `koningsdag-oranje` that replaced `rijkshuisstijl`
- WHEN an administrator cancels it on Settings > Administration > Theming
- THEN the active token set MUST be `rijkshuisstijl`
- AND the switch MUST disappear from the list

#### Scenario: A non-admin cannot plan a switch

- GIVEN a signed-in user who is not an administrator
- WHEN they call `POST /apps/thematiq/settings/scheduled-switches`
- THEN the response MUST be 403

### Requirement: The page warns when switches may run late

The "Planned switches" block MUST show when the scheduling job last ran, and MUST warn when Nextcloud's background job mode is AJAX, because planned switches then only run while someone uses the site.

#### Scenario: An administrator on AJAX cron is warned

- GIVEN a server whose background jobs run in AJAX mode
- WHEN an administrator opens the "Planned switches" block
- THEN the block MUST state that switches may start late and name the cron setting to change
