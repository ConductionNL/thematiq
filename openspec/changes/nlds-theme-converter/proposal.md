---
kind: code
---

## Why

Stage 1 made "correct" mechanical. It also measured the damage: `npm run audit:token-sets` reports
**39 of the 48 shipped sets incomplete**, 7 complete (`amsterdam`, `conduction`, `denhaag`,
`rotterdam`, `utrecht`, `vng`, `zwolle`) and 2 not auditable (`nextcloud`, which is stock by
definition, and `summer-breeze`, whose colours live in its own design-system layer). Nothing fixes
those 39 today, and nothing can: there is no path from an NL Design System theme to the
`--nldesign-*` vocabulary the app reads, so every set has been hand-authored or dumped raw.

The raw dumps are the whole problem, and they are also the way out. 25 of the 39 declare **none** of
the 26 required semantic tokens, and 30 declare no primary colour at all — yet they are not empty.
They carry their brand's full palette under names nothing reads: `nijmegen` declares **959** foreign
`--nldesign-*` names, `conduction-new` 189, `epe` 186, `tubbergen` 184, `noordwijk` 168, `xxllnc` 167
(and its primary contradicts `token-sets.json`). Those files hold `--nldesign-color-blue-40` where
they should hold `--nijmegen-color-blue-40` plus a semantic layer derived from it. The brand data is
present; the mapping is missing.

The same missing mapping is why the **Custom token sets** upload is narrower than its name suggests.
`CustomTokenSetController::upload()` accepts pre-baked `--nldesign-*` CSS, or W3C DTCG JSON through
`DesignTokensMapper`, and routes on the file extension. Hand an admin the artefact a design system
actually publishes — `dist/design-tokens.css` from a Style Dictionary theme package, one
class-scoped block such as `.openwoo-theme { }` with 697 declarations and `var()` chains — and the
upload has nothing to do with it. The admin's real input is a theme, not a token set.

This change is stage 4 of `MAKEOVER-PLAN.md` (stage 2 until the 2026-09-10 reorder): one mapping
table, one conversion, four accepted inputs, and a report that names every token that was applied,
adapted or skipped, and why.

## What Changes

- **The mapping table becomes data.** `scripts/mapping/nlds-to-nextcloud.json` holds the ordered
  rule list (plan Appendix A): `source` candidates in priority order (first match wins), `target`,
  `transform`, and the skip rules with their reason code. PHP and JS both load this file, so the two
  runtimes cannot drift; its SHA-256 goes into every converted file's provenance block.
- **`lib/Service/TokenSetConverterService.php`** — the runtime the admin path uses. Takes raw content
  plus a slug, returns `{css, manifestEntry, report}`. Wired into `CustomTokenSetController::upload()`
  so the existing file picker converts any of the four inputs instead of only accepting a finished
  token set.
- **A paste surface, because that is how a theme arrives.** The "Custom token sets" section gains a
  textarea next to the file picker: paste the contents of a `design-tokens.css` (or a DTCG document),
  give it a name, convert. `upload()` accepts either a `file` or a `content` parameter and is
  identical from the read onward, so the validator and the store path never fork.
- **Input detection by content, not by file name.** A paste has no extension. The converter sniffs:
  DTCG (`$value`/`$type`), Style Dictionary `tokens.json`, class-scoped built CSS, or an existing
  `--nldesign-*` token set. `DesignTokensMapper` keeps ownership of the DTCG branch.
- **`js/lib/tokenConverter.js`** — the dual-mode mirror (`module.exports` under Node,
  `window.NldesignTokenConverter` in the browser), following `js/lib/tokenTransforms.js`. It is what
  `scripts/convert-nlds-theme.mjs` runs and what the vitest fixtures exercise.
  `tests/Unit/Service/TokenSetConverterParityTest.php` runs the same fixtures through PHP and
  compares against the JSON the vitest suite writes.
- **The 39 incomplete sets are regenerated from their own files.** No upstream package is added,
  vendored or fetched (design decision 3): each set's foreign `--nldesign-*` palette steps are
  renamed to `--{slug}-*` and the semantic layer is derived from them. `summer-breeze` gets the same
  semantic layer as every other set, so the audit judges all of them alike (plan decision 4). The 7
  complete sets are re-run in add-only mode: hand-chosen values win, the converter may only fill
  gaps.
- **The theme's logo becomes a file.** A theme carries its wordmark inline, as a `data:` URI on a
  logo token; Nextcloud's core theming takes a logo as a FILE. The converter decodes the payload,
  hands the caller `img/logos/{set-id}.{ext}` to write, points `--nldesign-logo-url` and
  `theming.logo` at it, and rewrites the theme's other logo slots to `var(--nldesign-logo-url)`
  instead of repeating ~40 KB of base64 in a file served to every anonymous visitor. This is what
  lets a converted theme reach Nextcloud's own logo, name and e-mail branding at all.
- **Every skipped and adapted token gets a reason code** from the stage 6 list, carried in the report
  and rendered by the existing `groupDiagnosticsByReason()` / `buildDiagnosticsFragment()` pair, so
  the upload result says what a theme asked for that Nextcloud will not do — page width, type scale,
  clickable areas, spacing, and the components Nextcloud does not have.
- **`CustomTokenSetValidator` widens to the component prefixes** (`--utrecht-*`, `--ams-*`,
  `--denhaag-*`) because the emitted file needs them for `utrecht-bridge.css` and for Conduction's
  own apps. `isForbiddenValue()` is untouched and stays the final gate; external `url()` values are
  dropped with `external-url-blocked`.
- **The allow-list empties.** `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` goes to `[]`
  and `TokenSetVocabularyTest` guards all 46 auditable sets, which is stage 1's stated closing
  condition.

## Capabilities

### New Capabilities
- `token-set-converter`: converting a published design-system theme into a Nextcloud token set is a
  new capability with its own contract — accepted inputs, the four-section output, the provenance
  block, the report shape, and the fixed policy about what is never applied. It is not a variation
  on uploading a token set (`custom-token-sets` owns that) and not a property of a shipped set
  (`token-sets` owns that); both of those consume it.

### Modified Capabilities
- `custom-token-sets`: the upload accepts theme sources as well as token sets, accepts pasted content
  as well as a file, detects the input by content, returns a conversion report, and stores files that
  legitimately contain component-prefix tokens.
- `token-sets`: every shipped set that reads the `--nldesign-*` vocabulary is converter output with a
  provenance block, the known-incomplete allow-list is empty, and `summer-breeze` is auditable.
- `theming-sync`: `applyImages()` persists the mime type `ImageManager::updateImage()` returns.
  Without that app value Nextcloud serves its own logo however successful the sync reported itself,
  which is why no converted theme has ever changed the logo. Found by converting a theme end to
  end; the sync is otherwise untouched.

## Impact

- **An instance already running one of the 39 sets will look different after this change, and that is
  the fix.** Those sets render as Rijkshuisstijl today because the cascade falls through to
  `css/systems/nldesign/defaults.css`; afterwards they render as their own brand. 30 of them gain a
  `--nldesign-color-primary` where they had none, and `xxllnc`'s primary stops contradicting its
  manifest entry. The apply dialog's "Incomplete set" warning disappears for all of them.
- **`token-sets.json` `theming.primary_color` changes for the sets whose manifest value was never
  reflected in CSS**, which means Nextcloud core theming (login page, e-mails, mobile colour) changes
  for those instances on the next sync. Stage 3 owns making that automatic; this change only makes
  the two halves agree.
- **Large mechanical diff**: 39 regenerated `css/tokens/*.css`, their `css/tokens/dark/*.css`
  variants (`php scripts/generate-dark-variants.php --force`), 39 `token-sets.json` entries and 39
  `css/tokens/<slug>.report.json` files. Reviewable because every file carries counts and a mapping
  hash, so two runs diff cleanly.
- **Code**: `scripts/mapping/nlds-to-nextcloud.json` (new), `js/lib/tokenConverter.js` (new),
  `scripts/convert-nlds-theme.mjs` (new, absorbs `scripts/generate-brand-set.mjs`),
  `lib/Service/TokenSetConverterService.php` (new), `lib/Controller/CustomTokenSetController.php`
  (content-sniffing router, `content` parameter, report in the response),
  `lib/Service/CustomTokenSetValidator.php` (widened vocabulary),
  `templates/settings/admin.php` (paste textarea), `js/admin.js` (paste submit, report rendering),
  `l10n/*` (reason-code copy), `token-sets.json`, all `css/tokens/*`, the allow-list fixture, and new
  tests: `tests/vitest/tokenConverter.spec.js`, `tests/Unit/Service/TokenSetConverterServiceTest.php`
  and `tests/Unit/Service/TokenSetConverterParityTest.php`.
- **No new dependency, no network at convert time, no CI sourcing** (design decision 3). The fixtures
  are local: `css/tokens/openwoo.css` is the hand-resolved reference the converter's OpenWOO output
  is diffed against, and the installed `@gemeente-rotterdam/design-tokens` and
  `@nl-design-system-unstable/zwolle-design-tokens` packages provide DTCG and Style Dictionary
  fixtures. Final acceptance is a human paste of a real `design-tokens.css` into the panel.
- **No OpenRegister schemas, no lifecycle or notification behaviour.** Filesystem and config work
  only; ADR-031's declarative/imperative split does not apply.
- **Security**: the conversion runs before `CustomTokenSetValidator`, never instead of it. New value
  shapes reaching the validator (component prefixes, `url()` values, `var()` chains) get their own
  tests, and the wave-3/wave-5 `url()` policy is enforced inside the converter as a reason code
  rather than a silent drop.
