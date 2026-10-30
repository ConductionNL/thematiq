# cunningham-dashboard-card-header-rule Specification

## Purpose
The Cunningham layer (`css/systems/lasuite/element-overrides.css`, loaded by the `lasuite` and `cunningham` design systems) removes the rule under the header of a widget card on a dashboard page. Created by change `cunningham-dashboard-card-header-rule` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: Cunningham draws no rule under a dashboard card title.

## Requirements

### Requirement: Cunningham draws no rule under a dashboard card title

Under a design system built on Cunningham, a nextcloud-vue widget card on a dashboard page SHALL draw no rule under its
title. The Nextcloud dashboard chrome and chromeless cards are unchanged.

#### Scenario: A dashboard under the cunningham set

- **GIVEN** a nextcloud-vue dashboard under the `cunningham` token set
- **THEN** no card draws a line between its title and its content
