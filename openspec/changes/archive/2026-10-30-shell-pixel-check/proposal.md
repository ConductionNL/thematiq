---
kind: code
---

# A pixel check of the shell

## Why

The OpenProject plugin has a pixel check of its shell since its #26: the top bar and the sidebar,
compared with reference images, to notice when an OpenProject release moved something. Wave T of
its parity map asks for one in thematiq: the shell is drawn by class names Nextcloud can change in
any release too. Ruben's rule since 12 Oct 2026: add, never remove.

## What changes

- `tests/e2e/visual/shell.visual.spec.ts` in the opt-in visual project: Files at 1440 x 1000 with
  the Zuiddrecht set; the top bar (0, 0 to 1440, 68) may differ in 0.5% of its pixels and the
  navigation (0, 68 to 264, 1000) in 1%, a pixel counting as different at about 24 of 255, as in the
  plugin. The storage line and the notification dot are masked.
- Reference images made on 14 Oct 2026 on Nextcloud 35.0.1, development of that day.

## Not in this change

Wiring it into CI. The visual project is opt-in because references are host specific (fonts, GPU);
see `tests/e2e/visual/README.md`.
