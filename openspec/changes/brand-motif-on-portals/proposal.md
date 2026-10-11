---
kind: code
---

## Why

A portal that switched from an `example-*` set to `zuiddrecht` or one of the four school sets
looked worse than before. Measured on a live portal (portaliq, :8091, 5 October): with
`wilgenboom` in place of `example-basisschool` the site resolved 116 of the 489 component roles
it reads, against 489. There was no logo, the header and the DigiD button drew in browser
defaults and the footer lost its padding. The `example-*` sets carry a role layer of their own;
the newer sets declare only the `--nldesign-*` vocabulary, and the public bridge mapped only 85
roles from it.

The school designs also draw their motif under the website header and over its footer, use an
accent colour for the menu item on screen and for count badges, and show a light logo on the dark
footer. The motif token (`--nldesign-brand-stripe-*`, with `-image` for a motif that is not three
bands) reached only Nextcloud's own pages: `css/public-bridge.css`, the one file a portal links,
had no stripe line. The accent existed only as palette entries (`--<set>-color-accent`), which a
generic site cannot read.

## What Changes

- **The bridge carries the whole role layer the site reads.** Every role that resolved on
  `example-basisschool` and not on `wilgenboom` (375, listed in
  `tests/vitest/fixtures/portal-site-roles.json`) is declared in `css/public-bridge.css`:
  colours from the `--nldesign-*` layer with a fallback each, logos from `--nldesign-logo-url`,
  geometry (spacing, sizes, the type scale) as neutral values. A set with a role layer of its own
  loads after the bridge and keeps every value.
- **The brand stripe reaches the portal.** The bridge hands the stripe tokens to the site under
  the names the component library's `CnBrandStripe` reads (`--cn-brand-stripe-*`), plus
  `--cn-brand-stripe-image` and `--cn-brand-stripe-image-inverse`. No fallbacks: a set that
  declares no stripe draws none on its portal. `css/brand-stripe.css` hands the two image names to
  the library on Nextcloud's pages too.
- **A motif for a dark band.** A set may name `--nldesign-brand-stripe-image-inverse`, the same
  motif drawn for a dark band such as the footer. wilgenboom (light green twigs, no green line),
  vaartveld (the bank drawn white) and esdoornveen (the cut at the other end) name one. It is a
  variant of the one motif token, not a second mechanism.
- **The accent is vocabulary.** `--nldesign-color-accent`, `-accent-light` and `-accent-text`,
  declared by the four school sets and zuiddrecht. The bridge reads them into
  `--thematiq-accent-color`, `--thematiq-accent-light-color` and `--thematiq-accent-text-color`
  (falling back to the primary), and the navigation badge tokens into
  `--thematiq-badge-background-color` and `--thematiq-badge-color`.
- **The website logo and footer.** `--nldesign-website-logo-width`, `-height` and
  `-text-size` (0 when the logo carries the name, so the portal keeps its title for screen readers
  only), and `--nldesign-color-footer-background` and `-footer-text` in the five sets. The bridge
  shows the light logo on the footer when the portal names it in `--nldesign-logo-inverse-url`.
- wilgenboom's header motif gains the 3px green line the twigs hang from (17px high).

**Out of scope:** drawing the stripe, the header search and the footer button on the portal
(portaliq `site-chrome-follows-the-design`); the motif in `CnBrandStripe` (nextcloud-vue
`brand-motif-token`).

## Capabilities

### New Capabilities
- `brand-motif-on-portals`: the role layer a portal needs from any set, the stripe and its
  inverse on a portal, the accent vocabulary, the website logo and footer tokens.

## Impact

- Changed: `css/public-bridge.css`, `css/brand-stripe.css`, the token files of `wilgenboom`,
  `vaartveld`, `esdoornveen`, `warmtepompacademie` and `zuiddrecht` (with their generated dark
  variants and reference pages).
- Portals on a set with only `--nldesign-*` now draw buttons, form controls, the header account
  button and the footer from their own colours instead of browser defaults. Portals on a set with
  a role layer of its own (the `example-*` sets, vng, rotterdam) do not change.
- No migration. Additive, so a minor version.
