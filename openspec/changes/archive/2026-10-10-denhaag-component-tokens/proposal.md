---
kind: code
depends_on: []
---

# Proposal: denhaag-component-tokens

Part of the portal-design programme (2026-10-02). Ruben decided that the mijn-omgeving
components portaliq lacks (case card, process steps, action row, file item, contact timeline,
side navigation, data badge) adopt the `@gemeente-denhaag/*` CSS on portaliq's own Vue markup.
That CSS paints from `--denhaag-*` custom properties, and from `--nl-data-badge-*` for the
badge. This change makes thematiq feed those properties for every token set.

## Why

The Den Haag component CSS has no fallbacks. `@gemeente-denhaag/card` 5.1.4 reads 74
`var(--denhaag-*)` references and not one carries a default. A property nobody sets resolves
to its initial value: a transparent case card, an invisible step marker, a black process line.

Thematiq sets almost none of them. Measured on `development` `df5321f6`, 2026-10-02:

- `token-sets.json` lists 51 sets. 52 files sit in `css/tokens/`; `conduction.css` has no
  manifest entry.
- **Only 7 listed sets carry the full `--utrecht-*` role layer**: `vng`, `opencatalogi`,
  `rotterdam` and the four example school sets (570 to 901 declarations each). The unlisted
  `conduction.css` carries 437. `conduction-new` declares 32 and `utrecht` 7.
- **41 listed sets declare `--nldesign-*` only.** `denhaag`, `tilburg`, `amsterdam`,
  `rijkshuisstijl` and `nijmegen` are among them. `summer-breeze` declares neither family; its
  colours live in its own design-system layer.
- **The same 7 listed sets, plus `conduction.css`, declare Den Haag component tokens**, and
  only for some components: process steps, step marker and side navigation everywhere,
  contact timeline, action and file only in `rotterdam`.
- **No set declares a single case card token.** `--denhaag-case-card-*` is absent from all 52
  files.
- **The `denhaag` set declares six palette colours and no component token.** Den Haag's own
  theme cannot paint Den Haag's own components here.
- **The badge reads another family.** `@gemeente-denhaag/data-badge` 2.2.2 paints from
  `--nl-data-badge-{neutral,success,warning,error}-{color,background-color}`, not from
  `--denhaag-*`. `rotterdam` declares an older variant-less `--nl-data-badge-*` shape; no set
  declares the variant tokens the current package reads.

There is a bridge for exactly this problem. `css/public-bridge.css` maps the `--nldesign-*`
layer every set defines onto the `--utrecht-*`, `--tilburg-*` and `--conduction-*` roles a
public portal paints from, so one mapping serves every set. It maps 24 `--utrecht-*`
properties and no `--denhaag-*` property. And **portaliq does not link it**: no file under
portaliq's `lib/`, `src/` or `templates/` on `development` `b150def5` names `public-bridge`.
Portaliq's own open changes `portal-theme-blocks-and-contributed-pages` (task 2) and
`site-reaches-portal-parity` (design, line 151) plan the link and record that it is not there.
The portaliq lane of this programme specifies that link; this change does not touch portaliq.

The bridge's header comment is also stale. It says "of 46 shipped sets exactly one, `vng`,
carried the component roles". Today it is 7 of 51.

## What changes

- **The Den Haag mapping becomes data.** `scripts/mapping/denhaag-component-tokens.json`
  lists, per component, every `--denhaag-*` and `--nl-data-badge-*` property the pinned
  package CSS reads. A colour property names the `--nldesign-*` semantic token it follows,
  with `--utrecht-*` first where a set may carry one. A geometry property (gap, padding,
  radius, size, font size) carries the value from `@gemeente-denhaag/design-tokens-components`,
  pinned by version.
- **The bridge carries it.** A generator writes a delimited, generated section into
  `css/public-bridge.css`, so the one file portaliq links feeds both families. Every value
  carries a literal fallback, as the rest of the bridge does. A drift check fails when the
  committed section and the mapping disagree.
- **A set's own value still wins.** The bridge loads before the set, so `rotterdam`'s
  hand-chosen process steps and the school sets' contrast fixes stay.
- **The contrast guardrail covers the new pairs.** The shipped token set contrast audit
  resolves the bridge under each set and checks the text pairs the Den Haag components draw:
  step marker numbers on their discs, case card title and context on the card, the action
  row's date warning, badge text on badge fill, side navigation link states and file links.
  An unresolvable pair is `unevaluated` and never passes, as today.
- **The stale numbers go.** The bridge comment and `docs/features/public-portals-as-consumers.md`
  state the measured numbers above and name the portaliq change that links the bridge.

## What this change does not do

- It does not link the bridge in portaliq, add Den Haag CSS packages to portaliq or write any
  Vue markup. Those are portaliq's (`site-mijn-omgeving-components`, written by the portaliq
  lane).
- It does not change any `--nldesign-*` value of any set.
- It does not fix the dark variants for portals. `docs/features/public-portals-as-consumers.md`
  section 3 records that defect; it stays open.

## Builds on

- `nlds-theme-converter`: widens `CustomTokenSetValidator` to `--denhaag-*`, so an uploaded
  set may carry its own Den Haag values. This change supplies the defaults such a set
  overrides, and reads the converter's mapping-hash convention for its provenance line.
- `component-scoped-tokens`: the precedent for a mapping table read by a generator with a
  `--check` drift mode (`scripts/mapping/component-tokens.json`,
  `npm run test:component-scopes`). This change follows that shape. It does not add Den Haag
  tokens to the Nextcloud token editor, which edits Nextcloud UI components only.

## Capabilities

- New: `denhaag-component-tokens`.
- Modified: `token-set-contrast-audit` (the Den Haag pairs).

## Impact

`scripts/mapping/denhaag-component-tokens.json` (new), `scripts/generate-denhaag-bridge.mjs`
(new), `css/public-bridge.css` (a generated section), `package.json` (two scripts),
`lib/Service/ShippedTokenSetAuditService.php` (pairs), `docs/reference/contrast-report.md`
(regenerated), `docs/features/public-portals-as-consumers.md`, `REUSE.toml` (EUPL-1.2 for
the copied geometry values, which match the app's own licence).
