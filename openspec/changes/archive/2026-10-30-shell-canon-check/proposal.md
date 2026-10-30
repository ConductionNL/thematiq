---
kind: code
---

# The shell measured against the canon

## Why

The OpenProject plugin is built to a written canon of shell sizes (`spike/OP-TWIN-canon.md`,
measured from the Nextcloud boards) and measures OpenProject against it. Its parity map (wave T)
notes that no test in thematiq measures Nextcloud against the same canon. Ruben's rule since
12 Oct 2026: add, never remove.

## What changes

- `tests/css/check-shell-canon.mjs` (`npm run check:shell-canon -- --url … --user … --password …`)
  opens Files at 1440 x 1000 and measures the top bar, the navigation, a navigation item, the
  primary button, the table header row, a table row and the body text, against the canon.
- A difference thematiq draws today is recorded in `KNOWN` with its size. The check fails when a
  part leaves the canon, when a recorded gap moves, or when a gap closes and is still recorded.
- `tests/e2e/spec-coverage/shell-canon.spec.ts` runs it in CI on the Zuiddrecht set and puts the
  active set back.

## Measured on 14 Oct 2026

Nextcloud 35.0.1, development `99006149`, Zuiddrecht set: top bar 68, navigation 264, navigation
item 42 and table header row 44 match the canon. Three differ: the primary button is 48 (canon 40),
a table row 44 (canon 48), the body text 15 px (canon 14). Whether thematiq moves to the canon or the
canon to thematiq is not decided here.
