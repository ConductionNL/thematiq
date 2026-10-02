---
sidebar_position: 21
---

# Give each group its own house style

Several organisations can share one Nextcloud and still each see their own house style. Map a Nextcloud group to a token set, and members of that group get that set.

## Add a mapping

1. Open **Administration settings**, then **Theming**. Under **Thematiq**, find **Group theming**.
2. Click **Add mapping**. Pick a group and a token set.
3. Put the rows in order. The top row has the highest priority.
4. Click **Save group theming**.

The mapping takes effect on the next page a user opens. Thematiq refuses the whole save when a group or token set does not exist, or when one group appears twice.

## Which set a user gets

Thematiq picks one token set per page, in this order:

1. An admin who is [previewing a theme](./theme-preview.md) sees the preview.
2. A signed-in user gets the set of the first row whose group they belong to.
3. Everyone else gets the instance token set.

A user in two mapped groups gets the higher row. A row whose token set was deleted is skipped. Pages without a signed-in user always show the instance token set: the login page, public shares and error pages.

If the group lookup fails, Thematiq falls back to the instance token set. The house style is presentation, so a failure never blocks a page.

## What stays the same for everyone

Group theming changes only the token-set stylesheet. These follow the instance token set for every user:

- the logo and background image in Nextcloud's own theming
- the colours that [theming sync](./theming-sync.md) writes to Nextcloud
- the [email template](./email-theming.md)

[Theming per app](./toggles.md) still applies first. An app you excluded stays unthemed for every group.

## How quickly changes land

Saving a mapping applies on the next request. When you add a user to a group, the change shows within an hour at most, because Thematiq caches each user's set for up to an hour.

Group mappings are not part of the [configuration bundle](./configuration-bundle.md). Set them by hand on each server.

Next, try a group's set on yourself first with a [theme preview](./theme-preview.md).
