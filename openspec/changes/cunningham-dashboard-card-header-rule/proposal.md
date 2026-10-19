# Proposal: cunningham-dashboard-card-header-rule

## Why

Ruben's decision: the line under dashboard card titles goes, as a Cunningham override, not as a library default.
Cunningham's cards carry title and body on one surface without a divider, and the boards drawn on it show none;
nextcloud-vue's widget card draws a 1px rule under its header.

## What changes

The Cunningham layer (`css/systems/lasuite/element-overrides.css`, loaded by the `lasuite` and `cunningham` design
systems) removes the rule under the header of a widget card on a dashboard page. The Nextcloud dashboard chrome and a
chromeless card draw none of their own and are left alone.

## Reach

Token sets on those systems: `lasuite`, `frankendesk` (extends lasuite) and `cunningham`. Every nextcloud-vue app's
dashboards under them lose the rule under card titles. Sets on the `nldesign` system whose palette follows Cunningham
(zuiddrecht, vaartveld, wilgenboom and others) do not load this layer and keep the rule, except where the board look
already removes it.
