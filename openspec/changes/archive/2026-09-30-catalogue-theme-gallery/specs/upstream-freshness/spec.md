# Spec delta: upstream freshness (upstream-freshness)

The freshness check is no longer the only outbound request; the gallery is the second, and both are opt-in.

## ADDED Requirements

### Requirement: The settings hint describes every outbound request truthfully

The upstream token updates hint MUST NOT claim that the freshness check is the only outbound request the app makes. It MUST name its own host and cadence, and the settings page MUST name every opt-in outbound request the app can make (the freshness check and the gallery), each with its host.

#### Scenario: An administrator reads which hosts the app can contact

- GIVEN an administrator on Settings > Administration > Theming
- WHEN they read the Upstream token updates block and the Gallery block
- THEN each block MUST name the host it contacts and that it is off by default
- AND no text on the page MUST claim a single outbound request
