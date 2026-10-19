# Proposal: zuiddrecht-table-header-board-palette

## Why

The workplace boards (DqZaken, DqContacten, DqTaken; COMPDIFF F017, F019, F049) draw a table's header row near-white
(#FAFBFC) with muted labels (#5E6168) at weight 600. Zuiddrecht set no table header tokens, so it took the defaults:
the background-dark band (#EDEFF2) with bold body ink. And because `component-scopes.css` hands the table header
colour to `--color-text-maxcontrast` inside every table, every muted line in a table cell (a case number, a meta line)
drew in the body ink too.

## What changes

Zuiddrecht's token file sets the table header to #FAFBFC, the set's muted grey (#5E6168) and weight 600; the dark
variant is regenerated from it (#999CA3 on #12181D, 6.51:1). Muted text in tables is the set's muted grey again.

## Not changed

The board's field border, #C4C7CB, is 1.70:1 on white, under the 3:1 WCAG 1.4.11 asks of a control's boundary. The
set keeps #8A929E (3.14:1) for `--color-border-dark`.

## Reach

Every table under the Zuiddrecht set changes: the header row of a table in any app (Files' list header, admin and
settings tables, other Conduction apps' tables) goes from the grey bold band to the near-white muted row, and muted
text inside tables turns from ink to grey. Other sets do not change.
