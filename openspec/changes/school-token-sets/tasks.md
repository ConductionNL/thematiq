# Tasks: School token sets

## 1. Spec
- [x] 1.1 Proposal and the spec delta.

## 2. Shared
- [x] 2.1 `css/brand-stripe.css` draws `--nldesign-brand-stripe-image` when a set names one.
- [x] 2.2 `css/public-bridge.css`: the website radius roles and the heading face.
- [x] 2.3 Fonts: the seven families in `scripts/build-fonts.js`, both font directories, the OFL notices and `REUSE.toml`.

## 3. Token sets (each: token file, generated dark variant, overrides, four logos, manifest entry, reference page)
- [x] 3.1 `wilgenboom`
- [x] 3.2 `vaartveld`
- [x] 3.3 `esdoornveen`
- [x] 3.4 `warmtepompacademie`

## 4. Generated docs and counts
- [x] 4.1 Contrast report, token reference, coverage table, set and logo counts, brand identity, changelog.

## 5. Tests
- [x] 5.1 vitest: `tests/vitest/schoolTokenSets.spec.js` (palette, every pair in three schemes, overrides, faces, bridge), `workplaceLayout.spec.js` (stripe image, the sets with layout defaults).
- [x] 5.2 PHPUnit: `LayoutOptionsServiceTest` (the school layouts), `SetLogoReachTest` (the grey emblems).
- [x] 5.3 `bash scripts/token-set-gate.sh` runs the new spec.

## 6. Verify
- [ ] 6.1 Live check on a running instance (the coordinator does this after merge).
