# Tasks: admin-pages-as-the-tq-boards

Each task ends with a compdiff run of its board on the demo stack (English, admin) that pairs every
component and flags none, and the before and after PNGs in the PR. Board ids are
`thematiq/<Key>` on identity.conduction.nl/screens.

## 1. The page (TqTheming)

- [ ] 1.1 Page header: title, breadcrumb, intro, actions (Preview in my session, Documentation, menu).
- [ ] 1.2 Theme card around Nextcloud's own theming fields in the board's three-column grid, with the Nextcloud chip.
- [ ] 1.3 Thematiq heading with the environment chip and its line.
- [ ] 1.4 Active token set card: picker, chips, Workplace / Login page tabs, swatches, sample, actions, switch line, formats note.
- [ ] 1.5 Token sets: search, Own token set, a table with Name, Design system, Primary colour, Layout and Status columns, filters, pills, row menu, Show more.
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
