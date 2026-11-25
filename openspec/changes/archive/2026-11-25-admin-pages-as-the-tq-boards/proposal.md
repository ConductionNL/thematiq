---
kind: code
---

# The admin pages as the Tq boards draw them

## Why

Every capability the 36 thematiq boards name is built (`openspec/parity/capabilities.json`), but the
administration page is not drawn as the boards draw it. Measured on 23 November 2026 with compdiff on
the demo stack (`/settings/admin/theming` as admin, the boards in English): for 32 of the 36 boards
not one board component pairs with the live page, and TqBeweging, TqMerkPerApp, TqTokenSet and
TqWissels pair one component each. The page is one long column of
`templates/settings/admin.php` sections under Nextcloud's own Theming form, where the boards draw a
page header with actions, a set of cards (Theme, Active token set with its preview, Token sets as a
filterable table, Layout, Website lettering, Dark variant), dialogs for the confirmations and
sub-pages for a set, the components and tokens, the log and the other tools.

The boards are the UI canon, so the page follows them. This is a rebuild of the page's presentation,
not of its features: the controllers, services and stored settings stay as they are.

## What changes

- The Theming admin section becomes the page TqTheming draws: a page header "Themes and appearance"
  with breadcrumb, intro and the actions Preview in my session, Documentation and the menu; a Theme
  card holding Nextcloud's own name, web link, slogan, privacy link, colours, logo and background in
  the board's grid; the thematiq heading with the environment chip; the Active token set card with
  the set picker, the design system and contrast chips, the Workplace / Login page preview tabs, the
  swatch row, the live sample and the download and switch actions; Token sets as a table with
  search, filters, status pills and a row menu; and the Layout, Website: lettering and Dark variant
  cards side by side.
- The confirmations become dialogs as their boards draw them: TqTokensetToepassen,
  TqThemingBijwerken, TqTerugNaarNextcloud, TqNieuwThema, TqOverridesOpslaan, TqBasistokens,
  TqOverridesUploaden, TqMerkenKiezen, TqSetUploaden, TqTokenToevoegen, TqTokenUitfaseren,
  TqVersieHerstellen.
- The tools that the long page lists one under the other become the pages and panels their boards
  draw: TqTokenSet, TqComponenten, TqEigenComponent, TqBeweging, TqLayout, TqDonker, TqEigenSets,
  TqEigenTokens, TqMerkPerApp, TqGroepen, TqLettertypen, TqEmailDocumenten, TqEigenCss, TqWissels,
  TqGalerij, TqBundel, TqContrastrapport, TqLogboek, TqAiAssistent, TqOmgevingsmarkering,
  TqVoorbeeldSessie, and the personal Appearance section of TqPersoonlijk and NcThema.
- Every board is measured with compdiff against its live state; a board is done when compdiff pairs
  every component and flags none, apart from demo data.

## Not in this change

- New features. A board element whose feature thematiq does not have is listed in the task of its
  board and left out.
- The werkplek boards (top bar, apps menu, Menu settings, login): those follow their boards already
  (thematiq #1838 and its follow-ups).

## Impact

- `templates/settings/admin.php`, `templates/settings/group-house-style.php`, `css/admin.css`,
  `js/admin*.js` and new dialog and page scripts under `js/`.
- `lib/Settings/Admin.php` and `lib/Settings/AdminPageState.php` for the initial state of the page.
- Playwright e2e per board state, compdiff specs per board (route, user, steps).
