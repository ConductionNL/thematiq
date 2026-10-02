---
status: done
---

# Extended Token Sets Specification

## Purpose
Expands the nldesign app from 5 manually maintained token sets to all available NL Design System organization token sets (48+), using auto-generation from official upstream JSON token files.

## Requirements

### Requirement: Support All Available Token Sets
The system MUST support all organization token sets available in the `nl-design-system/themes` repository.

#### Scenario: Organization with complete token set
- GIVEN an organization (e.g., Utrecht) has a complete token set
- WHEN an admin selects "Gemeente Utrecht" in the nldesign settings
- THEN the Nextcloud instance MUST render with Utrecht's brand colors, typography, and border radius

#### Scenario: Organization with incomplete token set
- GIVEN an organization (e.g., Groningen) has a partial token set that only defines font weights
- WHEN an admin selects "Gemeente Groningen" in the nldesign settings
- THEN the defined tokens (font weights) MUST use Groningen's values
- AND undefined tokens (primary color, border radius) MUST fall back to the defaults from `defaults.css`

#### Scenario: New organization added upstream
- GIVEN a new municipality adds their token set to the `nl-design-system/themes` repository
- WHEN the nightly sync workflow runs
- THEN a new CSS token file MUST be generated for that organization
- AND the `token-sets.json` manifest MUST be updated to include the new organization

@e2e exclude GitHub Actions schedule: .github/workflows/sync-tokens.yml clones the themes repository nightly, runs scripts/generate-tokens.mjs and opens a PR. No Nextcloud page or API takes part.

### Requirement: Auto-Generated Token CSS Files
Token CSS files MUST be auto-generated from the official JSON token files in the `nl-design-system/themes` repository, not manually curated.

#### Scenario: Token generation from JSON
- GIVEN the themes repository contains `proprietary/groningen-design-tokens/src/*.tokens.json`
- WHEN the generation script runs
- THEN it MUST produce `css/tokens/groningen.css`
- AND the CSS file MUST contain `:root` declarations with `--nldesign-*` prefixed variables

#### Scenario: Token naming conversion
- GIVEN a JSON token like `{ "groningen": { "color": { "primary": { "value": "#2e7d32" } } } }`
- WHEN the generation script converts it to CSS
- THEN the output MUST be `--nldesign-color-primary: #2e7d32;`

@e2e exclude Node script internals: the JSON-to-CSS name conversion runs inside scripts/generate-tokens.mjs (flattenTokens) on the themes checkout, and its input JSON never reaches the instance. The served output it produces is proven by the token-generation-from-json and organization-specific-palette-preservation tests.

#### Scenario: Organization-specific palette preservation
- GIVEN an organization defines additional palette colors (e.g., `haarlem.color.oceaan.10`)
- WHEN the generation script runs
- THEN organization-specific palette values MUST be preserved as `--{org}-color-*` (e.g., `--haarlem-color-oceaan-10`)
- AND standard mappable tokens MUST be converted to `--nldesign-*` prefix

### Requirement: Token Set Manifest
The system MUST maintain a `token-sets.json` manifest file that maps token set IDs to display names and descriptions.

#### Scenario: Admin views token set dropdown
- GIVEN the admin opens the nldesign settings page
- WHEN the token set dropdown is rendered
- THEN each option MUST show the organization's display name from `token-sets.json`
- AND the description from `token-sets.json` MUST be available to the admin page through `GET /settings/tokensets`

#### Scenario: Manifest is auto-updated
- GIVEN the generation script discovers a new organization
- WHEN it generates the CSS token file
- THEN it MUST also add an entry to `token-sets.json` with the organization name and description

@e2e exclude Node script output: scripts/generate-tokens.mjs writes token-sets.json during the nightly sync (.github/workflows/sync-tokens.yml). No Nextcloud page or API takes part.

### Requirement: Dynamic Token Set Discovery
The system MUST dynamically discover available token sets from the filesystem instead of using a hardcoded list.

#### Scenario: Token set validation
- GIVEN an admin submits a `setTokenSet` API request with `tokenSet=groningen`
- WHEN the controller validates the request
- THEN it MUST check that `css/tokens/groningen.css` exists on disk
- AND it MUST NOT use a hardcoded array of valid set names

#### Scenario: Available token sets API
- GIVEN a client requests the list of available token sets
- WHEN the catalogue `GET /apps/thematiq/api/token-sets` is called
- THEN the response MUST include all token sets that have a CSS file in `css/tokens/`
- AND each entry MUST include `id` and `name` from the manifest
- AND the admin listing `GET /apps/thematiq/settings/tokensets` MUST add the manifest `description` to every set it offers

### Requirement: Admin Settings Dynamic Dropdown
The admin settings page MUST build its dropdown dynamically from what is on disk, never from a hardcoded list. Which of those discovered sets are OFFERED is a separate decision — see the token-sets spec, "Only Fully Functional Brands Are Selectable" — and today that decision leaves `nextcloud` plus whatever the instance is already using. The requirement here is that the list is computed, not that it is complete.

#### Scenario: Settings page shows all token sets
- GIVEN 48 token set CSS files exist in `css/tokens/`
- WHEN the admin opens the nldesign settings
- THEN the dropdown MUST list every SELECTABLE set with its display name, resolved at request time from the discovered catalogue
- AND a set that is offered only because the instance is running it MUST appear, which is what distinguishes a computed list from a hardcoded one
- AND the currently selected token set MUST be highlighted
