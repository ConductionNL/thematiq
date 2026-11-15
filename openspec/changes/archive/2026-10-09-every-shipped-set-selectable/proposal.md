---
kind: code
---

## Why

`TokenSetService::SELECTABLE_SHIPPED_SETS = ['nextcloud', 'cunningham']` limited the admin
picker to **2 of the 59 shipped token sets**. On the live instance `/settings/tokensets`
returned exactly those two while the public catalogue returned all 59, so an administrator
could not choose vng, leiden, zwolle, rotterdam or any other municipality at all. That is the
opposite of what this app is for.

The constant was right when it was written. In 2026-09 all but a handful of set files declared
none of the `--nldesign-*` vocabulary the theme reads, so picking one rendered Rijkshuisstijl
with the wrong header. The docblock named its own exit condition: a set returns once it passes
`TokenSetVocabularyAuditService` and leaves
`tests/Unit/fixtures/token-set-vocabulary-allowlist.json`, "which is shrink-only and must reach
empty. At that point every shipped set is selectable again and this constant is deleted rather
than widened."

**That condition has been met and nothing noticed.** The fixture reads `"sets": []`, the audit
reports 57 of 57 auditable sets complete, and `TokenSetVocabularyTest` passes. The constant
went stale because nothing failed when it did.

Its docblock also carried two claims that are no longer true: that `cunningham` "IS LISTED
WHILE STILL FAILING THAT AUDIT" and that "its id therefore STAYS in" the allow-list. Measured:
cunningham declares all 26 required tokens, the audit reports it complete, and the allow-list
is empty. Both halves were false.

## What Changes

**1. The constant is deleted, and the audit is the gate.** `getSelectableTokenSets()` now
offers every shipped set that is both named in `token-sets.json` and not reported incomplete by
the vocabulary audit, plus every `custom-*` import. Both conditions are read at request time,
so the picker cannot go stale again in either direction. **2 sets become 58.**

The verdict is read off the catalogue entry rather than recomputed: `getAvailableTokenSets()`
already runs the vocabulary audit for every set through `applyWarnings()`, so asking again
would both double the work on every admin page render and create a second answer to one
question. It is the same `warnings` channel the admin UI renders its "Incomplete set" banner
from, so the dropdown and the banner cannot disagree.

**2. One shipped file is still withheld, and it is the right one.** `css/tokens/conduction.css`
has no `token-sets.json` entry: it is the shared role layer `scripts/generate-brand-set.mjs`
copies into a brand set, not a theme, and it has no name, description or theming of its own. It
would have been offered as "Conduction" with a generated description. The three never-filtered
ids are unchanged, and now survive this condition too.

**3. A gate that fails when the picker is narrowed again.**
`TokenSetServiceSelectableTest::testEveryNamedShippedSetTheAuditPassesIsOfferedOnTheRealCatalogue`
runs against the real `css/tokens/` and `token-sets.json` and names every set a narrowing
withheld. Proven by reinstating a two-set narrowing: it failed and listed all 56.

**4. The bridge dimension is demoted from a bar to reported-only, because the bar was wrong.**
The preceding change (`honest-token-set-coverage`) gated `bridge > 0` on the reasoning that a
set declaring none of the 87 `--utrecht-*` names "keeps the Rijkshuisstijl default, so every
button, table and form control is the wrong brand". Resolving the bridge's own fallbacks shows
that is false, and false in the direction that overstates the defect: of its 84 declarations,
**42** fall back to a `--nldesign-*` token the set itself declares, **38** to a non-colour
literal (`1rem`, `1px`, `transparent`, `700`, `underline`) and **3** to a colour literal, those
three being `#e5e5e5`/`#696969` (disabled button) and `#ffffff` (textbox fill).

Resolved over the real cascade, `amsterdam` and `rijkshuisstijl` are both bridge-zero and
differ on **32 of the 84** component tokens: Amsterdam's buttons compute `#004699`,
Rijkshuisstijl's `#154273`. A bridge-zero set adopts the design system's component geometry and
type scale and brands the component colours from its own semantic layer. Gating that would have
withheld 8 working sets from the picker for a defect they do not have.

**5. Demoting a dimension is now impossible to do silently.** It is the one edit that lowers
the allow-list count with no set improving — here 42 entries became 34 and nothing was fixed.
So the audit fails unless every non-gated dimension carries a written reason under `$reported`
in the fixture, fails if a gated dimension claims one, and fails on an allow-list entry under a
dimension nothing gates. The fixture's own `$comment` records the 42 → 34 movement and its
cause.

## Impact

- **An administrator can now apply any of the 58 named shipped sets.** This is the behaviour
  change Ruben asked for; everything else here serves it.
- **Nothing is withheld for being bridge-zero**, and logo-absent never blocked selectability.
- The public catalogue, discovery, previews, capabilities and both audits are untouched: they
  answer what the app ships, not what may be chosen.
- `openspec/specs/token-sets/spec.md`'s "Only Fully Functional Brands Are Selectable"
  requirement is rewritten around the measured rule, and the coverage requirement gains the
  gated-versus-reported distinction.
- **Not in this change:** component layers for the 8 bridge-zero sets. They are no longer
  blocking anything, and `denhaag` is the one that can be done from a sourced upstream today
  (`scripts/sources/denhaag/*.css`, already vendored under EUPL-1.2 for the bridge generator).
  The other seven would need a 43-step ramp per brand that no source in this repository
  provides, and deriving one would be inventing an organisation's house style.
