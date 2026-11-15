# Proposal: panel-background-narrow

## Why

The nldesign element overrides give Nextcloud's panels a solid ground through `[class*='panel']:not(#header *)`, which
carries ID-level specificity. The substring caught nextcloud-vue's index page with a side panel
(`cn-index-page--with-side-panel`) and painted the whole page the card colour over the workspace ground (dossiq's
queue, DqTeamwachtrij). The same rule already leaves the library's widgets out for this reason.

## What changes

The catch-all skips elements with a `cn-` class. Nextcloud's own `.panel` and widgets keep their solid ground.

## Reach

nextcloud-vue pages and components whose class contains "panel" stop being painted by the theme: index pages with a
side panel, `cn-index-page__side-panel`, `cn-spend-analytics-widget__panel`, `cn-email-picker__panel` and similar. They
draw their own ground. No Nextcloud app changes.
