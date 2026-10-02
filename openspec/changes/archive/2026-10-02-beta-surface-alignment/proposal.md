---
kind: code
---

## Why

nldesign is Technical Core and already migrated to /connext, but its four public-facing
surfaces — `appinfo/info.xml`, the conduction.nl product page (EN + NL), and the
`thematiq.conduction.nl` docs — had drifted apart, which blocks a clean beta release:

1. **`appinfo/info.xml` had no Dutch localisation.** `<name>`, `<summary>`, and
   `<description>` were single, English-only tags with no `lang` attribute, while the fleet
   convention (ADR-007, confirmed live on `opencatalogi/appinfo/info.xml`) is
   `<summary lang="en">` / `<summary lang="nl">` pairs. The single-language summary was also a
   literal English sentence, not a translated Dutch one — the exact anti-pattern ADR-007 exists
   to prevent.
2. **`info.xml`'s feature description was stale.** It only mentioned "design token sets" and
   named 5 of the app's 39 government token sets. It predated (and never mentioned) four
   features that have since shipped and are documented in `docs/features/` and
   `docs/features.json`: the token editor, custom token set upload ("eigen huisstijl"),
   import/export of overrides, theming sync, per-app theming exclusion, and the bundled
   icon/logo asset library.
3. **`<licence>agpl</licence>` was wrong.** The actual license is EUPL-1.2 — confirmed by
   `LICENSE` (EUPL v1.2 text), `composer.json` (`"license": "EUPL-1.2"`), and the SPDX header on
   every PHP file (`SPDX-License-Identifier: EUPL-1.2`). `agpl` was a copy-paste leftover from
   the Nextcloud app template. Fixed to `eupl`, matching the majority convention already used by
   `nextcloud-app-template`, `petstore`, `pipelinq`, `procest`, `hermiq`, and others.
4. **One product-page claim was unverifiable against the code.** Both the EN and NL
   `apps/nldesign.mdx` pages claimed the theme is "switchable per user or organisation" ("A
   gemeente can require NLDesign for external portals and leave it optional for internal
   workplaces"). There is no per-user or per-organisation theming toggle anywhere in
   `lib/` — theming is instance-wide and admin-configured; the only exclusion mechanism that
   exists is **per-app** exclusion (`docs/features.json` `per-app-theming` /
   `openspec/specs/per-app-theming/spec.md`, backing GOVERNMENT-FEATURES F-15). The claim was
   corrected to describe the feature that actually ships.
5. **The page meta-description overclaimed "Government-compliant".** Per
   `docs/reference/compliance.md` (Rijkshuisstijl Compliance Checklist), the app is 70%
   compliant with the official Rijkshuisstijl requirements: colour palette and accessibility are
   ✅ 100% implemented, but the Rijkslogo is ❌ not implemented and typography is ⚠️ only
   partially implemented (the font is declared in CSS but the webfont files are not bundled, so
   it falls back to system fonts). Asserting blanket "government-compliant typography" was not
   accurate; the copy now says "official NLDS … tokens" instead, which is accurate and doesn't
   assert a compliance level the app hasn't reached.
6. **Version and icon were already consistent** — `info.xml`'s `0.1.3-unstable.11` follows the
   same auto-versioned `-unstable.N` CI suffix convention used fleet-wide (confirmed on
   docudesk, launchpad, openregister, openklant, opentalk, openzaak, valtimo); the product
   page's `version="v0.1"` already matches at the major.minor granularity the fleet uses
   elsewhere (e.g. opencatalogi `v0.7` vs. `0.7.41`). No version string change was needed.
   `img/app.svg` is already a white-fill, 24×24 SVG per the app-icon brand convention — no
   mismatch found.

## What Changes

- Localise `appinfo/info.xml`: `<name>`, `<summary>`, `<description>` split into
  `lang="en"` / `lang="nl"` pairs; description rewritten to list the canonical, current feature
  set (see spec below); `<licence>` corrected from `agpl` to `eupl`.
- Correct `conduction-website/src/pages/apps/nldesign.mdx` (EN) and
  `conduction-website/i18n/nl/docusaurus-plugin-content-pages/apps/nldesign.mdx` (NL):
  - Page meta `description`: drop the unverified "government-compliant" claim.
  - `FeatureItem` "Switchable per user or organisation" → "Per-app theming exclusion",
    describing the real, code-backed feature.
- No changes needed to `docs/` (Docusaurus) — `docs/intro.md` and `docs/features/*.md` were
  already accurate and are now the vocabulary source `info.xml` and the product page were
  aligned to.
- No `<dependency>` entries added — nldesign has **no** OpenRegister (or other app) runtime
  dependency (confirmed via `src/manifest.json`'s observability note: "pure NL Design theme app
  with NO OpenRegister dependency"); the product page's OpenCatalogi/LaunchPad
  "automatically picks up the theme" language is correct because those apps consume shared
  Nextcloud CSS variables, not an nldesign API — no code dependency exists in either direction.

## Canonical feature vocabulary (source of truth: `docs/features.json` + `lib/Controller/`)

1. 39 pre-built government token sets (Rijkshuisstijl, VNG, provinces, municipalities) + stock
   Nextcloud + Summer Breeze alternative (41 total entries in `token-sets.json`, 39 are Dutch
   government organisations)
2. Token editor (fine-tune individual colours/tokens, admin settings)
3. Custom token sets ("eigen huisstijl" upload: CSS or W3C Design Tokens JSON)
4. Import & export of token overrides (CSS file download/upload)
5. Theming sync (aligns Nextcloud's built-in theming — primary colour, background, logo —
   with the selected token set)
6. Per-app theming exclusion (exclude individual apps from theming)
7. Icon & logo asset bundle (Amsterdam Design System icon set + Dutch government org logos,
   exposed to other apps via `IURLGenerator::imagePath`)
8. WCAG 2.1 AA colour contrast (verified per token set, `ContrastService` +
   `docs/reference/compliance.md`)

## Claims verified vs. removed

| Claim | Surface(s) | Verdict | Action |
|---|---|---|---|
| 39 pre-built themes | product page, docs/intro.md | ✅ Verified (41 `token-sets.json` entries − stock Nextcloud − Summer Breeze = 39 government sets) | Kept; added to `info.xml` description |
| Token editor / import-export | product page, docs | ✅ Verified (`CustomTokenSetController`, `OverridesController`, `docs/features/token-editor.md`, `docs/features/import-export.md`) | Kept; added to `info.xml` |
| WCAG-AA / Twdt-ready | product page | ✅ Verified (`docs/reference/compliance.md` accessibility section: 100% implemented, documented contrast ratios) | Kept |
| "Government-compliant typography" (page meta description) | product page (EN+NL) | ❌ Not verified — typography is only partially implemented (font declared, files not bundled) per `compliance.md` | Corrected to "official NLDS … tokens" |
| "Switchable per user or organisation" | product page (EN+NL) | ❌ Not verified — no per-user/org toggle in `lib/`; only per-app exclusion exists | Corrected to "Per-app theming exclusion" |
| `<licence>agpl</licence>` | info.xml | ❌ Wrong — actual license is EUPL-1.2 (LICENSE file, composer.json, all SPDX headers) | Corrected to `eupl` |
| Proven on opencatalogi.conduction.nl / docudesk.conduction.nl | product page | ✅ Plausible — both apps reference nldesign in openspec specs (`docudesk/openspec/specs/print-preview/spec.md`) and consume the same standard Nextcloud CSS variables | Kept |

## Impact

- Affected specs: none (new spec below)
- Affected code: `appinfo/info.xml` only (metadata, no behaviour change)
- Affected surfaces outside this repo: `conduction-website/src/pages/apps/nldesign.mdx`,
  `conduction-website/i18n/nl/docusaurus-plugin-content-pages/apps/nldesign.mdx`
