## Why

Proof run 3 (9 October) measured the school portals' public pages against their boards. The page
titles render at the bridge's default 36px where the boards draw 44px (De Wilgenboom, Esdoornveen) or
48px (Vaartveld, Warmtepompacademie), on the content, article, search and sign-in pages and over the
own area. Text on the page is 16px where every board draws 17px, and the lead 20px where the boards
draw 21px. The academy's boards write the month under a date tile in capitals ("OKT"); the site
writes "okt".

## What Changes

- `css/tokens/{wilgenboom,vaartveld,esdoornveen,warmtepompacademie}.css`:
  `--nldesign-website-heading-1-font-size` (44px or 48px), `--nldesign-website-page-title-size`
  (2.75rem or 3rem), `--nldesign-website-content-font-size: 17px`,
  `--nldesign-website-lead-font-size: 21px`; the academy also
  `--nldesign-website-date-month-text-transform: uppercase`.
- `css/public-bridge.css`: `--thematiq-date-month-text-transform`, no fallback.
- `tests/vitest/publicBridgeRoleLayer.spec.js`: the schools' values; vng stays the control.

## Needs from portaliq

- `css/site-theme.css` reads `--thematiq-date-month-text-transform` on the date tile's month: change
  `site-home-follows-the-school-boards` (portaliq). Until then the token is inert.

## Impact

Public portals on the four school sets only. The Nextcloud workplace does not read the website
vocabulary.
