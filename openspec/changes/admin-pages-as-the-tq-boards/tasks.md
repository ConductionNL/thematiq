# Tasks: admin-pages-as-the-tq-boards

Each task ends with a compdiff run of its board on the demo stack (English, admin) that pairs every
component and flags none, and the before and after PNGs in the PR. Board ids are
`thematiq/<Key>` on identity.conduction.nl/screens.

## 1. The page (TqTheming)

- [x] 1.1 Page header: title, breadcrumb, intro, actions (Preview in my session, Documentation). The board's third button is buildiq's inspect button, an app this page does not ship.
- [x] 1.2 Theme card around Nextcloud's own theming fields in the board's three-column grid. compdiff pairs the card and flags nothing. The card's words stay Nextcloud's own ("Theming", core's intro and field labels) and the Nextcloud chip and the board's helper lines under the fields are not drawn, because core's form has no hook for them without changing its markup. Core's extra controls (legal notice link, favicon, navigation bar logo, the navigation bar settings) stay inside the card, so it is taller than the board's.
- [x] 1.3 Thematiq heading with the environment chip and its line. The board draws no component for the row, so it is measured by position: title at the header's 292px edge in 22px bold, the line beside it in 14px grey. Without a declared environment (the demo stack) the occ command shows on a line under the row instead of the chip.
- [x] 1.4 Active token set card: picker, chips, Workplace / Login page tabs, swatches, sample, actions, switch line, formats note. compdiff pairs the card, its tabs, the swatches and the workplace sample and flags none of them. Not drawn: the contrast chip (thematiq shows a contrast problem as a warning, not a passing chip), the planned-switch line (it shows only while a switch is planned), and the board's Plan a switch button, which lives with the scheduled switches further down. The search field above the picker stays: the board has no search, but the picker of 60+ sets needs it.
- [x] 1.5 Token sets: search, Own token set, a table with Name, Design system, Primary colour, Layout and Status columns, filters, pills, row menu, Show more. compdiff pairs the card and the table and flags neither. The columns sort; the board's per-column filter buttons are not built (search covers name and design system), and the Planned status needs the scheduled switch in the set entry, so it is not shown. Own token set moves focus to the custom token set forms until TqNieuwThema (2.4) is built.
- [ ] 1.6 Layout, Website: lettering and Dark variant cards in two columns; the NL Design System link under them.

## 2. Dialogs

- [ ] 2.1 TqTokensetToepassen
- [ ] 2.2 TqThemingBijwerken
- [ ] 2.3 TqTerugNaarNextcloud
- [ ] 2.4 TqNieuwThema
- [ ] 2.5 TqOverridesOpslaan
- [ ] 2.6 TqBasistokens
- [ ] 2.7 TqOverridesUploaden
- [ ] 2.8 TqMerkenKiezen
- [ ] 2.9 TqSetUploaden
- [ ] 2.10 TqTokenToevoegen
- [ ] 2.11 TqTokenUitfaseren
- [ ] 2.12 TqVersieHerstellen

## 3. Pages and panels

- [ ] 3.1 TqTokenSet (a set from the inside)
- [ ] 3.2 TqComponenten (components and tokens, with the More menu)
- [ ] 3.3 TqEigenComponent
- [ ] 3.4 TqBeweging
- [ ] 3.5 TqLayout
- [ ] 3.6 TqDonker
- [ ] 3.7 TqEigenSets
- [ ] 3.8 TqEigenTokens
- [ ] 3.9 TqMerkPerApp
- [ ] 3.10 TqGroepen
- [ ] 3.11 TqLettertypen
- [ ] 3.12 TqEmailDocumenten
- [ ] 3.13 TqEigenCss
- [ ] 3.14 TqWissels
- [ ] 3.15 TqGalerij
- [ ] 3.16 TqBundel
- [ ] 3.17 TqContrastrapport
- [ ] 3.18 TqLogboek
- [ ] 3.19 TqAiAssistent
- [ ] 3.20 TqOmgevingsmarkering
- [ ] 3.21 TqVoorbeeldSessie
- [ ] 3.22 TqPersoonlijk and NcThema (the personal Appearance section)

## 4. Checks

- [ ] 4.1 A compdiff spec row per board state (route, user, steps) in the repo, so the next run measures the same states.
- [ ] 4.2 Playwright e2e per dialog and page: it opens, its actions work, Escape closes a dialog and gives focus back.
- [ ] 4.3 The admin page passes the WCAG 2.2 AA checks of `admin-panel-accessibility` after the rebuild.
