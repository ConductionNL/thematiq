# token-set-licences

## ADDED Requirements

### Requirement: Every shipped organisation theme declares its licence

Every shipped set converted from a published theme (a manifest entry with `upstreamRef` or
`upstreamPackage`) MUST declare its licence. A set whose source licence limits use of the house style
to its owner MUST declare `restricted: true` and ship the licence text as found in its source. A set
whose source has no licence file but sits among proprietary themes, or has no published source, MUST
be declared restricted, with a text that says so. `docs/reference/token-set-licences.md` MUST list
every declared set with its source package, version, source and finding.

@e2e exclude manifest audit — what the release ships is read from its files; proven by tests/Unit/ShippedSetLicencesTest.php.

#### Scenario: A converted set without a declaration fails the build

- GIVEN a manifest entry with `upstreamRef` and no `licence`
- WHEN the unit suite runs
- THEN it fails and names the set

#### Scenario: Epe ships its licence text

- GIVEN the shipped set `epe`
- WHEN its licence is read
- THEN it is restricted, names `@nl-design-system-unstable/epe-design-tokens` version 1.0.3 and holder Gemeente Epe
- AND `css/tokens/epe.LICENSE.md` holds the text of that package

#### Scenario: The token sync records the version it converted in the licence

- GIVEN the shipped set `epe` declares its licence at version 1.0.3
- WHEN the nightly token sync converts `epe` from version 1.1.0 of its package
- THEN the set's `upstreamVersion` and its `licence.version` both read 1.1.0
- AND the rest of the licence declaration stays as it was

### Requirement: An upgrade records every restricted set that is already applied

On upgrade a repair step MUST find every restricted set that is applied without a confirmation (for
the instance, a group, an app's brand, or an open planned switch), keep it applied, record per set
that it needs a confirmation and since when, and write one log warning per newly recorded set. Running
it again MUST keep the first date and warn no more. A confirmation MUST end the record. A failure MUST
NOT fail the upgrade.

@e2e exclude upgrade — needs an upgrade of an instance that applied the set before; proven by tests/Unit/Repair/RecordRestrictedSetsAwaitingConfirmationTest.php and by `occ upgrade` on a Nextcloud 34 instance.

#### Scenario: An instance on Epe keeps Epe after the upgrade

- GIVEN `epe` is the active token set and nobody confirmed it
- WHEN the instance upgrades to this version
- THEN `epe` is still the active token set
- AND the upgrade log names `epe`
- AND the admin settings show that `epe` is in use without a confirmation, with the confirm action

#### Scenario: A second upgrade changes nothing

- GIVEN an upgrade recorded `epe` on 14 October
- WHEN the instance upgrades again
- THEN the record still says 14 October and no new warning is written
