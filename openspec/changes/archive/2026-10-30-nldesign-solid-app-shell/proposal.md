# Proposal: nldesign-solid-app-shell

## Why

Nextcloud paints the app shell (`#content-vue`, NcContent) with `--color-main-background-blur`, a translucent
`rgba(255, 255, 255, .8)` meant to sit over a background image with a backdrop blur. The nldesign system leaves that
variable to Nextcloud, so every nextcloud-vue app under an nldesign set sits on a frosted shell over the plain page
colour. The drawn boards paint that surface solid: the AI chat button is measured against it on all 49 dossiq boards
(board `rgb(255, 255, 255)`, live `rgba(255, 255, 255, 0.8)`).

## What changes

The nldesign theme points `--color-main-background-blur` at `--color-main-background` outside the login page, the way
the high-contrast system already does. It follows Nextcloud's light and dark main background, so dark mode keeps
working.

## Reach

Every surface that reads `--color-main-background-blur` in an app (the NcContent shell, the app navigation where no
navigation token is set, menus that use the blurred surface) becomes solid. The login page keeps its frosted card.
