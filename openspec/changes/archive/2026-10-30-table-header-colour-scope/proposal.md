# Proposal: table-header-colour-scope

## Why

`component-scopes.css` redirected `--color-text-maxcontrast` to `--nldesign-component-table-header-color` on every
`table`. So the header colour became the muted colour of every cell in every table: a case number or a meta line in a
row took the header's colour, the body ink in sets whose header colour is the text (the defaults' value), in all 48
token sets. The ARCH-AUDIT lane traced the boards' "muted text drawn in ink inside tables" to this rule.

## What changes

The table header colour token is a paint on `th` (`color: var(--nldesign-component-table-header-color, inherit)`), no
longer a table-wide redirect of the muted colour. Muted text in cells keeps the set's muted colour; header labels keep
the header colour. With the token unset a header cell inherits as before.

## Reach

Every set that sets or defaults the table header colour: muted cell text inside tables goes from the header colour back
to the set's muted colour, in every app that draws tables (Files, settings and admin tables, Conduction apps).
Header labels do not change.
