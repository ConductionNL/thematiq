# Spec delta: dark mode (dark-mode)

A shipped logo that cannot be read on a dark surface gets a dark variant, and that variant reaches the generated dark stylesheet.

## ADDED Requirements

### Requirement: Shipped Dark Logo Variants

A shipped token set whose `theming.logo_dark` is set MUST have that file under `img/logos/`, and the set's committed dark stylesheet `css/tokens/dark/{id}.css` MUST override `--nldesign-logo-url` with it. Every fill colour in a shipped dark logo MUST reach 3:1 against the dark background `#141414` (WCAG 1.4.11). The Epe set MUST ship one, because its light logo draws its letters in near black on a transparent background.

#### Scenario: A user in dark mode sees the Epe logo in light ink

- GIVEN the Epe token set is active and dark variants are enabled
- WHEN a user whose system is in dark mode opens any page
- THEN the dark stylesheet for Epe MUST set `--nldesign-logo-url` to `img/logos/epe-dark.svg`
- AND that file MUST be served with status 200
