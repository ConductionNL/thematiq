# Proposal: status-badge-neutral-tone

## Why

A status pill without a status colour (the badge's default tone: a priority, an on/off state, a status with no colour)
draws Nextcloud's dark background with the main text: #EDEFF2 with #1B1C1D under Zuiddrecht. The boards draw it as the
grey tint with the grey pill text, #F0F1F3 with #3D4047, the same pair the workplace sets already declare as
`--nl-color-grey-light` and `--nl-color-grey-text` (COMPDIFF r19b: Normaal, Uit and the other neutral pills on the Taken,
Instellingen and list boards). The status-badge scope re-pointed the four coloured tones but not the neutral one.

## What changes

The status-badge component gains a neutral pair, `--nldesign-component-status-badge-neutral-background-color` and
`--nldesign-component-status-badge-neutral-color`, which re-point `--color-background-dark` and `--color-main-text`
inside `.cn-status-badge`. A set without them renders as before. The five workplace sets declare the boards' grey tint and
pill text (9.19:1); the dark variants are regenerated and the pair is held at 4.5:1 there.
