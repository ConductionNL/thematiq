# Proposal: floating-label-hides-placeholder

## Summary

A labelled field shows its label at rest and its placeholder only on focus, as Nextcloud draws it. The theme no longer draws both on top of each other.

## Why

Zuiddrecht live round 3 (14 Oct): on a dossiq case, the timeline filters showed the label "Soort" over the placeholder "Alle soorten". Nextcloud hides a placeholder under a resting floating label with `opacity: 0`. The nldesign theme and the high-contrast overrides set `::placeholder { opacity: 1 !important }`, which beat it in every labelled NcSelect and NcTextField.

## What changes

- `css/systems/nldesign/theme.css` and `css/systems/high-contrast/element-overrides.css` restore `opacity: 0` for the placeholder of a field with a resting floating label. The placeholder colour stays as the theme sets it.
- `tests/vitest/floatingLabelPlaceholder.spec.js` checks both files.

## Follow-up (15 Oct)

The counter-rule left NcTextArea out (dossiq DqTaak notes field) and made nextcloud-vue add its own `opacity: 0 !important` under the board look. The opaque rule now skips a resting field with its label inside, text field and text area alike, so Nextcloud's own `opacity: 0` applies and no `!important` counter-rule is needed. The placeholder colour stays `!important` for contrast.
