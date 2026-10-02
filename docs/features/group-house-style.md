---
sidebar_position: 18
---

# Let a group choose its own house style

Several organisations can share one Nextcloud, for example the municipalities of a shared service centre. Each group can get its own house style under Group theming on Settings > Administration > Theming. You can also hand that choice to the group itself, while every other group stays locked.

## Delegate a group

1. On Settings > Administration > Theming, open Group theming.
2. Add a mapping for the group, or use the one it has.
3. Tick Subadmins choose on that row.
4. In the list next to it, select the token sets the group may choose from. The group's current set must be one of them.
5. Save group theming.

Members keep seeing the current set until a subadmin picks another one. The order of the rows still decides which set wins for a user in more than one group.

## Who chooses

The subadmins of the group choose. A subadmin is the owner role Nextcloud already has per group: you appoint one under Accounts. A subadmin can change the set of their own delegated group, and nothing else. They cannot change the order, the allowed sets or another group.

## What a subadmin sees

A subadmin of a delegated group finds House style of my groups under Personal settings, Appearance and accessibility. Every delegated group they manage is listed with its allowed sets and the contrast result of each set. A choice is saved at once. Members see it on their next page.

Users who manage no delegated group do not see the block.

## Lock a group again

Untick Subadmins choose and save. The group keeps the set it has, so nobody's screen changes by surprise. The group disappears from the subadmin's list.

## Audit

Every choice by a subadmin is written to the theming audit log, with the subadmin as user, the old and new set, and the group.
