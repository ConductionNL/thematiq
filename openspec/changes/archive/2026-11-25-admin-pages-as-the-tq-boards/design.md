# Design: admin-pages-as-the-tq-boards

## Decision: the Tq pages are panels of the one admin page

Decided 24 November 2026 by the coordinator of the admin lane.

Each Tq page or panel (TqLayout, TqDonker, TqTokenSet, TqComponenten and the others under task 3) is a
panel inside the one Theming administration page, `/settings/admin/theming`. It is addressed by a URL
hash: `#layout`, `#dark` and so on.

- A card link opens it: All options on the Layout card opens `#layout`, View and adjust on the Dark
  variant card opens `#dark`.
- While a panel is open, the overview's cards are hidden and the panel takes their place. A back link
  above the panel returns to the overview, and so does the breadcrumb's Themes and appearance.
- The page header stays. It shows the panel's title, breadcrumb, line and actions while the panel is
  open, and its own again when the panel closes.
- The browser's back button works, because opening a panel is a change of hash. Every panel can be
  deep-linked: a page opened with `#layout` opens on that panel.
- There are no new routes and no new settings sections. The controls a panel shows are the page's own
  controls, so a panel saves exactly as the overview does.

`js/admin-panels.js` implements this (`window.ThematiqPanels`). A panel is a
`<section class="thematiq-panel" data-thematiq-panel="<hash>" hidden>` in `templates/settings/admin.php`;
a slot `data-thematiq-slot="<control id>"` in it borrows that control's option from the overview while the
panel is open and gives it back when the panel closes.

Why: the boards draw these pages as separate screens of the Theming settings, while Nextcloud gives an app
one settings section per area. A hash keeps everything on the page the admin already has open, with its
state and its scripts loaded once, and still gives each page a link of its own.

## Decision: the overview links the pages that have no card

Decided 24 November 2026 in the admin lane. TqTheming draws card links only for Layout and Dark variant;
the other pages are reached from one another on the boards (TqTokenSet leads to TqComponenten, TqEigenCss to
TqLogboek). So that every panel can be reached from the overview, the overview ends with a More settings
card that links each page without a card of its own. A section that becomes a panel leaves the overview's
long column; the card links it instead.
