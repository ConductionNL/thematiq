# Beta Cross-Surface Alignment

**Spec refs**: ADR-007 (i18n / localisation), app-icon convention
**Standards**: n/a (metadata/marketing consistency, not a runtime contract)

## ADDED Requirements

### Requirement: info.xml localisation

`appinfo/info.xml` MUST declare `<name>`, `<summary>`, and `<description>` as `lang="en"` /
`lang="nl"` pairs (per ADR-007), and the `lang="nl"` value MUST be a real Dutch translation, not
a copy of the English text.

#### Scenario: Dutch summary is a translation, not a copy

@e2e exclude manifest metadata, no page; covered by ClaimAccuracyTest::testManifestTextIsPairedInEnglishAndDutch

- GIVEN `appinfo/info.xml`
- WHEN `<summary lang="nl">` is read
- THEN its text MUST be grammatically Dutch and MUST NOT be byte-identical to
  `<summary lang="en">`

### Requirement: info.xml licence matches the actual license

`appinfo/info.xml`'s `<licence>` tag MUST match the license declared in `LICENSE`,
`composer.json`, and the SPDX headers on `lib/` PHP files.

#### Scenario: Licence tag matches SPDX headers

@e2e exclude manifest metadata, no page; covered by ClaimAccuracyTest::testManifestLicenceMatchesBundledLicence and ClaimAccuracyTest::testSpdxHeadersAgreeWithManifest

- GIVEN `appinfo/info.xml` declares `<licence>EUPL-1.2</licence>` (or the bare `eupl`)
- AND every PHP file under `lib/` carries `SPDX-License-Identifier: EUPL-1.2`
- AND `composer.json` declares `"license": "EUPL-1.2"`
- THEN the three MUST agree on EUPL-1.2 as the license

### Requirement: Cross-surface feature vocabulary agreement

The feature names used in `appinfo/info.xml`'s description, the `conduction.nl/apps/nldesign`
product page (EN + NL), and `docs/features.json` MUST refer to the same canonical feature list,
so a reader moving between the app store listing, the product page, and the docs recognises the
same feature under the same name.

#### Scenario: A shipped feature appears under the same name everywhere it is mentioned

@e2e exclude the product page lives in another repository (conduction-website); a copy review, not app behaviour

- GIVEN a feature exists in `docs/features.json` (e.g. "Custom Token Sets")
- AND the same feature is mentioned in `info.xml`'s description or the product page
- THEN the feature MUST be referred to with the same or an equivalent user-facing name in both
  places (e.g. "Custom token sets" / "eigen huisstijl"), not a different or contradictory name

### Requirement: Marketing/compliance claims are code-verified

Every claim on the product page (EN or NL) about a capability, compliance standard, or
configuration mode MUST be traceable to a shipped, testable behaviour in `lib/` or a documented
feature in `docs/`. A claim that cannot be traced to code MUST be corrected or removed before a
beta release.

#### Scenario: An unverifiable capability claim is removed

@e2e exclude the product page lives in another repository (conduction-website); a copy review, not app behaviour

- GIVEN the product page claims a capability (e.g. "switchable per user or organisation")
- WHEN no corresponding code path exists in `lib/` (e.g. no per-user or per-organisation theming
  toggle, only a per-app exclusion mechanism)
- THEN the claim MUST be corrected to describe the capability that actually exists
  (e.g. "per-app theming exclusion")

#### Scenario: A partially-implemented compliance claim is not asserted as complete

@e2e exclude the product page lives in another repository (conduction-website); a copy review, not app behaviour

- GIVEN `docs/reference/compliance.md` (or an equivalent verification artefact) records a
  standard as partially implemented (e.g. Rijkshuisstijl typography: font declared but webfont
  files not bundled)
- THEN the product page MUST NOT assert blanket compliance with that standard's full scope
  (e.g. MUST NOT say "government-compliant typography"); it MAY describe the part that is
  verified (e.g. "official NLDS type tokens")
