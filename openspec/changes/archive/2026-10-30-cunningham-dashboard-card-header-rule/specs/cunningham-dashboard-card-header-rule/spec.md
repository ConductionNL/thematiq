# cunningham-dashboard-card-header-rule Delta

## ADDED Requirements

### Requirement: Cunningham draws no rule under a dashboard card title

Under a design system built on Cunningham, a nextcloud-vue widget card on a dashboard page SHALL draw no rule under its
title. The Nextcloud dashboard chrome and chromeless cards are unchanged.

#### Scenario: A dashboard under the cunningham set

- **GIVEN** a nextcloud-vue dashboard under the `cunningham` token set
- **THEN** no card draws a line between its title and its content
