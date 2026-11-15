# Design: every shipped set selectable

## D1. Why a derived rule beats a wider list

The instruction was to delete `SELECTABLE_SHIPPED_SETS` rather than widen it, and the reason is
visible in its own history: it was correct when written, its exit condition was met weeks ago,
and it stayed stale because **nothing failed when it went stale**. A wider hand-written list
would have the same property.

So selectability is now a function of two facts that are already in the repository:

```
selectable = { s in getAvailableTokenSets() :
                  ( s has an entry in token-sets.json
                    AND s carries no 'incomplete' warning )
                  OR s is the active set
                  OR s is named by a group mapping
                  OR s.id starts with 'custom-' }
```

Nothing to edit when a set improves, nothing to edit when one regresses, and no second place
that can disagree with the admin UI's own "Incomplete set" banner — because the verdict is read
off the entry that banner is rendered from.

**Read, not recomputed.** `getAvailableTokenSets()` already calls `applyWarnings()` for every
set, which already calls `TokenSetVocabularyAuditService::warningsFor()`. Calling the audit
again inside `getSelectableTokenSets()` would double the work on every admin page render and
create a second answer to one question. The private `isVocabularyIncomplete()` helper looks for
`kind === 'incomplete'` on the entry.

## D2. The one shipped file that is still withheld

`css/tokens/conduction.css` has no `token-sets.json` entry. Discovery is filesystem-based, so
it is found like any other file, and with the constant gone it would have been offered as
"Conduction" with a generated description. It is the shared role layer
`scripts/generate-brand-set.mjs` copies into a brand set — an input, not a theme.

This is the answer to "anything you found that means a set still should not be offered": one
file, for a structural reason, with a test that proves the catalogue still holds it.

## D3. The bridge bar was wrong, and this is the measurement

The preceding change gated `bridge > 0`, on the reasoning that a set declaring none of the 87
`--utrecht-*` names the bridge reads "keeps the Rijkshuisstijl default, so every button, table
and form control is the wrong brand". I wrote that, and it is wrong.

`css/systems/nldesign/utrecht-bridge.css` holds 84 declarations shaped
`--nldesign-component-X: var(--utrecht-Y, <fallback>)`. Scanning the fallbacks outside
comments:

| fallback | count | what it means for a bridge-zero set |
|---|---|---|
| `var(--nldesign-*)` the set declares | **42** | the set's own brand colour reaches the component |
| a non-colour literal | **38** | `1rem`, `1px`, `0.5rem`, `700`, `transparent`, `underline` — geometry and type scale, not a brand colour |
| a colour literal | **3** | `#e5e5e5` / `#696969` (disabled button), `#ffffff` (textbox fill) |
| another `var()` | **1** | `--utrecht-form-input-font-size`, a size |

Reading the CSS is not proof, so the cascade was resolved (defaults → set → bridge) for three
sets and the component tokens compared:

- `amsterdam` (bridge-zero) vs `vng` (74/87): differ on **63 of 84**.
- `amsterdam` (bridge-zero) vs `rijkshuisstijl` (bridge-zero): differ on **32 of 84**. Both are
  at zero, and they still do not look alike.
- Amsterdam's `--nldesign-component-button-color` computes **`#004699`**; Rijkshuisstijl's
  **`#154273`**; VNG's `#0a2750`. Their `border-radius` is `0` and VNG's is `8px`; their
  `font-size` is `1rem` and VNG's `16px`.
- Of amsterdam's 84, **7** land on a value identical to Rijkshuisstijl's and are a colour, and
  **2 of those 7** (`#e8f0f8`, `#d4e4f2`) are values amsterdam declares itself that happen to
  equal the default. So **3** are genuinely inherited, and they are two greys and a white.

So bridge-zero means: *this set adopts the NL Design System's component geometry and type scale,
and brands the component colours itself.* That is a legitimate theme — it is what "NL Design
System defaults with our colours" means — and the defect the constant existed to prevent was
the **semantic layer** being absent, which stage 1 already gates and all 57 sets pass.

The figure stays measured and published, because a set with its own `--utrecht-*` layer owns
its radii, spacing and type scale, and that is part of conformance to a house style. It is
information, not a verdict.

## D4. Demoting a dimension must be loud

Demoting `bridge` took the allow-list from 42 entries to 34 with no set improving. That is
precisely the shape my own memory calls *a debt ratchet must fail when the number goes down*:
the number improved and nothing got better.

Three mechanisms make it loud rather than silent:

1. `BARRED_DIMENSIONS` is a separate list from `DIMENSIONS`, so the difference between
   "measured" and "gated" is a value in the source, not an absence.
2. Every dimension in `DIMENSIONS \ BARRED_DIMENSIONS` must carry a `$reported` reason of at
   least 20 characters in the fixture, and a gated dimension must NOT carry one. Both halves
   fail the gate.
3. The fixture's `$comment` records the 42 → 34 movement, the 8 ids it deleted, and where the
   justifying measurement lives.

An allow-list entry under a dimension nothing gates also fails, so the 8 `bridge` entries could
not simply be left behind to rot.

## D5. What the new selectability gate asserts

`testEveryNamedShippedSetTheAuditPassesIsOfferedOnTheRealCatalogue` runs against the repository
root, not a synthetic catalogue — a synthetic one would pass happily while the shipped sets were
hidden, which is exactly the failure that went unnoticed. It asserts:

- the picker equals every id in `token-sets.json` minus those the vocabulary audit reports
  incomplete;
- at least one shipped token file has no manifest entry, and no such file is offered (so the
  "withheld" half is not vacuous);
- the count is above 50, so a future narrowing back to a handful fails on the number alone.

Proven by reinstating a two-set narrowing: the test failed and named all 56 withheld sets.

## D6. Measured before and after

| | before | after |
|---|---|---|
| shipped token files | 59 | 59 |
| named in `token-sets.json` | 58 | 58 |
| **selectable in the admin picker** | **2** (`nextcloud`, `cunningham`) | **58** |
| withheld shipped files | 57 | 1 (`conduction`, no manifest entry) |
| vocabulary-incomplete sets | 0 | 0 |
| gated coverage dimensions | 4 | 3 (`font`, `logo`, `contrast`) |
| coverage allow-list entries | 42 | 34 |
| sets passing every gated dimension | 17 of 56 | 22 of 56 |

The last row moves because `bridge` left the gate, not because 5 sets improved. The 8
bridge-zero sets and their figures are unchanged and still published:
`amsterdam` 0, `denhaag` 0, `epe` 0, `groningen` 0, `ridderkerk` 0, `rijkshuisstijl` 0,
`utrecht` 0, `zwolle` 0 — out of 87, median across bridged sets 21.

## D7. Why the 8 did not get brand files

`scripts/generate-brand-set.mjs` composes a set from a brand palette, the brand's own component
mapping where one exists, the shared role layer re-pointed at the brand's ramp, and a semantic
layer. A brand file needs a ~43-step ramp.

The 8 bridge-zero sets declare between **3 and 9** palette steps of their own (`epe` is the
exception at 226), so seven of them would need roughly 35 ramp steps invented per brand. Filling
a ramp by derivation under a stated contrast rule is an established method here
(`example-gemeente` did it), but `example-gemeente` is explicitly fictional. Deriving 35 steps
for Amsterdam, Groningen or Zwolle and shipping them as those municipalities' house style is
inventing a brand, which is the one thing this lane must not do.

`denhaag` is the exception worth doing next: `scripts/sources/denhaag/*.css` is already vendored
under EUPL-1.2 for `generate-denhaag-bridge.mjs`, so its component mapping comes from its own
published source. `rijkshuisstijl` is the highest-risk of the eight regardless — it is the
app's default theme, so regenerating it rewrites what every unconfigured instance looks like.
