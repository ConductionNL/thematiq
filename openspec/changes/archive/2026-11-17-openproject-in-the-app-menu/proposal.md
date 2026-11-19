---
kind: code
---

# OpenProject's modules in the apps menu

## Why

The OpenProject plugin lists Nextcloud's apps in a second column of its app switcher when an
administrator gives Nextcloud's address (its #12 and #61). Wave T of its parity map asks for the
other half: Nextcloud's apps menu with OpenProject's modules. Ruben's rule since 12 Oct 2026: add,
never remove.

## What changes

- App config `openproject_url` (an http or https address) and `openproject_modules` (keys, default
  `work_packages, gantt, boards, meetings`, the plugin's default row).
- The workplace apps menu (`js/app-menu.js`) gets a section "OpenProject" after Nextcloud's apps:
  links with an outline icon, the plugin's icons on the same 24 px grid.
- An admin block "OpenProject in the apps menu" with one address field, English and Dutch.

## Not in this change

- Asking OpenProject which modules exist or which ones a person may open. The plugin's list of
  Nextcloud apps is fixed in the same way.
- Nextcloud's own top bar: the section is part of the workplace menu thematiq arranges.
