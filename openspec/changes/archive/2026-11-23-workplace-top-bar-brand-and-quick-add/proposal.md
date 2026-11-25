---
kind: code
---

# The top bar's brand block and Quick add

## Why

The DqKop, MlInbox, ClWeek and TbBoard boards draw the same top bar on every app: the set's emblem,
the organisation over the app's name and a thin divider at the start, and on Mail, Calendar and Deck a
round Quick add button before the bell. Both were built on 23 November 2026 (#1783, #1793, #1804,
#1806 and #1807) without a spec of their own; their code cites the general workplace-layout spec.
This change writes down what was built, so the boards, the code and the spec agree.

## What changes

- **Brand block.** With the workplace header, an app that draws no brand of its own (Files, Mail,
  Calendar, Deck and every other app) gets the brand block Conduction apps draw: a 237 px link to the
  app with the set's emblem, the organisation over the app's name, then a divider. An app's own brand
  wins. The block is drawn only once its stylesheet is on the page, and it is synced again only when
  the bar or an app's brand changes.
- **Fallback.** Until the block is drawn, Mail, Calendar and Deck show Nextcloud's logo and the app's
  name in the bar (`css/workplace-apps/brand.css`).
- **Quick add.** On Mail, Calendar and Deck a 44 px round button with a plus sits before the bell. It
  opens a menu with the apps menu's own Create actions, in the same order, fetched on the first open.

## Not in this change

- Any other app's Quick add: only the boards of Mail, Calendar and Deck draw it.
- New behaviour. Every task below is built and cites its pull request.

## Impact

- `js/header-brand.js`, `js/header-create.js`, `css/header-workplace.css`, `css/workplace-apps/brand.css`
- `lib/Service/HeaderUserService.php` loads both scripts with the workplace header
- l10n: "Quick add" and "Nothing to create here."
