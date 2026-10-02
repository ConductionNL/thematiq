# Spec coverage report: thematiq

Measured: 2 October 2026, on `development` at `a9c6db53`.
This replaces the report generated on 24 May 2026 for `nldesign`, before the retrofit annotation
landed. Every number in that report is obsolete.

## Summary

| Measure | Value | How it was measured |
|---|---|---|
| PHP files in `lib/` | 99 | `find lib -name '*.php'` |
| Files carrying at least one `@spec` | 99 | each file grepped for `@spec` |
| Public and protected methods in `lib/` | 422 | `grep -E '^\s*(public\|protected)( static)? function'` |
| `@spec` tags in `lib/` | 879 | `grep -rh '@spec openspec' lib` |
| `@spec exclude` reasons in `lib/` | 16 | `grep -rh '@spec exclude' lib` |
| Methods without a tag (gate-16) | **0** | `check_spec_coverage.py` with `HYDRA_GATE_BASE_REF` set to an empty-tree commit, so every line of `lib/` and `src/` is in scope |

## The May buckets, closed

| Bucket (24 May) | Then | Now |
|---|---|---|
| 1, ready to annotate | 67 methods | Annotated by `retrofit-2026-05-24-annotate-nldesign` (archived). Gate-16 reports 0. |
| 2a / 2b, reverse-spec | 0 | 0 |
| 3a, possibly broken | 1: `theming_syncs_total` never incremented | Fixed. `SettingsController::updateThemingValues()` increments it after a successful sync. |
| 3b, never implemented | 39 | False alarms of the scanner's scope. The behaviour lives in `templates/`, `js/admin.js` and `css/`, which the scanner did not read. |
| 4, ADR conformance | 18 files without a file-docblock `@spec` | All 99 files carry one. `@license` and `@copyright` are on every file. |

## Stale anchors fixed

311 `@spec` tags named `openspec/changes/<change>/...` for fourteen changes that have since moved to
`openspec/changes/archive/`: 139 in `lib/Service` (sibling PR) and 172 in the rest of `lib/` and
`tests/` (this PR). Gate-46 resolves an archived change by itself, so none of them was red, but a
reader following the path found nothing. They now name the archive path. One tag still names
`openspec/specs/custom-css-freeform/spec.md`, which exists once the open `custom-css-freeform`
change is archived.

## Re-measure

Run the full sweep from a clone of this repository:

```bash
E=$(git commit-tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904 -m empty)
git update-ref refs/tmp/empty "$E"
HYDRA_GATE_BASE_REF=refs/tmp/empty python3 <hydra-gates>/scripts/lib/check_spec_coverage.py .
```

`# count=0` is the clean result.
