# Spec delta: configuration portability (config-portability)

A package carries font binaries, so an import from a package applies fonts too.

## ADDED Requirements

### Requirement: A package import applies fonts

When the import source is a branding package, the `customFonts` section MUST be applied: every font file MUST pass the existing font validator and be stored through `FontService` with its role. A `customFonts` entry without a matching file in the package MUST be a hard validation error, so no manifest entry points at a missing file. When the source is a bare bundle file, font metadata MUST stay informational, as before.

#### Scenario: A package with a missing font file is refused whole

- GIVEN a package whose bundle names the font `custom-corporate` but has no `fonts/custom-corporate.woff2`
- WHEN an operator runs `occ nldesign:config:import` on it
- THEN the command MUST exit non-zero, name the missing file and write nothing

#### Scenario: A bare bundle keeps its old font behaviour

- GIVEN a bundle file uploaded on Settings > Administration > Theming with two fonts in its metadata
- WHEN the import completes
- THEN no font MUST be added or removed
- AND the import result MUST state that fonts in a bare bundle are informational
