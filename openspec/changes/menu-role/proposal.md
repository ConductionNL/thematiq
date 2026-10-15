---
kind: code
---

# The main menu's colour as a shell role

## Why

The OpenProject plugin has a `menu` role, the background of its main menu, written at
`thematiq.shell.menu`. The Figma round left it open on the thematiq side: the token was kept, but
nothing drew it and the endpoint did not answer it. Wave T of the plugin's parity map, and Ruben's
rule since 12 Oct 2026 (add, never remove), ask for it. It means something in Nextcloud: the app
navigation column, which the nldesign bundle paints with `--nldesign-color-nav-background`.

## What changes

- `ShellRoleMapper` has a nineteenth role, `menu`: `--thematiq-shell-menu`, then
  `--nldesign-component-navigation-background-color`, then `--nldesign-color-nav-background`, then
  white. The endpoint, the catalogue and the set file carry it. The other eighteen do not change.
- An upload that holds `--thematiq-shell-menu` and no navigation colour gets it as
  `--nldesign-color-nav-background` too, so the column takes it and the dark variant follows it.
