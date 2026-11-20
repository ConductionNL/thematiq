---
description: Start a new OpenSpec change
---

Start an OpenSpec change named `$ARGUMENTS` (kebab-case).

1. Read `openspec/config.yaml` for the project context and the rules for proposals, designs and tasks.
2. Look at the existing specs in `openspec/specs/` that the change touches, and at a recent example in `openspec/changes/archive/`.
3. Create `openspec/changes/<name>/` with:
   - `.openspec.yaml` containing `schema: spec-driven` and `created: <YYYY-MM-DD>`
   - `proposal.md` with `## Why`, `## What changes` and `## Impact`
   - `design.md` with the context and a `## Decisions` section, when the change has design choices
   - `tasks.md` with numbered checkbox tasks (`- [ ] 1.1 ...`)
   - `specs/<capability>/spec.md` with `## ADDED Requirements` (or MODIFIED / REMOVED), each `### Requirement:` using SHALL or MUST and at least one `#### Scenario:` written as GIVEN / WHEN / THEN
4. Do not edit `openspec/specs/` directly. Specs are merged there when the change ships, and the change folder then moves to `openspec/changes/archive/<YYYY-MM-DD>-<name>/`.
