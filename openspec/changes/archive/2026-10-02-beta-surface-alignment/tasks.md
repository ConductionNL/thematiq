## Tasks

- [x] 1. Localise `appinfo/info.xml`: split `<name>`/`<summary>`/`<description>` into
      `lang="en"` / `lang="nl"` pairs with a real (non-copy) Dutch translation.
- [x] 2. Rewrite `info.xml` description (EN + NL) to cover the current canonical feature list:
      39 token sets, token editor, custom token sets, import/export, theming sync, per-app
      theming, icon/logo assets, WCAG 2.1 AA.
- [x] 3. Fix `<licence>agpl</licence>` → `<licence>eupl</licence>` (actual license is EUPL-1.2
      per LICENSE / composer.json / SPDX headers).
- [x] 4. Verify `info.xml` is well-formed XML after the edit (`php -r 'new DOMDocument ->
      load()'`).
- [x] 5. Correct the unverified "switchable per user or organisation" claim on the EN product
      page (`conduction-website/src/pages/apps/nldesign.mdx`) to the real, code-backed
      "per-app theming exclusion" feature.
- [x] 6. Apply the same correction to the NL product page
      (`conduction-website/i18n/nl/docusaurus-plugin-content-pages/apps/nldesign.mdx`).
- [x] 7. Soften the unverified "government-compliant typography" claim in both pages' meta
      `description` (typography is only partially implemented per
      `docs/reference/compliance.md`).
- [x] 8. Confirm version consistency: `info.xml` `-unstable.N` suffix vs. product page
      `version="v0.1"` — both already consistent at the fleet's major.minor convention; no
      change needed.
- [x] 9. Confirm `img/app.svg` matches the app-icon brand convention (white fill, 24×24) — no
      mismatch found.
- [x] 10. Confirm no `<dependency>` entries are missing — nldesign has no OpenRegister (or
      other app) runtime dependency; product-page mentions of OpenCatalogi/LaunchPad picking up
      the theme are via shared CSS variables, not a code dependency.
- [x] 11. Write `openspec/changes/beta-surface-alignment/proposal.md` documenting the
      canonical feature list and every reconciliation, and this `tasks.md`.
- [x] 12. Write `specs/beta-alignment/spec.md` delta capturing the cross-surface consistency
      requirement.

## Notes from the archive (2 Oct 2026)

- Checked against `development` before archiving: `appinfo/info.xml` carries `lang="en"` and
  `lang="nl"` pairs for name, summary and description with a translated Dutch text, and
  `<licence>EUPL-1.2</licence>`. `ClaimAccuracyTest::testManifestTextIsPairedInEnglishAndDutch`
  now guards the pairing; the licence was already guarded.
- The product-page corrections (tasks 5 to 7) live in `conduction-website` and are not part of
  this repository.
