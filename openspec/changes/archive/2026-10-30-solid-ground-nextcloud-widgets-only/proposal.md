# Proposal: solid-ground-nextcloud-widgets-only

## Summary

The nldesign solid-background rule paints Nextcloud's own panels and widgets and nothing an app or nextcloud-vue draws.

## Why

The rule listed `[class*='widget']:not([class*='widget__']):not(#header *)`. With the ID inside `:not(#header *)` it scored (1,2,0), so no class rule in an app or in nextcloud-vue could set a different ground. It painted every element with "widget" in its class white: a borderless widget's wrapper and content, a greeting on the page ground, and `.cn-reports-page__widgets` on the board-look reports page. nextcloud-vue had to answer with `background: transparent !important`, and this app carried a second block that made the borderless cases transparent again.

The catch-all dates from the first setup of the app ("absolutely no transparency anywhere") and was meant for Nextcloud's dashboard panels and widgets.

## What changes

- `css/systems/nldesign/element-overrides.css`: the catch-all goes. The rule names Nextcloud's own widgets instead: `.widget`, `.dashboard-widget` and the reference widget cards `.widget-default`, `.widget-custom` and `.widget-access`, next to the panel selectors it had.
- The block that cleared borderless nextcloud-vue widgets goes with it: nothing paints them any more.
- `tests/vitest/widgetPartsKeepTheirColour.spec.js` checks which elements the rule reaches.
- `[class*='panel']` stays; it has the same shape and is a separate change.
