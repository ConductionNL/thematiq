---
status: done
---

# Token Sync Workflow Specification

## Purpose
Automates the synchronization of NL Design System token sets from the upstream `nl-design-system/themes` repository via a nightly GitHub Actions workflow that generates CSS token files and opens PRs when changes are detected.

@e2e exclude GitHub Actions / CI workflow spec — all scenarios describe scheduled workflow execution, PR creation, and token generation scripts; no Nextcloud admin UI surface.
## Requirements
### Requirement: Nightly Schedule
The sync workflow MUST run automatically every night to check for upstream token changes.

#### Scenario: Scheduled execution
- GIVEN the GitHub Actions workflow `sync-tokens.yml` is configured
- WHEN the cron schedule triggers (daily at 3 AM UTC)
- THEN the workflow MUST clone the `nl-design-system/themes` repository
- AND it MUST run the token generation script

#### Scenario: Manual trigger
- GIVEN a maintainer wants to sync tokens immediately
- WHEN they trigger the workflow manually via `workflow_dispatch`
- THEN the workflow MUST execute the same steps as the nightly run

### Requirement: Change Detection
The workflow MUST detect whether upstream token changes result in different CSS output before creating a PR.

#### Scenario: No upstream changes
- GIVEN the upstream token files have not changed since the last sync
- WHEN the generation script runs and produces identical CSS output
- THEN the workflow MUST NOT create a PR
- AND it MUST exit successfully

#### Scenario: Upstream changes detected
- GIVEN the upstream token files have changed
- WHEN the generation script produces different CSS output
- THEN the workflow MUST create a PR with the updated token files

#### Scenario: New organization added upstream
- GIVEN a new organization directory appears in the themes repository
- WHEN the generation script runs
- THEN a new CSS token file MUST be generated
- AND `token-sets.json` MUST be updated with the new organization
- AND the PR MUST include both the new CSS file and the updated manifest

### Requirement: PR-Based Updates
Token updates MUST be delivered as pull requests, not direct commits, to allow review before merging.

#### Scenario: PR creation
- GIVEN the generation script produced changed CSS output
- WHEN the workflow creates a PR
- THEN the PR title MUST be `chore: sync NL Design System tokens`
- AND the PR body MUST describe which token sets were added or changed
- AND the PR MUST be created on a branch named `chore/sync-nldesign-tokens`

#### Scenario: Existing open PR
- GIVEN a sync PR from a previous run is still open
- WHEN a new sync run detects additional changes
- THEN the workflow MUST update the existing branch and PR rather than creating a duplicate

### Requirement: Token Generation Script

The system MUST include a generation script that converts upstream JSON token files to CSS
custom property files. For every upstream-generated entry, the script MUST additionally
record provenance into `token-sets.json`: `upstreamVersion` (the version of the upstream
theme package the tokens were generated from, when the upstream package declares one) and
`upstreamRef` (the commit SHA of the `nl-design-system/themes` checkout being processed,
provided to the script by the sync workflow). Provenance MUST only be written for entries
the script actually (re)generates; hand-authored entries MUST remain untouched. These
fields are the comparison baseline for the deployed instances' upstream-freshness check
(`upstream-freshness` spec).

#### Scenario: Script reads upstream tokens

- GIVEN the themes repository is cloned to a local path
- WHEN `node scripts/sync-upstream-tokens.mjs /path/to/themes` is executed
- THEN the script MUST process all directories under `proprietary/` that contain token files
- AND the script MUST output converted CSS files to `css/tokens/`

#### Scenario: Script handles malformed input

- GIVEN an upstream token JSON file contains invalid JSON
- WHEN the generation script encounters it
- THEN the script MUST log a warning for that organization
- AND it MUST continue processing other organizations
- AND it MUST NOT overwrite the existing CSS file for that organization
- AND it MUST NOT update that organization's provenance fields (stale tokens keep their
  stale provenance so the freshness check still reports them as outdated)

#### Scenario: Script updates manifest

- GIVEN the script processes all upstream organizations
- WHEN it finishes generating CSS files
- THEN it MUST update `token-sets.json` with the complete list of processed organizations
- AND it MUST preserve any manually added metadata (descriptions, display names)
- AND each regenerated entry MUST carry `upstreamRef` set to the processed themes-repo
  commit SHA
- AND each regenerated entry MUST carry `upstreamVersion` when the upstream theme package
  declares a version, and omit the field otherwise
- AND entries not generated from upstream (e.g. `nextcloud`, `summer-breeze`) MUST NOT
  receive provenance fields

#### Scenario: Workflow provides the upstream commit SHA

- GIVEN the sync workflow has cloned `nl-design-system/themes`
- WHEN it invokes the generation script
- THEN it MUST pass the checkout's commit SHA to the script (argument or environment)
- AND the recorded `upstreamRef` MUST equal that SHA

### Requirement: Converted and gated sync

The sync MUST convert upstream themes onto the nldesign vocabulary through the theme
converter (`js/lib/tokenConverter.js`), MUST NOT write a set that was not generated from
upstream, and MUST NOT open a PR unless the shipped token-set tests pass. The workflow runs
`scripts/sync-upstream-tokens.mjs`; the gate is `scripts/token-set-gate.sh`.

#### Scenario: Hand-resolved set is never overwritten

- GIVEN `css/tokens/zwolle.css` was resolved by hand and carries neither the raw-sync header
  nor the converter provenance naming `nl-design-system/themes`
- WHEN upstream ships a `zwolle-design-tokens` theme
- THEN the sync MUST leave `css/tokens/zwolle.css` and its manifest entry unchanged
- AND the PR body MUST list it as not touched

#### Scenario: Local overrides survive a conversion

- GIVEN an upstream-managed set declares values upstream does not produce (for example
  `--denhaag-*` colours or a footer band chosen for contrast)
- WHEN the sync converts the set again
- THEN those declarations MUST be kept in the set's local overrides section

#### Scenario: A set that fails the gate is left unchanged

- GIVEN a converted set makes a shipped token-set test fail
- WHEN the sync runs the gate
- THEN that set MUST be restored exactly as committed
- AND the PR body MUST name it with the failing tests
- AND a new organisation MUST only be added when its conversion is vocabulary complete

#### Scenario: Upstream publishes a Tokens Studio export

- GIVEN an organisation upstream has no `src/**/*.tokens.json` but has `figma/*.tokens.json`
- WHEN the sync runs
- THEN it MUST merge the export's token sets in their `tokenSetOrder`, without the dark colour scheme
- AND convert and gate the result like any other theme
- AND a new organisation MUST NOT join the vocabulary allow-list or bring a logo

#### Scenario: Generated docs follow the sets

- GIVEN the sync changed at least one set
- WHEN it finishes
- THEN dark variants, `docs/reference/contrast-report.md` and the token reference pages MUST be
  regenerated and committed in the same PR

#### Scenario: A red tree opens no PR

- GIVEN the shipped token-set tests fail on the tree the sync would commit
- WHEN the workflow reaches its verification step
- THEN the job MUST fail before any branch is pushed or PR opened

### Requirement: README Sources Section
The README MUST document the token sync architecture and link to upstream sources.

#### Scenario: Developer reads README
- GIVEN a developer opens the nldesign README
- WHEN they look for information about token sources
- THEN they MUST find links to the NL Design System themes repository
- AND they MUST find links to the NL Design System design tokens handbook
- AND they MUST find an explanation of how the nightly sync workflow operates
- AND they MUST find instructions for adding a new token set manually

