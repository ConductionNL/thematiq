## ADDED Requirements

### Requirement: The solid background reaches only Nextcloud's own panels and widgets

The nldesign solid-background rule SHALL paint Nextcloud's panels, its dashboard widget and its reference widget cards with `var(--color-main-background)`, and SHALL NOT select an element by a substring of its class name "widget". A widget drawn by an app or by nextcloud-vue keeps the ground it draws.

#### Scenario: A nextcloud-vue widget area keeps its own ground
@e2e exclude a cascade defect over the shipped stylesheet; tests/vitest/widgetPartsKeepTheirColour.spec.js matches every background selector against Nextcloud, nextcloud-vue and app fixtures, and the live check was made on :8080 with computed styles
- **GIVEN** the nldesign design system is active
- **WHEN** a page renders `.cn-reports-page__widgets`, a borderless `.cn-widget-wrapper`, a `.cn-stages-widget` or an app's `.dq-case-widget`
- **THEN** the solid-background rule SHALL NOT match them
- **AND** a `.dashboard-widget`, `.widget-default`, `.widget-custom`, `.widget-access` and `.panel` outside the header SHALL still get `var(--color-main-background)`
