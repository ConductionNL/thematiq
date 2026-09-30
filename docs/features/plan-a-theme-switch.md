---
sidebar_position: 18
---

# Plan a theme switch

Plan a campaign week, King's Day or a quiet remembrance period ahead of time. You pick the token set and the times, and the switch happens on its own. With an end time, the token set you had before comes back by itself.

## Plan a switch

Open Settings > Administration > Theming and find **Planned switches**, under the token set dropdown.

1. Choose the token set.
2. Enter the start date and time. Times are in your own time zone; the page names it.
3. Enter an end date and time if the look should stop again. Leave it empty to keep the new token set.
4. Tick **Also update the Nextcloud logo and colours** if Nextcloud's own logo and primary colour should follow the token set. Without it, only the token set changes.
5. Choose **Plan switch**.

The list shows every planned switch with its times. A switch that is running says so, and the block names the token set that comes back at the end.

Two planned switches cannot overlap. When they do, the page names the switch that is in the way, and you change the times or cancel that one first.

## Cancel a switch

Choose **Cancel** next to the switch. A switch that has not started disappears. A switch that is running switches back at once, to the token set it replaced.

## What happens at the start and the end

A background job checks every five minutes. At the start it switches to the planned token set; at the end it switches back. Users see the new look on their next page load. Each start and each end leaves a `scheduled_switch_applied` entry in the theming audit log, with actor `system`.

When the planned token set no longer exists at the start, for example a custom set someone deleted, nothing changes. The switch shows as failed in the list, with the reason, and the audit log records it.

## Background jobs must run on cron

A planned switch only starts when Nextcloud runs its background jobs. In AJAX mode that happens only while someone uses the site, so a switch can start late. The block warns you when that is the case and says when the job last ran. Choose **Cron** under Administration settings > Basic settings > Background jobs.

## Moving plans between servers

Planned switches travel in the configuration bundle, next to the token set and the custom sets. Prepare a campaign on acceptance, export the bundle there and import it on production. An import with overlapping switches, or a switch to a token set neither the bundle nor the server has, is refused whole and changes nothing.
