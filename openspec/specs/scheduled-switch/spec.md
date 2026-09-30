# scheduled-switch Specification

## Purpose
An administrator plans a token set switch for a date, optionally with an end date, and the app applies it on time. Created by archiving change apply-scheduled-theme-switch.

## Requirements
### Requirement: An administrator plans a switch

Settings > Administration > Theming MUST let an administrator plan a switch to any available token set at a start date and time, with an optional end date and time. The app MUST refuse a planned switch whose token set does not exist, whose end is not after its start, or whose window overlaps another planned switch, and MUST say why.

#### Scenario: An administrator plans a campaign look

@e2e exclude writes a planned switch into the shared CI instance that the other e2e specs read; proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testPlanningStoresTheSwitchAndChangesNothingYet and tests/vitest/admin-scheduled-switches.spec.js 'plans a switch with the local times sent as UTC'

- GIVEN an administrator on Settings > Administration > Theming with `rijkshuisstijl` active
- WHEN they plan a switch to `koningsdag-oranje` from 26 April 18:00 to 28 April 08:00 and save
- THEN the "Planned switches" list MUST show the switch with both times in their own time zone
- AND the active token set MUST still be `rijkshuisstijl`

#### Scenario: Overlapping plans are refused

@e2e exclude proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testOverlappingPlanIsRefused, tests/Unit/Controller/ScheduledSwitchControllerTest.php::testARefusedPlanAnswers400WithTheReason and tests/vitest/admin-scheduled-switches.spec.js 'shows the reason when a plan is refused'

- GIVEN a planned switch from 1 May to 7 May
- WHEN an administrator plans a second switch from 5 May to 10 May
- THEN the save MUST fail with a message that the windows overlap
- AND the list MUST still hold only the first switch

### Requirement: The app applies a due switch and switches back

A background job MUST run at least every five minutes and apply every planned switch whose start has passed, through the same token set validation as the dropdown. When the switch has an end, the app MUST switch back to the token set that was active just before the switch, once the end has passed. The switch MUST reach every page on the next page load, like a switch from the dropdown.
Until its end, a running switch MUST stay applied: when another token set is active, each run of the job MUST apply the switch's token set again, and MUST still switch back to the token set it replaced at the end.

#### Scenario: A set picked by hand during a running switch does not last

@e2e exclude depends on the background job and the clock, not on a page; proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testARunningSwitchKeepsItsSetActive and ::testKeepingASwitchActiveSyncsCoreThemingAgain

- GIVEN a running switch to `koningsdag-oranje` that replaced `rijkshuisstijl`
- AND an administrator picks `nextcloud` in the dropdown during the window
- WHEN the background job runs
- THEN the active token set MUST be `koningsdag-oranje` again
- AND when the end passes, the active token set MUST be `rijkshuisstijl`

#### Scenario: The look starts and ends on time

@e2e exclude depends on the background job and the clock, not on a page; proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testTheLookStartsAndEndsOnTime

- GIVEN a planned switch to `koningsdag-oranje` from 26 April 18:00 to 28 April 08:00, and `rijkshuisstijl` active
- WHEN the background job runs at 26 April 18:03
- THEN a user who opens the dashboard MUST see the `koningsdag-oranje` colours
- AND when the job runs at 28 April 08:02, the dashboard MUST show `rijkshuisstijl` again

#### Scenario: A switch to a deleted set fails visibly

@e2e exclude needs a custom set deleted after planning and a job run; proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testASwitchToADeletedSetFailsVisibly and tests/vitest/admin-scheduled-switches.spec.js 'lists the switches with a cancel button each and the failure reason'

- GIVEN a planned switch to the custom set `custom-campagne`, which an administrator deleted afterwards
- WHEN the switch becomes due
- THEN the active token set MUST stay unchanged
- AND the planned switch MUST show as failed in the list with the reason
- AND the audit log MUST contain a `scheduled_switch_applied` entry with `new` empty and the reason

### Requirement: A planned switch applies a set like the apply dialog

A planned switch MUST apply its token set the way applying it by hand does: the token set, and the Nextcloud core theming (logo, primary colour, background) that the theming-sync dialog would have offered for that set — the branding a set captured when it was saved, or a reset to Nextcloud's defaults for the stock set. The same MUST hold when a running switch applies its set again and when a switch goes back to the token set it replaced. There MUST be no option to switch the token set alone: without its logo and colours a switch changes the token set and nothing an administrator can see.

#### Scenario: A planned switch brings the set's logo and colours

@e2e exclude depends on the background job and core theming; proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testASwitchAppliesTheSetsThemingBothWays and ::testASwitchBackToStockResetsCoreTheming

- GIVEN a planned switch to `koningsdag-oranje` while `rijkshuisstijl` is active
- WHEN the switch starts
- THEN the Nextcloud primary colour MUST be the one `koningsdag-oranje` carries
- AND when the switch ends, the Nextcloud logo and colours MUST be those of `rijkshuisstijl` again

### Requirement: An administrator cancels a planned switch

An administrator MUST be able to cancel a planned switch that has not started, and a running switch, which then switches back at once. The endpoints MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]`.

#### Scenario: Cancelling a running campaign

@e2e exclude needs a running switch, which only the background job starts; proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testCancellingARunningCampaignSwitchesBackAtOnce and tests/vitest/admin-scheduled-switches.spec.js 'cancels a switch through DELETE and reloads the list'

- GIVEN a running switch to `koningsdag-oranje` that replaced `rijkshuisstijl`
- WHEN an administrator cancels it on Settings > Administration > Theming
- THEN the active token set MUST be `rijkshuisstijl`
- AND the switch MUST disappear from the list

#### Scenario: A non-admin cannot plan a switch

@e2e exclude an HTTP status, not DOM; proven by tests/Unit/Controller/ScheduledSwitchControllerTest.php::testEveryEndpointIsAdminOnly

- GIVEN a signed-in user who is not an administrator
- WHEN they call `POST /apps/thematiq/settings/scheduled-switches`
- THEN the response MUST be 403

### Requirement: The page warns when switches may run late

The "Planned switches" block MUST show when the scheduling job last ran, and MUST warn when Nextcloud's background job mode is AJAX, because planned switches then only run while someone uses the site.

#### Scenario: An administrator on AJAX cron is warned

@e2e exclude the CI instance runs cron mode, which cannot be switched for one spec; proven by tests/Unit/Service/ScheduledSwitchServiceTest.php::testStatusNamesTheLastRunTheCronModeAndTheActiveWindow and tests/vitest/admin-scheduled-switches.spec.js 'shows the last run and warns on AJAX cron'

- GIVEN a server whose background jobs run in AJAX mode
- WHEN an administrator opens the "Planned switches" block
- THEN the block MUST state that switches may start late and name the cron setting to change
