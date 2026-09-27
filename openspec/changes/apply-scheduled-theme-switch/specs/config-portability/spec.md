# Spec delta: configuration portability (config-portability)

Planned switches are instance-wide configuration, so they travel in the bundle.

## ADDED Requirements

### Requirement: Planned switches travel in the bundle

The bundle MUST carry the planned switches as `config.scheduledSwitches`, with their token set, start, end and core sync option, and the change that adds them MUST bump `bundleVersion`. Import MUST validate every planned switch in phase 1 (token set exists in the bundle or on the target, windows do not overlap, times parse) and MUST write nothing when one fails. Runtime state (`revertTo`, `failed`) MUST NOT be exported.

#### Scenario: A campaign prepared on acceptance goes to production

- GIVEN an acceptance server with a planned switch to `koningsdag-oranje` and that custom set uploaded
- WHEN an administrator exports the bundle there and imports it on production
- THEN production MUST list the same planned switch
- AND production MUST hold the custom set it names

#### Scenario: An overlapping plan blocks the whole import

- GIVEN a bundle whose planned switches overlap
- WHEN an administrator imports it with `occ nldesign:config:import`
- THEN the command MUST exit non-zero, name the overlap and write nothing
