## Context

The current audit lives as comments in `css/systems/nldesign/overrides.css`: one line per theming variable, either mapped or "unmapped" / "intentionally not overridden" with a reason. 35 of the theme variables thematiq does not set globally are commented this way, so the existing spec holds. 10 more are only re-scoped inside single components, where they fall back to Nextcloud's own value. What it cannot do:

- see past the 111 theming variables, because nobody listed the rest;
- notice a Nextcloud release adding one, because the list is not read from Nextcloud;
- tell a comment from a mapping, because a plain grep counts both. The first measurement of this chain read "111 of 111" for that reason; with comments stripped and per-component re-scoping set apart, it is 52 set globally plus 14 that Nextcloud derives, 66 in all.

## Goals / Non-Goals

**Goals:**
- One file that states what "all" means for a given Nextcloud and library version.
- A guard that turns red when coverage drops or a new property arrives unclassified.

**Non-Goals:**
- Changing what any instance renders. Changes 2 and 3 of the chain do that.
- Scanning custom apps' own bundled copies of `@nextcloud/vue`. Versions differ per app; the inventory is tied to one Nextcloud release and one shared-library release.

## Decisions

### Read the release, not the source tree

The extractor reads a built Nextcloud release directory (`core/`, `apps/`, `dist/`), because that is what an instance serves. The source tree has SCSS and Vue files that compile into names the build renames or drops.

The theme vocabulary and its stock values come from the theming app's served stylesheets (`/apps/theming/theme/{id}.css`) for `default`, `dark`, `light-highcontrast` and `dark-highcontrast`. That is the authority for per-theme values. Reading `DefaultTheme.php` would mean re-implementing its colour maths.

Alternative considered: hard-code the list from the theming PHP classes. Rejected, because a release that adds a variable would pass silently, and that is the failure this change exists to end.

### Classify by rule, and store the rule's verdict

| Class | Rule |
|---|---|
| `theme` | declared by a theming stylesheet |
| `icon` | name starts with `--icon-` or `--original-icon-`, or the value is a `url()` |
| `runtime` | written from JavaScript (`style.setProperty`, inline style bindings) |
| `component` | declared and read in shipped CSS/JS, not theme |
| `slot` | read with `var()` but declared nowhere |
| `unread` | declared, never read |
| `conduction` | `--cn-*` in `@conduction/nextcloud-vue` |

The rule decides; a short `overrides` block in the extractor config can force a class by name, with a comment saying why. The measured split for `component`: 131 follow a theme variable, 61 chain to another internal one, 94 are hardcoded colours, 87 hardcoded sizes or timings, 65 keywords or runtime-set.

### Status is data thematiq owns, kept next to the generated part

Class, owner, selectors and stock values are generated. Status (`mapped` / `settable` / `excluded` plus reason) is a human decision, kept in `scripts/mapping/variable-status.json` and merged by name. Regenerating never loses a decision; a decision for a name the release no longer has is reported as stale.

### The guard counts in both directions

Per the fleet lesson "a ratchet must fail on the way down", the guard stores the counts of `mapped` and `settable` per class as a baseline. Fewer fails unless the status file records the removal. More updates the baseline in the same change.

### Comments are stripped before any count

Every count in the guard strips CSS comments and ignores self-references (`--x: var(--x)`) first, so an annotation can never be read as a mapping.

## Risks / Trade-offs

- [The extractor misreads a minified bundle] → The guard compares totals per class with the committed baseline, so a parser change that drops hundreds of names fails loudly. Spot-check ten names per class by hand on first run.
- [Runtime detection misses a property written through a helper] → Such a property lands in `component` or `slot`. Change 3 sets nothing unless a theme provides a value, so a misclassified runtime property is harmless until someone sets it. The playground check in change 4 catches the visible ones.
- [The inventory goes stale between Nextcloud releases] → The file names its version. Regenerating is one command, and the upstream-freshness job already watches releases.

## Migration Plan

No runtime migration. Land the extractor, the first generated file and the guard together. The first baseline is the measured state: 66 of 111 theme entries mapped (52 by thematiq's stylesheets, 14 by Nextcloud deriving them from the primary and background colour thematiq writes), 12 component entries mapped, 0 `--cn-*`.

## Seed Data

Not applicable. This change adds no OpenRegister schema.
