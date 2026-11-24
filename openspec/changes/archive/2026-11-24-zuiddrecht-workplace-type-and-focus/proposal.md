---
kind: code
---

# Zuiddrecht's workplace type scale and focus ring

## Why

The DqTokens board ("Werkplek-laag: Zuiddrecht op Cunningham") fixes the workplace's type scale and
its focus ring: page title 28, card title 17, text 15 (Nextcloud's default) and meta 13 in grey
#5E6168, and "Focus: 2 px blue border, always visible". The Zuiddrecht set declares the palette, the
radius scale and the card shadow of that board, but not the two heading sizes, so pages and cards take
the nldesign defaults (40 px and 24 px). The focus colour is the action blue at 50 % opacity, which
reads as a soft halo, not the board's ring.

## What changes

- The Zuiddrecht set declares the page title at 28 px and the card title at 17 px, and keeps
  Nextcloud's 15 px text and 13 px small text.
- In the light workplace under Zuiddrecht, a control with keyboard focus shows a 2 px ring in the
  action blue #3669A5, at least 3:1 against white and the grey workspace.

## Not in this change

- Any other set's type scale or focus ring.
- The board's colours, radii and card shadow: the set declares them already
  (`zuiddrecht-token-set`).

## Impact

- `css/tokens/zuiddrecht.css`, possibly `css/workplace-layout.css` for the ring
- `tests/Unit/TokenSetVocabularyTest.php` or a vitest that reads the set
