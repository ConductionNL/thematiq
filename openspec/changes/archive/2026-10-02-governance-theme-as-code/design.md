# Design: theme as code

## Where it fits (development b4e7568)

- `lib/Service/ConfigBundleService.php:241` `export()` and `:309` `import(array $bundle, bool $dryRun)`: validate every section, then write; any hard failure writes nothing. Fonts are exported as metadata only and never applied on import (`:50-57`, `binariesIncluded => false` at `:262` and `:865`).
- `lib/Controller/ConfigBundleController.php:56` caps a web upload at 256 KB, which is why fonts were left out.
- `lib/Command/ConfigExport.php:69` `thematiq:config:export [file]` and `lib/Command/ConfigImport.php:73` `thematiq:config:import <file> [--dry-run]`; `openspec/specs/config-portability/spec.md:93-126` requires both to reuse `ConfigBundleService`.
- `lib/Service/FontService.php`: fonts live as `fonts/custom-<slug>.woff2` in app data (`:39`, `:77`), with a manifest in app config `custom_fonts` (`:56`) and a revision key (`:63`).
- `lib/Service/TokenSetConverterService.php` converts DTCG and other theme inputs into a token set (open change `nlds-theme-converter` widens it).
- Repair steps are registered in `appinfo/info.xml:182-233`; `lib/BackgroundJob/UpstreamFreshnessJob.php` is the job pattern.
- `lib/Service/ThemingAuditService.php` action `config_imported` is already in the vocabulary.

## Decisions

### 1. A package is a directory first

```
branding/
  bundle.json          the existing bundle, unchanged format
  fonts/<id>.woff2     one file per customFonts entry
  tokens/<id>.json     optional DTCG sources, converted on apply
  REVISION             optional, the Git commit the directory came from
```

A directory diffs well in Git and mounts as a volume. A ZIP of the same tree is accepted wherever a directory is, for people who move files by hand. The web upload keeps its 256 KB cap for a bare bundle; a package goes through occ or declarative mode, which have no such cap.

`tokens/<id>.json` lets designers keep DTCG in Git instead of generated CSS: on apply, each source runs through `TokenSetConverterService` and replaces the custom set of the same id in the bundle before validation.

### 2. Fonts are applied when their files are present

For a package, the `customFonts` section is applied: every font file is checked by the existing font validator, stored through `FontService`, and assigned its role. A manifest entry without a file in the package is a hard error, because it would serve a broken `@font-face` URL. A bare bundle (no package) keeps today's behaviour: font metadata is informational.

### 3. Declarative mode reads a path, it never pulls

`thematiq.config_source` in `config.php` names a directory or ZIP. Thematiq never talks to Git or any remote: the deployment tool (Argo CD, Helm with a ConfigMap or an init container, Ansible) puts the checkout on disk. That keeps credentials and network egress out of the app, and keeps the upstream-freshness non-goal intact (no downloads by background jobs).

Rejected: a Git URL and a ref that thematiq clones itself. It needs Git binaries or a PHP Git client, credentials in app config, and outbound traffic from a background job.

### 4. Apply on change, validate first

`ConfigSourceService::applyIfChanged()` hashes the package (sorted file list and contents) and compares it with `config_source_applied_hash`. When they differ it runs the package import. On success it stores the hash and writes a `config_imported` audit entry with actor `system`, context `source: deployment` and the `REVISION` when present. On failure it writes nothing, keeps the old hash, stores the error listing in `config_source_last_error` and logs an error. It runs from a post-migration repair step (so every upgrade applies it), from a background job every 5 minutes, and from `occ thematiq:config:apply` (exit non-zero on failure, for pipelines).

### 5. Managed notice, drift and an optional lock

While `thematiq.config_source` is set, the configuration bundle block states the source path, the last applied revision and time, and the last error if any. Drift is shown when `export()` of the running configuration differs from the package's bundle, so an administrator sees that a web change will be replaced on the next apply. `thematiq.config_source_lock = true` makes every configuration setter answer 423 with a message naming the source, and the settings page disables its controls. Lock is off by default, because some teams want the package as a baseline and small web fixes on top.

### 6. Export for the round trip

`occ thematiq:config:export --package <dir>` writes the tree above from the running configuration, fonts included, so a team can start their Git repository from what they have today.

## Risks

- A failing package on every job run fills the log. The error is logged once per distinct package hash.
- Two replicas applying at once: the apply takes the Nextcloud lock `thematiq-config-source` so one runs at a time.

## Out of scope

- Pull request review itself: that is the Git host's job.
- Per-environment differences inside one package. The environment marker (change `governance-environment-marker`) is what differs, and it lives in `config.php`.
