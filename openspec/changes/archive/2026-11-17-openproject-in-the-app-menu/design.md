# Design: openproject-in-the-app-menu

The section travels in the same initial state as the menu's own sections. The script adds the links
once per grid and keeps them out of the tile arrangement, so a second pass of the observer does not
reorder them as Nextcloud tiles. Nextcloud's scoped tile styles do not reach links thematiq adds,
so `css/header-workplace.css` gives them the tile layout.

Each module's path is OpenProject's global route (`/work_packages`, `/gantt`, `/boards`, `/meetings`,
`/projects`, `/my/page`, `/team_planners`, `/calendars`, `/news`). They were not checked against a
running OpenProject: this lane starts no OpenProject container.

Looked at live on a throwaway Nextcloud 35.0.1 with the Zuiddrecht set: the section sits after
Nextcloud's apps with its own heading, four tiles with their icons. The popover there starts 34 px
left of the window edge, with or without the section; that is not this change.
