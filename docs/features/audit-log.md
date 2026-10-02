---
sidebar_position: 29
---

# See who changed the theme

An accessibility audit asks who changed what and when. The theming audit log records each change to the Thematiq configuration, with the person, the old value and the new value.

## Read the log

In the Thematiq admin settings, find **Theming audit log**. The table shows the most recent changes, newest first: **Timestamp**, **User**, **Action**, **From**, **To** and **Version**.

Click **Download full log** for the complete record. The file is `nldesign-audit.jsonl`, one JSON object per line, oldest first.

Only administrators can read or download the log.

## What gets recorded

Each entry names the person who made the change. A change from the command line shows `cli`. A change by a background job, such as a [planned switch](./plan-a-theme-switch.md), shows `system`.

Recorded changes include:

- switching the token set
- turning a toggle on or off
- writing or importing token overrides
- changing the per-app exclusions or the group mappings
- uploading or deleting a custom token set
- applying theming sync
- restoring an earlier version
- applying a planned switch

Two kinds of change do not reach the log today: saving freeform custom CSS and importing a [configuration bundle](./configuration-bundle.md). Keep your own record of those.

The log stores no full stylesheets. A CSS change is recorded as a short content hash plus its size.

## How long entries stay

The log lives in the app's data folder, outside the web root. When it passes 1 MB, Thematiq moves it aside and starts a new file. It keeps one older file, so the log stays around 2 MB in total. There is no way to edit or delete single entries.

A failed write to the log never blocks the change itself.

## Go back to an earlier state

Each entry with a version number can be restored. See [restore an earlier version](./restore-a-version.md).

Next, download the full log and keep it with your accessibility statement.
