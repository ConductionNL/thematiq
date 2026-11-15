## Why

The Esdoornveen home board draws the photo beside the hero text with a slanted lower right corner. portaliq's hero aside (#1397) reads the corner from `--nldesign-hero-image-clip-path`, which is `none` unless a set names it. learniq now declares the photo place on the Esdoornveen hero (learniq #1856), so without the token the photo is a plain rectangle.

## What Changes

- `css/tokens/esdoornveen.css` names `--nldesign-hero-image-clip-path` with the board's polygon: `polygon(0 0, 100% 0, 100% calc(100% - 120px), calc(100% - 75px) 100%, 0 100%)` (school-design esdoornveen, Home).
- `tests/vitest/schoolTokenSets.spec.js` checks the value, and checks that the other three schools leave the token unset.

## Not in this change

- No dark override: the corner is not a colour.
