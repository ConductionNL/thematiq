# Proposal: floating-label-hides-placeholder

## Summary

A labelled field shows its label at rest and its placeholder only on focus, as Nextcloud draws it. The theme no longer draws both on top of each other.

## Why

Zuiddrecht live round 3 (14 Oct): on a dossiq case, the timeline filters showed the label "Soort" over the placeholder "Alle soorten". Nextcloud hides a placeholder under a resting floating label with `opacity: 0`. The nldesign theme and the high-contrast overrides set `::placeholder { opacity: 1 !important }`, which beat it in every labelled NcSelect and NcTextField.

## What changes

- `css/systems/nldesign/theme.css` and `css/systems/high-contrast/element-overrides.css` restore `opacity: 0` for the placeholder of a field with a resting floating label. The placeholder colour stays as the theme sets it.
- `tests/vitest/floatingLabelPlaceholder.spec.js` checks both files.
