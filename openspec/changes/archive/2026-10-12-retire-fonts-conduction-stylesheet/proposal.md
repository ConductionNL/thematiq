---
kind: code
---

## Why

`css/fonts-conduction.css` declared Figtree and IBM Plex Mono, and no design system listed it in
`design-systems.json`, so nothing it declared ever loaded on an instance. It still looked like the
place those faces were served: a comment in `css/tokens/frankendesk.css` cited it for Inter (which it
never declared), a docblock in `TokenSetFontAuditService` named it, and a capability's evidence line
in `openspec/parity/capabilities.json` quoted its line numbers as proof of self-hosting.

Both faces are declared by `css/systems/nldesign/fonts.css`, which the nldesign system links, and by
`css/fonts.css`, which portaliq links for a public site. Inter is declared by
`css/systems/lasuite/fonts.css`. portaliq does not link the retired file (searched on its
development branch and in the organisation's default branches, 12 Oct 2026).

This is task 4.1 of `token-set-coverage-next-waves`, built as a change of its own as that plan asks.

## What Changes

- Remove `css/fonts-conduction.css`.
- Point the three citations at the stylesheets that do serve the faces.
- A test keeps every stylesheet that declares a typeface linked: by a design system, by the public
  site layer, or by the opt-in Marianne layer the injection service adds.

## Impact

- Nothing a visitor or an administrator sees changes: the file was never loaded.
- No setting, no migration, no version move.
