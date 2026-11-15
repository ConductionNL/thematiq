---
kind: code
---

## Why

`tests/Unit/fixtures/token-set-vocabulary-allowlist.json` reads `"sets": []`. Every shipped
token set passes the repo's own audit, and the file says so: "This array MUST be empty once
every shipped set is complete." It has been empty since thematiq#1006.

That audit asks one question: does the set declare the 26 semantic tokens
`css/systems/nldesign/theme.css` reads? It is a good question and the answer is a real yes.
It is not the question Ruben asked, which is whether an instance on each set looks like that
organisation. Measured on 2026-10-04 on `origin/development` (3dc02602), the gap between the
two was:

- **8 of 52** sets whose design system links the Utrecht bridge declare **none** of the 87
  `--utrecht-*` names it reads, so they adopt the design system's component geometry and type
  scale rather than their own: `amsterdam`, `denhaag`, `epe`, `groningen`, `ridderkerk`,
  `rijkshuisstijl`, `utrecht`, `zwolle`. (This bullet first said such a set "keeps the
  Rijkshuisstijl default" on every control. That was wrong and overstated the defect; the
  measurement that corrects it is in the follow-up change `every-shipped-set-selectable`, which
  also demotes this dimension from a bar to reported-only.)
- **33 of 58** sets name a typeface no stylesheet their design system links declares, so the
  page silently renders the next family in the stack. `conduction-new` asks for Figtree, whose
  bytes were already in the tree, declared only in `css/fonts-conduction.css`, a layer no
  design system links.
- **34 of 56** selectable sets point Nextcloud at no logo, so the Nextcloud logo stays in the
  header of a municipality's instance.
- **8 of 57** sets had no contrast verdict at all: `vng` failed at 2.50:1 and seven read
  `unevaluated`, which is never a pass.

None of that is visible from the audit, and none of it fails anything. A set is "complete" and
an instance on it is not this organisation's instance. The instrument answers a question
adjacent to the one being asked, and the empty array makes the adjacent answer read as the
real one.

## What Changes

**1. The instrument measures what an administrator sees.** `scripts/audit-token-sets.mjs`
keeps its three vocabulary rules unchanged and gains a stage 2 that measures four things per
selectable set and prints them in one table: the bridge figure (`n/87`), whether the named
typeface is served, whether the set carries a logo, and the contrast verdict.
`docs/reference/token-set-coverage.md` is generated from it, with the counts it was generated
from recorded in the page.

**2. It fails, with a reason per set.**
`tests/Unit/fixtures/token-set-coverage-allowlist.json` is a map of dimension to set id to
**reason**, not an array of ids. The gate fails three ways, which is what makes it a ratchet
rather than a baseline: on a set below the bar that is not listed, on a listed set that has
started passing (delete the entry), and on an entry whose reason is under 20 characters.
Baseline recorded: bridge 8, font 0, logo 34, contrast 0. (`bridge` was demoted to
reported-only by the follow-up change, leaving font 0, logo 34, contrast 0 gated.)

**3. The fonts the sets name are served.** Open Sans, Lato, Roboto, Source Sans Pro,
IBM Plex Sans and Clear Sans are self-hosted (woff2, latin subset, from `@fontsource` 5.3.0),
and Figtree's existing bytes are declared in the layer that is actually linked. Five are SIL
OFL 1.1, Intel Clear Sans is Apache 2.0. Over the 58 sets in `token-sets.json`, the number
whose named typeface is actually served goes from **19 to 36**, and the number naming a family
nothing serves and nothing declares goes from **33 to 0**.

**4. A family we may not redistribute is refused and said out loud.** Avenir, DIN, Bolder,
Karbon, Sofia Pro, greycliff-cf, neue-haas-grotesk, Calibri, RijksoverheidSans and the
Monotype `Droid Sans W01` webfont are not shipped. Each of the 16 sets naming one carries a
`font` block in `token-sets.json` with the licence position, the holder and the action, and
`TokenSetFontAuditService` puts that on the admin UI's existing warnings channel so an
administrator learns it from the product instead of from a page that quietly renders Arial.

**5. Every set has a contrast verdict.** `CssParserService::resolveVarChain()` follows a
`var()` value the way a browser does, the audit's background chain gains the white page the
rest of the app already assumes, and `vng` declares its own page background. 57 pass, 0 fail,
0 unevaluated. No brand colour changed and no threshold moved.

## Impact

- **Behaviour change, deliberate:** a set naming a licensed family now shows a warning in the
  admin apply dialog. Nothing is blocked; the set applies exactly as before.
- **Behaviour change, deliberate:** `vng` declares `--nldesign-color-background: #ffffff`, so
  its generated dark variant now derives the dark page from white (#141414, what every other
  set uses) rather than from the login blue (#04253a). Its login background is unchanged.
- **New bytes:** 16 woff2 files per font layer, about 360 KiB each layer. A face is fetched
  only when a page names its family.
- **`npm run build` no longer deletes a font.** `scripts/build-fonts.js` held one hard-coded
  Fira Sans string and overwrote `css/fonts.css`, which has carried hand-added Source Sans 3
  since the (EXAMPLE) Gemeente set; and `css/systems/nldesign/fonts.css`, the file that
  matters on an instance, was written by no generator at all.
- **Not in this change,** and listed in `tasks.md` as the next waves: the 8 sets at bridge
  zero (one `scripts/brands/<id>.json` each), the 453 internal tokens no set declares, the 45
  theme variables no set can reach, the 18 sets with no recorded source, and the 28 sets
  between 8 and 27 on the bridge.
