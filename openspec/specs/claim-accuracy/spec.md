# claim-accuracy Specification

## Purpose
What thematiq says about itself must be true: the licence in the manifest and in the government checklist, how fonts are delivered, the number of token sets, the scope of the token audit, Marianne's licensing and the Nextcloud versions CI really tests. Each claim is held to the file or test that proves it.
## Requirements
### Requirement: Manifest Licence Matches the Bundled Licence
`appinfo/info.xml` `<licence>` MUST declare the same licence family as the root `LICENSE` file and the SPDX headers of the PHP sources. The bundled `LICENSE` is the European Union Public Licence v1.2 and every `lib/**/*.php` file carries `SPDX-License-Identifier: EUPL-1.2`, so `<licence>` MUST be `eupl` and MUST NOT be `agpl`. The `<description>` MUST also state the EUPL-1.2 licence in prose, consistent with the rest of the Conduction fleet.

#### Scenario: Declared licence equals the bundled licence
@e2e exclude static metadata invariant — PHPUnit/grep asserts info.xml and LICENSE agree, not a UI flow
- GIVEN the shipped app package
- WHEN `appinfo/info.xml` `<licence>` is read
- THEN it MUST equal `eupl`
- AND the first line of `LICENSE` MUST contain "EUROPEAN UNION PUBLIC LICENCE"
- AND `<description>` MUST mention the EUPL-1.2 licence

#### Scenario: SPDX headers agree with the manifest
@e2e exclude static source invariant — PHPUnit scans lib/ headers
- GIVEN every PHP file under `lib/`
- WHEN its `SPDX-License-Identifier` tag is read
- THEN it MUST be `EUPL-1.2`
- AND no `lib/` PHP file MUST declare AGPL

### Requirement: Government Checklist States the Real Licence and Host
`docs/GOVERNMENT-FEATURES.md` — used by procuring organizations as a Programma van Eisen checklist — MUST state the real licence (EUPL-1.2) and the canonical source host (`github.com/ConductionNL/thematiq`). It MUST NOT state AGPL, and open-source / source-access rows MUST NOT reference Codeberg as the canonical repository.

This requirement previously named `codeberg.org/Conduction/nldesign` and forbade GitHub. Both halves of that were superseded: the app was renamed nldesign → thematiq, and Codeberg went from canonical host to unmaintained mirror, with GitHub the only host — including for issues. The requirement is inverted rather than dropped, because this checklist is a claim made to a procuring customer: naming a host the organisation no longer publishes to is precisely the class of false claim this capability exists to catch.

#### Scenario: Checklist licence and host are correct
@e2e exclude documentation invariant — checked against the shipped doc, not a UI flow
- GIVEN `docs/GOVERNMENT-FEATURES.md`
- WHEN the "Licentie" line and the open-source technical row are read
- THEN the licence MUST read EUPL-1.2 (never AGPL)
- AND the source-access reference MUST point at the GitHub repository (never Codeberg)

### Requirement: Font Delivery Documented as Bundled and Self-Hosted
Nextcloud enforces a strict Content-Security-Policy and government instances are frequently air-gapped, so the app MUST bundle Fira Sans as self-hosted `.woff2/.woff` files under `css/fonts/` and load them from `css/fonts.css` with app-relative `url()` only. Documentation MUST describe this delivery and MUST NOT instruct or claim that fonts load from an external CDN when the code contains no external font URL.

#### Scenario: Stylesheet uses only bundled fonts
@e2e exclude static asset invariant — PHPUnit inspects css/fonts.css and css/fonts/
- GIVEN `css/fonts.css`
- WHEN its `url()` references are inspected
- THEN none MUST use an `http://` or `https://` scheme
- AND every referenced `css/fonts/*.woff2` file MUST exist on disk

#### Scenario: Documentation matches the bundled delivery
@e2e exclude documentation invariant — checked against README/compliance docs
- GIVEN the font sections of `README.md` and `docs/reference/compliance.md`
- WHEN they describe how the font is delivered
- THEN they MUST describe self-hosted, app-bundled woff2 delivery
- AND they MUST NOT claim CDN/jsdelivr loading, nor that the font files are "not loaded"

### Requirement: Token-Set Count Claims Match the Inventory
Every human-readable statement of how many token sets ship — `appinfo/info.xml` `<description>`, `README.md`, `project.md`, and the documentation — MUST agree with the number of entries in `token-sets.json` (equivalently, the `css/tokens/*.css` inventory). A statement enumerating a subset MUST either enumerate accurately or state the true total.

#### Scenario: README count equals the inventory
@e2e exclude static inventory invariant — PHPUnit compares README count to token-sets.json length
- GIVEN the token-set count stated in `README.md`
- WHEN compared with the length of `token-sets.json`
- THEN the two MUST be equal
- AND `project.md` MUST NOT state a different total

### Requirement: Token Audit Scope Stated Honestly
`docs/reference/token-audit.md` MUST state which token sets have actually been reviewed and MUST NOT assert a blanket production verdict ("100/100", "APPROVED FOR PRODUCTION", "all token sets reviewed") that covers sets which were never audited. Sets that are community-derived and not individually reviewed MUST be labelled as unaudited.

#### Scenario: Audit doc scopes its verdict to reviewed sets
@e2e exclude documentation invariant — checked against the shipped doc
- GIVEN `docs/reference/token-audit.md`
- WHEN the audited scope is read
- THEN it MUST name the manually-reviewed sets (currently the original five)
- AND it MUST mark the remaining shipped sets as not individually audited
- AND no "APPROVED FOR PRODUCTION" verdict MUST be stated as covering the unaudited sets

### Requirement: Marianne Licensing Is Stated Honestly

Every human-readable statement about Marianne MUST describe its real, legally-restricted situation and MUST NOT overclaim or underclaim.
Marianne is a legally restricted French-State asset now bundled behind a gate, so `README.md`,
the relevant `docs/` page (font delivery / compliance), and any in-product notice
MUST state that Marianne is bundled self-hosted under the Etalab Open Licence
2.0 (from `@gouvfr/dsfr`), that it is the official typeface of the French State reserved for
French State administrations, that it is **off by default** and activated only when an admin
acknowledges the organisation is a French State agency, and that Inter is used otherwise. The
documentation MUST NOT claim Marianne is an unconditionally free/open font, and MUST NOT (as the
pre-change text did) assert that "no Marianne file exists anywhere in the app" now that the
gated files ship. Statements about font delivery MUST remain consistent with the
`### Requirement: Font Delivery Documented as Bundled and Self-Hosted` requirement — self-hosted,
app-relative, no CDN.

#### Scenario: Docs describe the gated, restricted Marianne accurately
@e2e exclude documentation invariant — checked against the shipped README/docs, not a UI flow

- GIVEN the Marianne sections of `README.md` and the font-delivery `docs/` page
- WHEN they describe Marianne
- THEN they MUST state it is bundled self-hosted under Etalab Open Licence 2.0, restricted to
  French State agencies, and off by default until an admin acknowledges eligibility
- AND they MUST NOT describe Marianne as an unconditionally free/open font
- AND they MUST NOT claim that no Marianne file ships in the app

#### Scenario: No claim that Marianne loads from an external host
@e2e exclude static asset invariant — PHPUnit inspects the Marianne stylesheet and docs

- GIVEN `css/systems/lasuite/marianne.css` and the documentation describing its delivery
- WHEN the `url()` references and the doc prose are read
- THEN none MUST use an `http://` or `https://` scheme
- AND the documentation MUST NOT claim CDN loading nor that Marianne is fetched from an external
  server

### Requirement: Declared Nextcloud Range Matches What CI Actually Tests

The `<nextcloud min-version>` declared in `appinfo/info.xml` is a promise the Nextcloud app store
passes on to administrators: it decides which servers are offered this app. That promise MUST be
backed by an exercised configuration. The declared floor MUST equal the LOWEST Nextcloud ref in
`nextcloud-test-refs` in `.github/workflows/code-quality.yml`, and `max-version` MUST be at least
the highest such ref.

Both directions are defects, and this repository has shipped each of them:

- **Floor above the matrix.** `min-version` is enforced at install time, so a floor higher than a
  tested ref makes `occ app:enable` refuse on that leg, and the failure surfaces as an unrelated
  "app is not installed or enabled" error much later in the run.
- **Floor below the matrix.** A floor lower than every tested ref advertises versions no leg has
  ever exercised. Nothing reports it, because a version that is never tested cannot go red.

#### Scenario: Declared floor equals the lowest tested Nextcloud ref
@e2e exclude manifest/CI invariant — PHPUnit reads `appinfo/info.xml` and the workflow, not a UI flow

- GIVEN `appinfo/info.xml` declares `<nextcloud min-version="X" max-version="Y"/>`
- AND `.github/workflows/code-quality.yml` pins `nextcloud-test-refs` to a JSON list of `stableNN`
  refs
- WHEN the declared range is compared with that list
- THEN no tested ref MUST be below `X`
- AND the lowest tested ref MUST equal `X`
- AND `Y` MUST be greater than or equal to the highest tested ref

#### Scenario: Declared PHP floor is satisfiable on the declared Nextcloud floor
@e2e exclude manifest invariant — PHPUnit reads `appinfo/info.xml`

- GIVEN `appinfo/info.xml` declares `<php min-version="8.3"/>`
- WHEN the declared Nextcloud floor is read
- THEN it MUST be at least 32, the first Nextcloud release whose own PHP floor is 8.3
- AND a lower Nextcloud floor MUST be treated as a contradiction between the two declarations,
  not as a wider support range

